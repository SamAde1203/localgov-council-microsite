<?php

declare(strict_types=1);

namespace Drupal\demo_city_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * The home page.
 *
 * The welcome message and the list of services are blocks that LocalGov
 * places on the front page, so this page itself only needs a short intro.
 */
final class HomeController extends ControllerBase {

  /**
   * Builds the home page content.
   */
  public function build(): array {
    return [
      '#markup' => '',
      '#cache' => ['max-age' => -1],
    ];
  }

}
