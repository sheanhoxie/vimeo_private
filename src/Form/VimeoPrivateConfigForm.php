<?php

namespace Drupal\vimeo_private\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vimeo_private\VimeoPrivate;

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

    // Create default image style option
    $default_style = VimeoPrivate::getDefaultImageStyle();
    $image_styles = \Drupal::entityTypeManager()
      ->getStorage('image_style')
      ->getQuery()
      ->execute();
    $form['vimeo_private_settings']['default_style'] = [
      '#type'          => 'radios',
      '#title'         => t('Choose image style:'),
      '#options'       => $image_styles,
      '#empty_option'  => $this->t('Select a default image style'),
      '#default_value' => $default_style ? $default_style : '',
      '#required'      => TRUE,
    ];

    // File type
    $form['vimeo_private_settings']['file_type'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('File type'),
      '#description'   => $this->t('Enter the file type to download from Vimeo.'),
      '#default_value' => VimeoPrivate::getFileType(),
      '#required'      => TRUE,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Save settings'),
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
    parent::submitForm($form, $form_state);

    $this->config('vimeo_private.settings')
      ->set('default_style', $form_state->getValue('default_style'))
      ->set('file_type', $form_state->getValue('file_type'))
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
