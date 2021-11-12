<?php

namespace Drupal\vimeo_private;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcher;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Overrides the default Media oEmbed resource fetcher
 *
 * @package Drupal\vimeo_private
 */
class VimeoPrivateResourceFetcher extends ResourceFetcher {

  /**
   * @var \Drupal\vimeo_private\VimeoPrivate
   */
  private $vimeoThumbnailRebuilder;

  /**
   * VimeoResourceFetcher constructor.
   *
   * @param  \GuzzleHttp\ClientInterface                       $http_client
   * @param  \Drupal\media\OEmbed\ProviderRepositoryInterface  $providers
   * @param  \Drupal\Core\Cache\CacheBackendInterface|NULL     $cache_backend
   */
  public function __construct(ClientInterface $http_client, ProviderRepositoryInterface $providers, CacheBackendInterface $cache_backend = NULL) {
    parent::__construct($http_client, $providers, $cache_backend);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchResource($url) {
    $cache_id = "media:oembed_resource:$url";

    if ($cached = $this->cacheGet($cache_id)) {
      return $this->createResource($cached->data, $url);
    }

    try {
      $response = $this->httpClient->get($url);
    } catch (RequestException $e) {
      throw new ResourceException('Could not retrieve the oEmbed resource.', $url, [], $e);
    }

    // Get response format type, and parse accordingly
    [$format] = $response->getHeader('Content-Type');
    $content = (string) $response->getBody();

    if (strstr($format, 'xml')) {
      $data = $this->parseResourceXml($content, $url);
    }
    elseif (strstr($format, 'javascript') || strstr($format, 'json')) {
      $data = Json::decode($content);
    }
    else {
      throw new ResourceException('The fetched resource did not have a valid Content-Type header.', $url);
    }

    /**
     * Fetch the hidden/locked Vimeo video thumbnail details and attach to the
     * data resource
     */
    if (strpos($url, 'vimeo.com')) {
      // Get the latest details from vimeo
      $vimeo = VimeoPrivate::vimeoRequest($data['video_id']);

      // Get the thumbnail width/height
      $vimeo_settings = \Drupal::config('vimeo_private.settings');

      $data += [
        'thumbnail_url'    => VimeoPrivate::getImageUrlFromResponse($vimeo),
        'thumbnail_width'  => $vimeo_settings->get('thumbnail_width'),
        'thumbnail_height' => $vimeo_settings->get('thumbnail_height'),
      ];
    }

    $this->cacheSet($cache_id, $data);
    return $this->createResource($data, $url);
  }

}
