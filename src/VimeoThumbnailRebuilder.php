<?php

/** @file
 *  Provides the functionality for creating & updating Vimeo thumbnails
 */

namespace Drupal\vimeo_thumbnail_rebuilder;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Vimeo\Vimeo;

/**
 * Class VimeoThumbnailRebuilder
 *
 * @package Drupal\vimeo_thumbnail_rebuilder
 */
class VimeoThumbnailRebuilder {

  /**
   * Returns the vimeo credentials used to make the Vimeo request
   *
   * @return array
   */
  private static function credentials() {
    $state = \Drupal::state();

    return [
      'client_id'     => $state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_id'),
      'client_secret' => $state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_secret'),
      'api_token'     => $state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.api_token'),
    ];
  }

  /**
   * Requests details of a video from Vimeo using the Vimeo Developers API
   *
   * @param  string  $vimeo_id
   *
   * @return \Drupal\vimeo_thumbnail_rebuilder\VimeoResponse
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public static function vimeoRequest(string $vimeo_id) {
    $credentials = self::credentials();
    $vimeo = new Vimeo($credentials['client_id'], $credentials['client_secret'], $credentials['api_token']);
    $response = $vimeo->request('/videos/' . $vimeo_id, [], 'GET');

    return new VimeoResponse($response['body']);
  }

  /**
   * Retrieves a Vimeo Media object's active picture from Vimeo.com, and sets it
   * as the thumbnail.
   *
   * @param  Media   $media
   * @param  string  $image_style
   *
   * @return bool
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Vimeo\Exceptions\VimeoRequestException
   */
  public static function rebuildThumbnail(Media $media, string $image_style) {
    // Request video details from Vimeo
    $vimeo_id = self::getVimeoIdFromMedia($media);
    $vimeo_response = self::vimeoRequest($vimeo_id);

    // Save the thumbnail derivative
    /** @var ImageStyle $image_style */
    $image_style = ImageStyle::load($image_style);
    $image = self::createImagesFromResponse($vimeo_response, $image_style);

    // Set the new thumbnail and save the Media
    $media->thumbnail->target_id = $image->id();
    return $media->save();
  }

  /**
   * Creates the thumbnail image
   *
   * @param  \Drupal\vimeo_thumbnail_rebuilder\VimeoResponse  $vimeo_response
   * @param  ImageStyle                                       $image_style
   *
   * @return \Drupal\file\FileInterface|false
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private static function createImagesFromResponse(VimeoResponse $vimeo_response, ImageStyle $image_style) {
    // Build image uri
    $image_url = self::getImageUrlFromResponse($vimeo_response);
    $image_name = self::getImageNameFromResponse($vimeo_response);
    $image_style_uri = $image_style->buildUri($image_name);

    // Retrieve the image from Vimeo and save it
    $image_data = file_get_contents($image_url);
    $image = file_save_data($image_data, $image_style_uri, FileSystemInterface::EXISTS_REPLACE);

    // Flush the image style for this image
    $image_style->flush($image_style_uri);

    return $image;
  }

  /**
   * Returns the thumbnail url from the Vimeo response
   *
   * @param  VimeoResponse  $vimeo_response
   *
   * @return string|null
   */
  public static function getImageUrlFromResponse(VimeoResponse $vimeo_response) {
    if ($vimeoImage = $vimeo_response->defaultPicture()) {
      $vimeoImage = UrlHelper::parse($vimeoImage['link'])['path'];
    }

    return $vimeoImage;
  }

  /**
   * Returns the image name from it's url
   *
   * @param \Drupal\vimeo_thumbnail_rebuilder\VimeoResponse
   *
   * @return mixed|string
   */
  private static function getImageNameFromResponse(VimeoResponse $vimeo_response) {
    $url = self::getImageUrlFromResponse($vimeo_response);
    $url_elements = explode('/', $url);

    return array_pop($url_elements);
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
    $config = \Drupal::config('vimeo_thumbnail_rebuilder.settings');
    return $config->get('default_style');
  }

  /**
   * Returns the height and width of the default image style as set in
   * vimeo_thumbnail_rebuilder.settings.default_style
   *
   * @return array|null
   */
  public static function getDefaultImageStyleSizes() {
    $default_style = \Drupal::config('vimeo_thumbnail_rebuilder.settings')
      ->get('default_style');
    $image_style = ImageStyle::load($default_style);
    $effects = $image_style->getEffects()->getConfiguration();
    foreach ($effects as $uuid => $effect) {
      if ($effect['id'] === 'image_scale_and_crop') {
        return $effect['data'];
      }
    }

    return [
      'width'  => '500',
      'height' => '500',
    ];
  }

  /**
   * Returns Media of type 'vimeo'
   *
   * @param  string|null  $id  The ID of the media to be rebuilt, if NULL all
   *                           Vimeo media will be returned.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function loadVimeoMedia(string $id = NULL) {
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

}
