<?php

namespace Drupal\vimeo_thumbnail_rebuilder;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class VimeoThumbnailRebuilderServiceProvider extends ServiceProviderBase {

  /*
   * Override the resource_fetcher service
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('media.oembed.resource_fetcher');
    $definition->setClass('Drupal\vimeo_thumbnail_rebuilder\VimeoResourceFetcher');
  }

}
