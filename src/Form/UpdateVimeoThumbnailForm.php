<?php

namespace Drupal\vimeo_thumbnail_rebuilder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vimeo_thumbnail_rebuilder\VimeoThumbnailRebuilder;

class UpdateVimeoThumbnailForm extends FormBase {

  /** @var \Drupal\media_entity\Entity\Media media */
  private $media;

  public function getFormId() {
    return 'update_vimeo_thumbnail_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $media = NULL) {
    $this->media = $media;

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update Thumbnail'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $default_image_style = VimeoThumbnailRebuilder::getDefaultImageStyle();
    $success = VimeoThumbnailRebuilder::rebuildThumbnail($this->media, $default_image_style);
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
  private function getVimeoIDFromUrl($url) {
    $url_array = explode('/', $url);
    $video_id = array_pop($url_array);

    return $video_id;
  }
}
