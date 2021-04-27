<?php

namespace Drupal\savvier_members\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media_entity_embeddable_video\VideoProviderInterface;

class VimeoThumbnailRebuilderUpdateVimeoThumbnailForm extends FormBase {

  /** @var \Drupal\media_entity\Entity\Media media */
  private $media;

  public function getFormId() {
    return 'update_vimeo_thumbnail_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $media = NULL) {
    $this->media = $media;

    // Select image style

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update Thumbnail'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mediaUrl = $this->media->get('field_media_oembed_video')->value;
    $vimeo_id = $this->getVimeoIDFromUrl($mediaUrl);
    $hean = 21;

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
