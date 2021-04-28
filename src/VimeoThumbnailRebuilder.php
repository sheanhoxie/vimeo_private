<?php

/** @file
 *  Provides the functionality for updating vimeo thumbnails
 */

namespace Drupal\vimeo_thumbnail_rebuilder;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Vimeo\Vimeo;

class VimeoThumbnailRebuilder {

  private static $clientId;
  private static $clientSecret;
  private static $apiToken;

  public static function vimeoCredentials() {
    // @todo refactor
    $state = \Drupal::state();
    self::$clientId = $state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_id');
    self::$clientSecret = $state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_secret');
    self::$apiToken = $state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.api_token');

    switch (FALSE) {
      case isset(self::$clientId):
      case isset(self::$clientSecret):
      case isset(self::$apiToken):
        $messenger = \Drupal::messenger();
        $messenger->addWarning(t('You need to set your Vimeo credentials.'));
        return FALSE;
    }

    return TRUE;
  }

  /**
   * Save the image and create the thumbnail
   *
   * @param $video
   * @param $image_style
   *
   * @return bool
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public static function rebuildThumbnail($video, ImageStyle $image_style) {
    /**
     * @todo Take all of this thumbnail stuff combine and move into method,
     *       return the thumbnail info
     *       - $thumbnailDetails = getThumbnailDetails($video)
     * $thumbnail_url = https://i.vimeocdn.com/video/904424857_640x360.jpg
     */
    $thumbnail_url = self::getThumbnailUrl($video);

    /**
     * $thumbnail_info = [
     *  filename = '12345678_640x360.jpg'
     *  video_id = '12345678'
     *  dimensions = '640x360'
     *  extension = 'jpg'
     * ]
     */
    $thumbnail_info = self::getThumbnailInfo($thumbnail_url);

    /**
     * @todo Take all of this image get/save stuff and move into method,
     *       return the image after it's been saved to attach it to video
     *       - $image = getImage($thumbnailDetails)
     *
     * $image_dest = 'public://styles/large/public/904424857_640x360.jpg'
     */
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

    $video->thumbnail->target_id = $image->id();
    $video->save();

    return TRUE;
  }

  private static function vimeo() {
    if (self::vimeoCredentials()) {
      return new Vimeo(self::$clientId, self::$clientSecret, self::$apiToken);
    }
  }

  /**
   * Returns Media of type 'vimeo'
   *
   * @param  string|null  $id  The ID of the media to be rebuilt, if NULL all
   *                           media will be returned.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function loadVimeoMedia(string $id = NULL): array {
    $entityTypeManager = \Drupal::entityTypeManager();
    $media_storage = $entityTypeManager->getStorage('media');
    $media = $media_storage->getQuery()
      ->condition('bundle', 'vimeo')
      ->exists('field_media_oembed_video');

    if ($id) {
      $media->condition('mid', $id);
    }

    return $media_storage->loadMultiple($media->execute());
  }

  /**
   * Get the thumbnail image from Vimeo.com
   *
   * @param \Drupal\media\Entity\Media $video
   *
   * @return string
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public static function getThumbnailUrl(Media $video) {
    // @todo refactor this
    if (!$video->get('field_media_oembed_video')->value) {
      return FALSE;
    }

    $video_id = self::getVimeoId($video->get('field_media_oembed_video')->value);
    $vimeo_response = self::requestVimeo($video_id);

    if ($thumbnail_url = isset($vimeo_response['body']['pictures'])) {
      $parsed_thumb = UrlHelper::parse($vimeo_response["body"]["pictures"]["sizes"][3]["link"]);
      $thumbnail_url = $parsed_thumb['path'];
    }

    return $thumbnail_url;
  }

  public static function requestVimeo($vimeo_id) {
    $vimeo = self::vimeo();
    return $vimeo->request('/videos/' . $vimeo_id, [], 'GET');
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
  public static function getVimeoId($url) {
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
  public static function getThumbnailInfo($url) {
    $vimeo_id['filename'] = self::getVimeoId($url);
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

}
