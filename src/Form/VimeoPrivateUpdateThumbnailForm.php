<?php

namespace Drupal\vimeo_private\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vimeo_private\VimeoPrivate;

class VimeoPrivateUpdateThumbnailForm extends FormBase {

  /** @var \Drupal\media\Entity\Media */
  private $media;

  public function getFormId() {
    return 'vimeo_private_update_thumbnail_form';
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
    $default_image_style = VimeoPrivate::getDefaultImageStyle();
    VimeoPrivate::rebuildThumbnail($this->media, $default_image_style);
  }

}
