<?php

namespace Drupal\vimeo_private;

/**
 * Class VimeoResponse
 *
 * @package Drupal\vimeo_private
 */
class VimeoPrivateResponse implements VimeoPrivateResponseInterface {

  /**
   * Stores all data returned from Vimeo
   *
   * @var array
   */
  private $response;

  /**
   * VimeoResponse constructor.
   *
   * @param  array  $response
   */
  public function __construct(array $response) {
    $this->response = $response;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    $uri = $this->uri();
    $array = explode('/', $uri);

    return array_pop($array);
  }

  /**
   * {@inheritdoc}
   */
  public function uri() {
    return $this->response['uri'];
  }

  /**
   * {@inheritdoc}
   */
  public function name() {
    return $this->response['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultPicture($size = 3) {
    $pictures = $this->pictures();

    return $pictures['sizes'][3];
  }

  /**
   * {@inheritdoc}
   */
  public function pictures() {
    return $this->response['pictures'];
  }

  /**
   * {@inheritdoc}
   */
  public function link() {
    return $this->response['link'];
  }

  /**
   * {@inheritdoc}
   */
  public function duration() {
    return $this->response['duration'];
  }

}
