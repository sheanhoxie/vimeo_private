<?php
/**
 * @file
 * Class Vimeo Thumbnail Rebuilder
 */

namespace Drupal\vimeo_thumbnail_rebuilder;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;
use Vimeo\Vimeo;

class VimeoThumbnailRebuilder {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

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
   * VimeoThumbnailRebuilder constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelInterface $logger, AccountInterface $current_user, EntityTypeManager $entityTypeManager, MessengerInterface $messenger) {
    $this->configFactory = $configFactory;
    $this->logger = $logger;
    $this->current_user = $current_user;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
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
    $vimeo_config = $this->configFactory->get('vimeo_thumbnail_rebuilder.vimeo_credentials');
    /** @var Vimeo $vimeo_client */
    $vimeo_client = new Vimeo($vimeo_config->get('client_id'), $vimeo_config->get('client_secret'), $vimeo_config->get('api_token'));

    $videos = $this->loadAllVimeoMedia();

    $count = 0;
    foreach ($videos as $video) {
      $thumbnail = $video->thumbnail->target_id;

      /** @var File $file */
      $file = File::load($thumbnail);
      if (!$file->getFilename() || $file->getFilename() == 'video.png') {
        if ($thumbnail_url = $this->getThumbnailUrl($vimeo_client, $video)) {
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
   * @param \Vimeo\Vimeo $client
   * @param \Drupal\media\Entity\Media $video
   *
   * @return string
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  private function getThumbnailUrl(Vimeo $client, Media $video) {
    $video_id = $this->getVimeoVideoID($video);

    $vimeo_response = $client->request('/videos/' . $video_id, [], 'GET');

    $thumbnail_url = '';
    if (isset($vimeo_response['body']['pictures'])) {
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
  private function getVimeoVideoID(Media $video) {
    $url = explode('/', $video->get('field_media_oembed_video')->value);
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
    /** @var \Drupal\user\Entity\User $owner */
    $owner = User::load(\Drupal::currentUser()->id());
    /** @var \Drupal\file\FileInterface $file */
    $file = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->create([
        'uri' => $thumbnail_uri,
      ])
      ->setOwner($owner);
    $file->setPermanent();

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
