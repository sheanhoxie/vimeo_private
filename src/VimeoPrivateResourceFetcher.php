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

    // The oembed resource doesnt fetch the thumbnail url, or it's details when
    // it's locked/hidden, so we must
    if (strpos($url, 'vimeo.com')) {
      $vimeo = VimeoPrivate::vimeoRequest($data['video_id']);
      $thumbnail_url = VimeoPrivate::getImageUrlFromResponse($vimeo);
      $image_style_size = VimeoPrivate::getDefaultImageStyleSizes();

      $data += [
        'thumbnail_url'    => $thumbnail_url,
        'thumbnail_width'  => $image_style_size['width'],
        'thumbnail_height' => $image_style_size['height'],
      ];
    }

    $this->cacheSet($cache_id, $data);

    return $this->createResource($data, $url);
  }

}
