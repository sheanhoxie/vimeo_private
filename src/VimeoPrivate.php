<?php

/** @file
 *  Provides the functionality for creating & updating Vimeo thumbnails that are
 *  set as private on Vimeo.com
 */

namespace Drupal\vimeo_private;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Vimeo\Vimeo;

/**
 * Class VimeoThumbnailRebuilder
 *
 * @package Drupal\vimeo_private
 */
class VimeoPrivate {

  /**
   * Returns the vimeo credentials used to make the Vimeo request
   *
   * @return array|bool
   */
  public static function credentials() {
    $state = \Drupal::state();

    // Return warning if any credentials are not set
    switch (NULL) {
      case $id = $state->get('vimeo_private.credentials.client_id'):
      case $secret = $state->get('vimeo_private.credentials.client_secret'):
      case $token = $state->get('vimeo_private.credentials.api_token'):
        return FALSE;
    }

    return [
      'client_id'     => $id,
      'client_secret' => $secret,
      'api_token'     => $token
    ];
  }

  /**
   * Requests details of a video from Vimeo using the Vimeo Developers API
   *
   * @param  Media  $media
   *
   * @return \Drupal\vimeo_private\VimeoPrivateResponse
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public static function vimeoRequest($vimeo_id) {
    if ($credentials = self::credentials()) {
      $vimeo = new Vimeo($credentials['client_id'], $credentials['client_secret'], $credentials['api_token']);
      $response = $vimeo->request('/videos/' . $vimeo_id, [], 'GET');

      return new VimeoPrivateResponse($response['body']);
    }

    return NULL;
  }

  /**
   * Retrieves a video's details from Vimeo.com, and sets the latest image as
   * the $media thumbnail.
   *
   * @param  Media   $media
   */
  public static function rebuildThumbnail(Media $media) {

    // Create the new image from the
    $image = self::createImageFromMedia($media);

    // Set the new thumbnail and save the Media
    $media->thumbnail->target_id = $image->id();
    $media->save();

    \Drupal::messenger()->addMessage(t('Thumbnail %name updated.', [
      '%name' => $media->getName(),
    ]));
  }

  /**
   * Overwrites the existing media's image with a new image
   *
   * @param  Media  $media
   *
   * @return \Drupal\file\FileInterface|false
   */
  private static function createImageFromMedia(Media $media) {
    // Request video details from Vimeo
    $vimeo_id = self::getVimeoIdFromMedia($media);
    $vimeo_response = self::vimeoRequest($vimeo_id);

    // Build image uri
    $image_url = self::getImageUrlFromResponse($vimeo_response);
    $thumbnail_uri = 'public://oembed_thumbnails/' . $vimeo_response->id() . '.jpg';

    $image_data = file_get_contents($image_url);
    $image = file_save_data($image_data, $thumbnail_uri, FileSystemInterface::EXISTS_REPLACE);

    // Clear all the images errrwhere
    foreach (ImageStyle::loadMultiple() as $style) {
      $style->flush();

      \Drupal::logger('savvier_members')->notice('Flushing style %style', ['%style' => $style->getName()]);
    }

    return $image;
  }

  /**
   * Returns the thumbnail url from the Vimeo response
   *
   * @param  VimeoPrivateResponse  $vimeo_response
   *
   * @return string|null
   */
  public static function getImageUrlFromResponse(VimeoPrivateResponse $vimeo_response) {
    if ($vimeoImage = $vimeo_response->defaultPicture()) {
      $vimeoImage = UrlHelper::parse($vimeoImage['link'])['path'];
    }

    return $vimeoImage;
  }

  /**
   * Returns the Vimeo video ID
   *
   * @param  \Drupal\media\Entity\Media  $media
   *
   * @return string
   *  The vimeo video id. Can return with file extension
   */
  public static function getVimeoIdFromMedia(Media $media) {
    $url = $media->get('field_media_oembed_video')->value;
    $array = explode('/', $url);

    return array_pop($array);
  }

  public static function getDefaultImageStyle() {
    $config = \Drupal::config('vimeo_private.settings');
    return $config->get('default_style');
  }

  /**
   * Returns Media of type 'vimeo'
   *
   * @param  string|null  $id  The ID of the media to be rebuilt, if NULL all
   *                           Vimeo media will be returned.
   *
   * @return EntityInterface|EntityInterface[]
   */
  public static function loadVimeoMedia(string $id = NULL) {
    $mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
    $media = $mediaStorage->getQuery()->condition('bundle', 'vimeo');

    if ($id) {
      $media->condition('mid', $id);
      $media = $media->execute();
      return $mediaStorage->load($media);
    }

    return $mediaStorage->loadMultiple($media->execute());
  }

}
