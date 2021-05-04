<?php

namespace Drupal\vimeo_private\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\vimeo_private\VimeoPrivate;

class VimeoPrivateUpdateThumbnailForm extends FormBase {

  /** @var \Drupal\media\Entity\Media */
  private $media;

  public function getFormId() {
    return 'vimeo_private_update_thumbnail_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $media = NULL) {
    $this->media = $media;

    // Set form to enabled initially, and then disable on missing settings
    $disabled = FALSE;

    // Check credentials are set
    if (VimeoPrivate::credentials() === FALSE) {
      $disabled = TRUE;
      $url = Url::fromRoute('vimeo_private.credentials_form');
      $credentials_form = Link::fromTextAndUrl(t('Set Vimeo credentials here'), $url)
        ->toString();
      $this->messenger()
        ->addWarning($this->t('No Vimeo API credentials set. %vimeo_credentials.', [
          '%vimeo_credentials' => $credentials_form,
        ]));
    }

    // Check default image style is set
    $default_image_style = VimeoPrivate::getDefaultImageStyle();
    if (!isset($default_image_style)) {
      $disabled = TRUE;
      $url = Url::fromRoute('vimeo_private.settings');
      $vimeo_settings = Link::fromTextAndUrl(t('Set Vimeo Private\'s default image style here'), $url)
        ->toString();

      $this->messenger()
        ->addWarning($this->t('No default image style set. %vimeo_settings.', [
          '%vimeo_settings' => $vimeo_settings,
        ]));
    }

    $form['default_image_style'] = [
      '#type'  => 'hidden',
      '#value' => $default_image_style,
    ];

    $form['submit'] = [
      '#type'     => 'submit',
      '#value'    => t('Update Thumbnail'),
      '#disabled' => $disabled,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $default_image_style = $form_state->getValue('default_image_style');
    VimeoPrivate::rebuildThumbnail($this->media, $default_image_style);
  }

}
