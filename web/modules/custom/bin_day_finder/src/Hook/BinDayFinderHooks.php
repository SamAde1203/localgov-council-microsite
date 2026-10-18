<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the Bin Day Finder module.
 *
 * Uses Drupal 11's attribute-based (object-oriented) hooks.
 */
final class BinDayFinderHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'bin_day_finder_results' => [
        'variables' => [
          'postcode' => NULL,
          'district' => NULL,
          'verified' => TRUE,
          'collections' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.bin_day_finder') {
      return NULL;
    }
    return '<p>' . $this->t('Bin Day Finder lets residents enter a postcode at <a href=":url">/bin-day</a> and see their next black, blue and brown bin collections. Postcodes are verified with postcodes.io, and the page still works if that service is unavailable.', [
      ':url' => '/bin-day',
    ]) . '</p>';
  }

}
