<?php

declare(strict_types=1);

namespace Drupal\accessible_formats\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for the accessible format notice and requests.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'accessible_formats_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['accessible_formats.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File types that show the notice'),
      '#description' => $this->t('File extensions separated by spaces, for example: pdf docx xlsx'),
      '#config_target' => 'accessible_formats.settings:extensions',
      '#required' => TRUE,
    ];
    $form['response_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Working days to respond'),
      '#description' => $this->t('Shown to residents when they send a request.'),
      '#min' => 1,
      '#max' => 30,
      '#config_target' => 'accessible_formats.settings:response_days',
      '#required' => TRUE,
    ];
    $form['notify_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email new requests to'),
      '#description' => $this->t('Leave blank to only list requests under Content.'),
      '#config_target' => 'accessible_formats.settings:notify_email',
    ];
    $form['flood_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum requests per visitor per hour'),
      '#min' => 1,
      '#config_target' => 'accessible_formats.settings:flood_limit',
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

}
