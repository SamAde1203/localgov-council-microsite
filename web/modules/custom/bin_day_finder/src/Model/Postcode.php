<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Model;

/**
 * Normalises and validates UK postcodes.
 *
 * Validation here is a format check only. Whether the postcode actually
 * exists is confirmed by the postcode lookup service.
 */
final class Postcode {

  /**
   * UK postcode pattern, applied to upper-case input with spaces removed.
   *
   * Outward code: one or two letters, a digit, then an optional letter or
   * digit. Inward code: a digit followed by two letters.
   */
  private const PATTERN = '/^([A-Z]{1,2}[0-9][A-Z0-9]?)([0-9][A-Z]{2})$/';

  /**
   * Converts user input to the standard "S1 2HH" format.
   *
   * @param string $input
   *   The postcode as typed by the user.
   *
   * @return string|null
   *   The normalised postcode, or NULL if the input is not a valid format.
   */
  public static function normalise(string $input): ?string {
    $compact = strtoupper((string) preg_replace('/\s+/', '', $input));
    if (!preg_match(self::PATTERN, $compact, $matches)) {
      return NULL;
    }
    return $matches[1] . ' ' . $matches[2];
  }

  /**
   * Returns the outward code ("S1" from "S1 2HH").
   *
   * @param string $normalised
   *   A postcode already passed through ::normalise().
   *
   * @return string
   *   The outward code.
   */
  public static function outwardCode(string $normalised): string {
    return strtok($normalised, ' ') ?: $normalised;
  }

}
