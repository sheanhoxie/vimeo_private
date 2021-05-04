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

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'vimeo_private_settings_form';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('vimeo_private.settings');
    $default_style = $config->get('default_style');

    $image_styles = \Drupal::entityTypeManager()->getStorage('image_style')->getQuery()->execute();

    $form['vimeo_private_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Vimeo Private settings'),
    ];

    $form['vimeo_private_settings']['default_style'] = [
      '#type' => 'select',
      '#title' => t('Choose image style:'),
      '#options' => $image_styles,
      '#default_value' => $default_style ? $default_style : '',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Set default vimeo thumbnail'),
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
      ->save();
  }


}
