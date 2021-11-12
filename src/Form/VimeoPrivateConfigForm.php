<?php

namespace Drupal\vimeo_private\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Vimeo Private settings
 *
 * @package Drupal\vimeo_private\Form
 */
class VimeoPrivateConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vimeo_private_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['vimeo_private_settings'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Vimeo Private settings'),
    ];

    // Thumbnail Width
    $form['thumbnail_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Thumbnail width'),
      '#default_value' => $form_state->getValue('thumbnail_width') ?? '1280',
    ];

    // Thumbnail Height
    $form['thumbnail_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Thumbnail height'),
      '#default_value' => $form_state->getValue('thumbnail_height') ?? '720',
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Set thumbnail sizes.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('vimeo_private.settings')
      ->set('thumbnail_width', $form_state->getValue('thumbnail_width'))
      ->set('thumbnail_height', $form_state->getValue('thumbnail_height'))
      ->save();
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'vimeo_private.settings',
    ];
  }


}
