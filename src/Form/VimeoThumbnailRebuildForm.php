<?php
/**
 * @file
 * Contains Drupal\vimeo_thumbnail_rebuilder\Form\RebuildVimeoThumbnailsForm
 */

namespace Drupal\vimeo_thumbnail_rebuilder\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Vimeo\Vimeo;

/**
 * Provides a form to rebuild vimeo thumbnails
 */
class VimeoThumbnailsRebuildForm extends FormBase {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $current_user;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * @var \Vimeo\Vimeo
   */
  protected $vimeo;

  /**
   * @var bool
   */
  private $credentials_set = FALSE;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $image_styles;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $media_entity;

  /**
   * Constructs a new \Drupal\vimeo_thumbnail_rebuilder\Form\RebuildVimeoThumbnailsForm
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Vimeo\Vimeo $vimeo
   *  The vimeo client
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entityTypeManager, LoggerChannelInterface $logger, MessengerInterface $messenger, StateInterface $state, Vimeo $vimeo) {
    $this->logger = $logger;
    $this->state = $state;
    $this->messenger = $messenger;
    $this->current_user = $current_user;
    $this->entityTypeManager = $entityTypeManager;
    $this->image_styles = $entityTypeManager->getStorage('image_style')->getQuery()->execute();
    $this->media_entity = $entityTypeManager->getStorage('media');

    // Build the Vimeo client
    $client_id = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_id');
    $client_secret = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_secret');
    $api_token = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.api_token');

    if (isset($client_id) && isset($client_secret) && isset($api_token)) {
      $this->credentials_set = TRUE;
    }
    else {
      $message = t('You need to set your Vimeo credentials');
      $this->messenger->addWarning($message);
    }
    $this->vimeo = new Vimeo($client_id, $client_secret, $api_token);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('vimeo_thumbnail_rebuilder'),
      $container->get('messenger'),
      $container->get('state'),
      $container->get('vimeo_thumbnail_rebuilder.vimeo')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rebuild_vimeo_thumbnails_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('vimeo_thumbnail_rebuilder.settings');
    $default_style = $config->get('default_style');

    $form['scope'] = [
      '#type' => 'select',
      '#title' => t('Choose which thumbnails to rebuild:'),
      '#options' => [
        'missing' => t('Rebuild MISSING Vimeo thumbnails'),
        'all' => t('Rebuild ALL Vimeo thumbnails'),
      ],
      '#default_value' => 'missing',
      '#required' => TRUE,
    ];

    $form['image_style'] = [
      '#type' => 'select',
      '#title' => t('Choose image style:'),
      '#options' => $this->image_styles,
      '#default_value' => isset($default_style) ? $default_style : '',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update all Vimeo thumbnails'),
      '#disabled' => !$this->credentials_set ?: FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get all Vimeo videos
    $videos = $this->loadAllVimeoMedia();

    $batch_videos = [];
    /** @var \Drupal\media\Entity\Media $video */
    foreach ($videos as $video) {
      $batch_videos[] = [
        'video' => $video,
        'thumbnail_tid' => $video->thumbnail->target_id,
      ];
    }

    $scope = $form_state->getValue('scope');

    $image_style = ImageStyle::load($form_state->getValue('image_style'));
    // make sure the image style directory exists and is writeable
    $directory = file_default_scheme() . '://styles/' . $image_style->getName();
    if (!$prepare_destination = file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      return $form_state->setErrorByName('Unable to write to image style directory');
    }


    $batch = [
      'title' => t('Vimeo Thumbnail Rebuilder'),
      'init_message' => t('<h2>Preparing to batch process Vimeo thumbnails...</h2>'),
      'operations' => [
        [[$this, 'batchThumbnailRebuild'], [$batch_videos, $image_style, $scope]],
      ],
      'finished' => [$this, 'batchFinished'],
    ];

    batch_set($batch);
  }

  /**
   * Batch process the video thumbnails rebuild
   *
   * @param $videos
   * @param $image_style
   * @param $context
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public function batchThumbnailRebuild($videos, $image_style, $scope, &$context) {

    // Setup the sandbox
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($videos);
      $context['results']['processed'] = 0;
      $context['results']['skipped'] = 0;
      $context['results']['errored'] = 0;
    }

    // Show progress message
    $context['message'] = t('<h2>Processing @current of @max videos.</h2>', [
      '@current' => $context['sandbox']['progress'],
      '@max' => $context['sandbox']['max']
    ]);

    // Current video to process
    $current_index = $context['sandbox']['progress'];
    /** @var \Drupal\media\Entity\Media $video */
    $video = $videos[$current_index]['video'];
    $thumbnail_tid = $videos[$current_index]['thumbnail_tid'];

    /** @var File $thumbnail */
    $thumbnail = File::load($thumbnail_tid);
    // Only build the thumbnail if it's missing or using the default thumbnail
    if ($scope === 'all' || !$thumbnail->getFilename() || $thumbnail->getFilename() == 'video.png') {
      if ($thumbnail_file = self::createThumbnailFromVideo($video, $image_style)) {
        $video->thumbnail->target_id = $thumbnail_file->id();
        $video->save();
        $context['results']['processed']++;
      }
      else {
        $this->logger->log(RfcLogLevel::WARNING, "No thumbnail found via Vimeo API for media id: @video", ['@video' => $video->id()]);
        $context['results']['errored']++;
      }
    } else {
      $context['results']['skipped']++;
    }

    // Iterate progress
    $context['sandbox']['progress']++;

    // Finish if we've reached max
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];

  }

  /**
   * Batch API callback
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   An array of function calls (not used in this function).
   */
  public function batchFinished($success, array $results, array $operations) {
    if (!$success) {
      $this->messenger()->addStatus(t('Finished with an error.'));
    }
    elseif ($results['errored'] > 0) {
      $db_log_link = Link::createFromRoute('DB logs', 'dblog.overview');
      $this->messenger()->addStatus(t('Rebuilt @success_count thumbnails. Skipped @skipped_count. Errors on @error_count thumbnails. Check @db_logs for error details.', [
        '@success_count' => $results['processed'],
        '@error_count' => $results['errored'],
        '@db_logs' => $db_log_link,
        ]));

    }
    else {
      $this->messenger()->addStatus(t('Rebuilt @success_count thumbnails. Skipped @skipped_count.', [
        '@success_count' => $results['processed'],
        '@skipped_count' => $results['skipped'],
      ]));
    }
  }

  /**
   * Returns all existing Media of type 'vimeo'
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  private function loadAllVimeoMedia() {
    $media_storage = $this->media_entity;
    $media_id = $media_storage->getQuery()
      ->condition('bundle', 'vimeo')
      ->exists('field_media_oembed_video')
      ->execute();

    $videos = Media::loadMultiple($media_id);

    return $videos;
  }

  /**
   * Get the thumbnail image from Vimeo.com
   *
   * @param \Drupal\media\Entity\Media $video
   *
   * @return string
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  private function getThumbnailUrl(Media $video) {
    if (!$video->get('field_media_oembed_video')->value) {
      return FALSE;
    }

    $video_id = $this->getVimeoIDFromUrl($video->get('field_media_oembed_video')->value);
    $vimeo_response = $this->vimeo->request('/videos/' . $video_id, [], 'GET');

    if ($thumbnail_url = isset($vimeo_response['body']['pictures'])) {
      $parsed_thumb = UrlHelper::parse($vimeo_response["body"]["pictures"]["sizes"][3]["link"]);
      $thumbnail_url = $parsed_thumb['path'];
    }

    return $thumbnail_url;
  }

  /**
   * Get the Vimeo video ID
   *
   * @param string $url
   *  Video, or thumbnail url
   *
   * @return string
   *  The vimeo video id. Can return with file extension
   */
  private function getVimeoIDFromUrl($url) {
    $url_array = explode('/', $url);
    $video_id = array_pop($url_array);

    return $video_id;
  }

  /**
   * Break the vimeo image url into separate parts
   *
   * @param $url
   *
   * @return array
   */
  private function getThumbnailInfo($url) {
    $vimeo_id['filename'] = $this->getVimeoIDFromUrl($url);
    // strip the appended dimensions tag and extension from the filename
    if ($dimensions = explode('_', $vimeo_id['filename'])) {
      $vimeo_id['video_id'] = $dimensions[0];
      // break the dimensions and extension apart and save
      if ($extension = explode('.', $dimensions[1])) {
        $vimeo_id['dimensions'] = $extension[0];
        $vimeo_id['extension'] = $extension[1];
      }
    }

    return $vimeo_id;
  }

  /**
   * Save the image and create the thumbnail
   *
   * @param $video
   * @param $image_style
   *
   * @return \Drupal\file\FileInterface
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  private function createThumbnailFromVideo($video, ImageStyle $image_style) {
    $thumbnail_url = self::getThumbnailUrl($video);
    $thumbnail_info = $this->getThumbnailInfo($thumbnail_url);

    // Set the full size image destination
    $image_dest = $image_style->buildUri($thumbnail_info['filename']);

    // Get the image from vimeo, and save it locally
    $image_data = file_get_contents($thumbnail_url);
    /** @var \Drupal\file\FileInterface $image */
    $image = file_save_data($image_data, $image_dest, FileSystemInterface::EXISTS_REPLACE);

    // Create the thumbnail
    $image_uri = $image->getFileUri();
    $thumb_dest = $image_style->buildUri($thumbnail_info['video_id'] . '_' . $image_style->getName() . '.' . $thumbnail_info['extension']);
    $image_style->createDerivative($image_uri, $thumb_dest);

    $image->setFileUri($thumb_dest);
    $image->save();

    return $image;
  }
}
