<?php

/**
 * @file
 * Class VimeoThumbnailRebuilder
 */

namespace Drupal\vimeo_thumbnail_rebuilder;


use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Vimeo\Vimeo;

class VimeoThumbnailRebuilder {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * VimeoThumbnailRebuilder constructor.
   *
   * @param  \Drupal\Core\Entity\EntityTypeManagerInterface  $entityTypeManager
   * @param  \Drupal\Core\State\StateInterface               $state
   * @param  \Drupal\Core\Messenger\MessengerInterface       $messenger
   * @param  \Vimeo\Vimeo                                    $vimeo
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, StateInterface $state, MessengerInterface $messenger, Vimeo $vimeo) {
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
    $this->messenger = $messenger;

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

  public function vimeoCredentialsSet() {
    return FALSE;
  }

  /**
   * Returns all existing Media of type 'vimeo'
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  public function loadAllVimeoMedia() {
    $media_storage = $this->entityTypeManager->getStorage('media');
    $media_id = $media_storage->getQuery()
      ->condition('bundle', 'vimeo')
      ->exists('field_media_oembed_video')
      ->execute();

    return Media::loadMultiple($media_id);
  }

  /**
   * Get the thumbnail image from Vimeo.com
   *
   * @param \Drupal\media\Entity\Media $video
   *
   * @return string
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public function getThumbnailUrl(Media $video) {
    // @todo refactor this
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
  public function getVimeoIDFromUrl($url) {
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
  public function getThumbnailInfo($url) {
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
  public function createThumbnailFromVideo($video, ImageStyle $image_style) {
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
