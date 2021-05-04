<?php

namespace Drupal\vimeo_private;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class VimeoPrivateServiceProvider extends ServiceProviderBase {

  /*
   * Override the resource_fetcher service
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('media.oembed.resource_fetcher')) {
      $definition = $container->getDefinition('media.oembed.resource_fetcher');
      $definition->setClass('Drupal\vimeo_private\VimeoPrivateResourceFetcher');
    }
  }

}
