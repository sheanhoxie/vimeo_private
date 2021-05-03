<?php

namespace Drupal\vimeo_thumbnail_rebuilder;

/**
 * Provides an interface providing a Vimeo Response
 *
 * @package Drupal\vimeo_thumbnail_rebuilder
 */
interface VimeoResponseInterface {

  /**
   * Returns the video id
   *
   * @return string|null
   */
  public function id();

  /**
   * Returns the name of the video
   *
   * @return string|null
   */
  public function name();

  /**
   * Returns the various sizes, and details of the active thumbnail
   *
   * @return array
   */
  public function pictures();

  /**
   * Returns the details of a single thumbnail
   *
   * @return array
   */
  public function defaultPicture($size = 3);

  /**
   * Returns the vimeo.com link to the video
   *
   * @return string|null
   */
  public function link();

  /**
   * Returns the video duration in seconds
   *
   * @return string|null
   */
  public function duration();

  /**
   * Returns the video uri relative to vimeo
   *
   * @return string|null
   */
  public function uri();

}
