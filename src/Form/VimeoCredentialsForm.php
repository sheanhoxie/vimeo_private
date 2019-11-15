<?php

namespace Drupal\vimeo_thumbnail_rebuilder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class VimeoCredentialsForm.
 */
class VimeoCredentialsForm extends FormBase {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * VimeoCredentialsForm constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\vimeo_thumbnail_rebuilder\Form\VimeoCredentialsForm
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vimeo_credentials_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $this->state->get('vimeo.thumbnail_rebuilder.vimeo_credentials.client_id'),
    ];
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $this->state->get('vimeo.thumbnail_rebuilder.vimeo_credentials.client_secret'),
    ];
    $form['api_token'] = [
      '#type' => 'password',
      '#title' => $this->t('API token'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => '',
      '#description' => $this->state->get('vimeo.thumbnail_rebuilder.vimeo_credentials.api_token') ? t("API Token Set") : t("API Token Not Set"),
    ];
    return parent::buildForm($form, $form_state);
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
    $this->state->set('vimeo.thumbnail_rebuilder.vimeo_credentials.client_id', $form_state->getValue('client_id'));
    $this->state->set('vimeo.thumbnail_rebuilder.vimeo_credentials.client_secret', $form_state->getValue('client_secret'));
    $this->state->set('vimeo.thumbnail_rebuilder.vimeo_credentials.api_token', $form_state->getValue('api_token'));
  }

}
