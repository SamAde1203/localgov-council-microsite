<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin settings for the postcode lookup service.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bin_day_finder_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bin_day_finder.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['lookup_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify postcodes with the lookup service'),
      '#description' => $this->t('When off, or if the service is unavailable, postcodes are checked for format only.'),
      '#config_target' => 'bin_day_finder.settings:lookup_enabled',
    ];

    $form['api_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Lookup API base URL'),
      '#config_target' => 'bin_day_finder.settings:api_base_url',
      '#required' => TRUE,
      '#states' => [
        'visible' => [':input[name="lookup_enabled"]' => ['checked' => TRUE]],
      ],
    ];

    $form['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout'),
      '#field_suffix' => $this->t('seconds'),
      '#min' => 1,
      '#max' => 30,
      '#config_target' => 'bin_day_finder.settings:request_timeout',
    ];

    $form['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache successful lookups for'),
      '#field_suffix' => $this->t('seconds'),
      '#min' => 0,
      '#description' => $this->t('Set to 0 to turn off caching.'),
      '#config_target' => 'bin_day_finder.settings:cache_lifetime',
    ];

    $form['flood_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum searches per visitor per hour'),
      '#min' => 1,
      '#config_target' => 'bin_day_finder.settings:flood_limit',
    ];

    return parent::buildForm($form, $form_state);
  }

}
