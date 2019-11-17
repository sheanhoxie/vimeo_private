<?php

namespace Drupal\vimeo_thumbnail_rebuilder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\State;

/**
 * Class RebuildVimeoThumbnailsForm.
 */
class RebuildVimeoThumbnailsForm extends FormBase {

  /**
   * @var \Drupal\Core\State\State
   */
  private $state;

  /**
   * @var bool
   */
  private $credentials_set = FALSE;

  /**
   * RebuildVimeoThumbnailsForm constructor.
   *
   * @param \Drupal\Core\State\State $state
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(State $state, MessengerInterface $messenger) {
    $this->state = $state;
    $this->messenger = $messenger;

    $client_id = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_id');
    $client_secret = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.client_secret');
    $api_token = $this->state->get('vimeo_thumbnail_rebuilder.vimeo_credentials.api_token');

    if (isset($client_id) && isset($client_secret) && isset($api_token)) {
      $this->credentials_set = TRUE;
    } else {
      $message = t('You need to set your Vimeo credentials');
      $this->messenger->addWarning($message);
    }
  }

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
    return 'rebuild_vimeo_thumbnails_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update all Vimeo thumbnails'),
      '#disabled' => !$this->credentials_set ?: FALSE,
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
