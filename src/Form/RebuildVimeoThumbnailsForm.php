<?php

namespace Drupal\vimeo_thumbnail_rebuilder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RebuildVimeoThumbnailsForm.
 */
class RebuildVimeoThumbnailsForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rebuild_vimeo_thumbnails_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update all Vimeo thumbnails'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\vimeo_thumbnail_rebuilder\VimeoThumbnailRebuilder $vimeo */
    $vimeo = \Drupal::service('vimeo_thumbnail_rebuilder.thumbnail_rebuilder');
    $vimeo->rebuildMissingVimeoThumbnails();
  }

}
