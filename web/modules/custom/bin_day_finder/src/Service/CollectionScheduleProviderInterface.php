<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Service;

use Drupal\bin_day_finder\Model\PostcodeResult;

/**
 * Calculates upcoming bin collections for a postcode.
 */
interface CollectionScheduleProviderInterface {

  /**
   * Returns the next collection of each bin, soonest first.
   *
   * @param \Drupal\bin_day_finder\Model\PostcodeResult $postcode
   *   The looked-up postcode.
   *
   * @return \Drupal\bin_day_finder\Model\Collection[]
   *   One upcoming collection per bin, ordered by date then bin order.
   */
  public function getUpcoming(PostcodeResult $postcode): array;

}
