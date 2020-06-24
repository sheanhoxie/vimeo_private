<?php
/**
 * @file
 * Class VimeoThumbnailRebuilder
 */

namespace Drupal\vimeo_thumbnail_rebuilder;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Vimeo\Vimeo;

class VimeoThumbnailRebuilder {

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $current_user;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * @var \Vimeo\Vimeo
   */
  private $vimeo;

  /**
   * VimeoThumbnailRebuilder constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Vimeo\Vimeo $vimeo
   */
  public function __construct(StateInterface $state, LoggerChannelInterface $logger, AccountInterface $current_user, EntityTypeManager $entityTypeManager, MessengerInterface $messenger) {
    $this->logger = $logger;
    $this->current_user = $current_user;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->state = $state;

    $client_id = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_id');
    $client_secret = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_secret');
    $api_token = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.api_token');

    if (!isset($client_id) || !isset($client_secret) || !isset($api_token)) {
      $this->messenger->addMessage(t('Vimeo credentials missing.'), 'error');
    }

    /** @var Vimeo $vimeo_client */
    $this->vimeo = new Vimeo($client_id, $client_secret, $api_token);
  }

  /**
   * Rebuild any vimeo thumbnails that are missing, or stored as the default
   * thumbnail 'video.png'
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public function rebuildMissingVimeoThumbnails() {
    $videos = $this->loadAllVimeoMedia();

    $count = 0;
    /** @var \Drupal\media\Entity\Media $video */
    foreach ($videos as $video) {
      $thumbnail = $video->thumbnail->target_id;

      /** @var File $file */
      $file = File::load($thumbnail);
      if (!$file->getFilename() || $file->getFilename() == 'video.png') {
        $vimeo_id = $this->getVimeoVideoID($video->get('field_media_oembed_video')->value);
        if ($thumbnail_url = $this->getThumbnailUrl($vimeo_id)) {
          $thumbnail_file = $this->createThumbnailFromUrl($thumbnail_url);
          $video->thumbnail->target_id = $thumbnail_file->id();
          $video->save();
          $count++;
        } else {
          $this->logger->log(RfcLogLevel::WARNING, "No thumbnail found for media id: @video", ['@video' => $video->id()]);
        }
      }
    }

    $this->messenger->addMessage(t('@count thumbnails updated', ['@count' => $count]));
  }

  /**
   * Returns all existing Media of type 'vimeo'
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadAllVimeoMedia() {
    $media_storage = $this->entityTypeManager->getStorage('media');
    $media_id = $media_storage->getQuery()
      ->condition('bundle', 'vimeo')
      ->exists('field_media_oembed_video')
      ->execute();

    $videos = Media::loadMultiple($media_id);

    return $videos;
  }

  /**
   * Get the thumbnail from Vimeo.com
   *
   * @param string $vimeo_id
   *
   * @return string
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public function getThumbnailUrl($vimeo_id) {
    $vimeo_response = $this->vimeo->request('/videos/' . $vimeo_id, [], 'GET');

    if ($thumbnail_url = isset($vimeo_response['body']['pictures'])) {
      $parsed_thumb = UrlHelper::parse($vimeo_response["body"]["pictures"]["sizes"][1]["link"]);
      $thumbnail_url = $parsed_thumb['path'];
    }

    return $thumbnail_url;
  }

  /**
   * Return the Vimeo video ID
   *
   * @param \Drupal\media\Entity\Media $video
   *
   * @return mixed
   */
  private function getVimeoVideoID($video_uri) {
    $url = explode('/', $video_uri);
    $video_id = array_pop($url);

    return $video_id;
  }

  /**
   * Save the image and create the thumbnail
   *
   * @param $video
   * @param $thumbnail_uri
   *
   * @return \Drupal\file\FileInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createThumbnailFromUrl($thumbnail_uri) {
    $image_data = file_get_contents($thumbnail_uri);
    $image_destination = 'public://oembed_thumbnails/' . $this->getVimeoVideoID($thumbnail_uri);

    /** @var \Drupal\file\FileInterface $file */
    $file = file_save_data($image_data, $image_destination, FILE_EXISTS_REPLACE);

    $image_uri = $file->getFileUri();
    $image_array = explode("/", $image_uri);
    $img_name = array_pop($image_array);

    $image_style = ImageStyle::load('thumbnail');
    $destination_uri = $image_style->buildUri('public://oembed_thumbnails/' . $img_name);
    $image_style->createDerivative($image_uri, $destination_uri);

    $file->setFileUri('public://oembed_thumbnails/' . $img_name);
    $file->save();

    return $file;
  }

}
