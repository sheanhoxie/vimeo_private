<?php

/**
 * @file
 * Contains Drupal\vimeo_private\Form\RebuildVimeoThumbnailsForm
 */

namespace Drupal\vimeo_private\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\vimeo_private\VimeoPrivate;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to rebuild vimeo thumbnails
 */
class VimeoPrivateRebuildThumbnailsForm extends FormBase {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

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
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $imageStyles;

  /**
   * Constructs a new RebuildVimeoThumbnailsForm
   *
   * @param  \Drupal\Core\Session\AccountInterface           $currentUser
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entityTypeManager
   * @param  \Drupal\Core\Logger\LoggerChannelInterface      $logger
   * @param  \Drupal\Core\Messenger\MessengerInterface       $messenger
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AccountInterface $currentUser, EntityTypeManagerInterface $entityTypeManager, LoggerChannelInterface $logger, MessengerInterface $messenger) {
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;
    $this->imageStyles = $entityTypeManager->getStorage('image_style')
      ->getQuery()
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('vimeo_private'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vimeo_private_rebuild_thumbnails_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_image_style = VimeoPrivate::getDefaultImageStyle();

    $form['image_style'] = [
      '#type'          => 'select',
      '#title'         => t('Choose image style:'),
      '#options'       => $this->imageStyles,
      '#empty_option' => $this->t('No default image style'),
      '#default_value' => isset($default_image_style) ? $default_image_style : '',
      '#required'      => TRUE,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Update all Vimeo thumbnails'),
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
    $batch_videos = [];
    /** @var \Drupal\media\Entity\Media $video */
    foreach (VimeoPrivate::loadVimeoMedia() as $video) {
      $batch_videos[] = [
        'video'         => $video,
        'thumbnail_tid' => $video->thumbnail->target_id,
      ];
    }

    $batch = [
      'title'        => t('Vimeo Thumbnail Rebuilder'),
      'init_message' => t('<h2>Preparing to batch process Vimeo thumbnails...</h2>'),
      'operations'   => [
        [
          [$this, 'batchThumbnailRebuild'],
          [$batch_videos, $form_state->getValue('image_style')],
        ],
      ],
      'finished'     => [$this, 'batchFinished'],
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
  public function batchThumbnailRebuild($videos, $image_style, &$context) {
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
      '@max'     => $context['sandbox']['max'],
    ]);

    // Current video to process
    $current_index = $context['sandbox']['progress'];

    try {
      VimeoPrivate::rebuildThumbnail($videos[$current_index]['video'], $image_style);
      $context['results']['processed']++;
    } catch (EntityStorageException $exception) {
      $context['results']['errored']++;
    }

    // Iterate progress
    $context['sandbox']['progress']++;

    // Finish if we've reached max
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch API callback
   *
   * @param  bool   $success
   *   TRUE if batch successfully completed.
   * @param  array  $results
   *   Batch results.
   * @param  array  $operations
   *   An array of function calls (not used in this function).
   */
  public function batchFinished($success, array $results, array $operations) {
    if (!$success) {
      $this->messenger()->addStatus(t('Finished with an error.'));
    } elseif ($results['errored'] > 0) {
      $db_log_link = Link::createFromRoute('DB logs', 'dblog.overview');
      $this->messenger()
        ->addStatus(t('Rebuilt @success_count thumbnails. Skipped @skipped_count. Errors on @error_count thumbnails. Check @db_logs for error details.', [
          '@success_count' => $results['processed'],
          '@error_count'   => $results['errored'],
          '@db_logs'       => $db_log_link,
        ]));
    } else {
      $this->messenger()
        ->addStatus(t('Rebuilt @success_count thumbnails. Skipped @skipped_count.', [
          '@success_count' => $results['processed'],
          '@skipped_count' => $results['skipped'],
        ]));
    }
  }

}
