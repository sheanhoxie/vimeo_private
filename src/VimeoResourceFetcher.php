<?php

namespace Drupal\vimeo_thumbnail_rebuilder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcher;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

class VimeoResourceFetcher extends ResourceFetcher {

  /**
   * @var \Drupal\vimeo_thumbnail_rebuilder\VimeoThumbnailRebuilder
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

    $cached = $this->cacheGet($cache_id);
    if ($cached) {
      return $this->createResource($cached->data, $url);
    }

    try {
      $response = $this->httpClient->get($url);
    } catch (RequestException $e) {
      throw new ResourceException('Could not retrieve the oEmbed resource.', $url, [], $e);
    }

    list($format) = $response->getHeader('Content-Type');
    $content = (string) $response->getBody();

    if (strstr($format, 'text/xml') || strstr($format, 'application/xml')) {
      $data = $this->parseResourceXml($content, $url);
    } elseif (strstr($format, 'text/javascript') || strstr($format, 'application/json')) {
      $data = Json::decode($content);
    } // If the response is neither XML nor JSON, we are in bat country.
    else {
      throw new ResourceException('The fetched resource did not have a valid Content-Type header.', $url);
    }

    // Set the vimeo thumbnail url + width + height
    if (strpos($url, 'vimeo.com')) {
      $vimeo = VimeoThumbnailRebuilder::requestVimeo($data['video_id']);
      $vimeo_data = VimeoThumbnailRebuilder::parseVimeoVideoRequest($vimeo['body']);

      $data += [
        'thumbnail_url'    => $vimeo_data['images'][3]['link'],
        'thumbnail_width'  => $vimeo_data['thumbnail_width'],
        'thumbnail_height' => $vimeo_data['thumbnail_height'],
      ];
    }

    $this->cacheSet($cache_id, $data);

    return $this->createResource($data, $url);
  }

}
