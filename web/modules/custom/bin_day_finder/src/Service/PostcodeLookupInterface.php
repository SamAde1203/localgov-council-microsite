<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Service;

use Drupal\bin_day_finder\Model\PostcodeResult;

/**
 * Looks up a UK postcode.
 */
interface PostcodeLookupInterface {

  /**
   * Validates a postcode and returns what is known about it.
   *
   * If the remote service is unavailable the postcode is still accepted on
   * format alone, and the result is marked as unverified, so residents can
   * always get an answer.
   *
   * @param string $input
   *   The postcode as typed by the user.
   *
   * @return \Drupal\bin_day_finder\Model\PostcodeResult
   *   The lookup result.
   *
   * @throws \Drupal\bin_day_finder\Exception\InvalidPostcodeException
   *   If the postcode is badly formatted or the service says it does not exist.
   */
  public function lookup(string $input): PostcodeResult;

}
