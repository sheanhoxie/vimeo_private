<?php

namespace Drupal\vimeo_thumbnail_rebuilder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
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
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(StateInterface $state, MessengerInterface $messenger) {
    $this->state = $state;
    $this->messenger = $messenger;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Form\FormBase|\Drupal\vimeo_thumbnail_rebuilder\Form\VimeoCredentialsForm
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('messenger')
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
      '#default_value' => $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_id'),
    ];
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_secret'),
    ];
    $form['api_token'] = [
      '#type' => 'password',
      '#title' => $this->t('API token'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => '',
      '#description' => $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.api_token') ? t("API Token Set &#x2705;") : t("API Token Not Set &#x2757;"),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('client_id')) {
      return $form_state->setErrorByName('client_id', $this->t("Client ID required"));
    }
    if (!$client_secret = $form_state->getValue('client_secret')) {
      return $form_state->setErrorByName('client_secret', $this->t("Client Secret required"));
    }
    if (!$api_token = $form_state->getValue('api_token')) {
      return $form_state->setErrorByName('api_token', $this->t("API Token required"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->set('vimeo_thumbnail_rebuilder.vimeo_credentials.client_id', $form_state->getValue('client_id'));
    $this->state->set('vimeo_thumbnail_rebuilder.vimeo_credentials.client_secret', $form_state->getValue('client_secret'));
    $this->state->set('vimeo_thumbnail_rebuilder.vimeo_credentials.api_token', $form_state->getValue('api_token'));

    $this->messenger->addMessage($this->t('Vimeo credentials set.'));
  }

}
