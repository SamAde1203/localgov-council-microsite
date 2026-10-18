<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Model;

/**
 * The outcome of looking up a postcode.
 */
final class PostcodeResult {

  /**
   * Constructs a PostcodeResult.
   *
   * @param string $postcode
   *   The normalised postcode, for example "S1 2HH".
   * @param string|null $district
   *   The local authority district, if known.
   * @param string|null $ward
   *   The electoral ward, if known.
   * @param bool $verified
   *   TRUE if the lookup service confirmed the postcode exists. FALSE if the
   *   service was unavailable or disabled and only the format was checked.
   */
  public function __construct(
    public readonly string $postcode,
    public readonly ?string $district = NULL,
    public readonly ?string $ward = NULL,
    public readonly bool $verified = TRUE,
  ) {}

  /**
   * Returns the outward code, used to match a collection round.
   */
  public function outwardCode(): string {
    return Postcode::outwardCode($this->postcode);
  }

}
