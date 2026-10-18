<?php

declare(strict_types=1);

namespace Drupal\Tests\bin_day_finder\Unit;

use Drupal\bin_day_finder\Model\Postcode;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests postcode normalisation.
 */
#[CoversClass(Postcode::class)]
#[Group('bin_day_finder')]
final class PostcodeTest extends UnitTestCase {

  /**
   * Valid input in different shapes, and the expected normalised form.
   */
  public static function validPostcodes(): array {
    return [
      'already normal' => ['S1 2HH', 'S1 2HH'],
      'lower case, no space' => ['s12hh', 'S1 2HH'],
      'extra spaces' => ['  S10   2TN ', 'S10 2TN'],
      'two-letter area' => ['sw1a1aa', 'SW1A 1AA'],
      'letter in district' => ['EC1A 1BB', 'EC1A 1BB'],
    ];
  }

  /**
   * Tests that valid postcodes are normalised consistently.
   */
  #[DataProvider('validPostcodes')]
  public function testNormalisesValidPostcodes(string $input, string $expected): void {
    $this->assertSame($expected, Postcode::normalise($input));
  }

  /**
   * Input that must be rejected.
   */
  public static function invalidPostcodes(): array {
    return [
      'empty' => [''],
      'outward code only' => ['S1'],
      'words' => ['Sheffield'],
      'missing inward letters' => ['S1 2'],
      'digit first' => ['1S 2HH'],
      'script injection' => ['<script>'],
    ];
  }

  /**
   * Tests that invalid input returns NULL.
   */
  #[DataProvider('invalidPostcodes')]
  public function testRejectsInvalidPostcodes(string $input): void {
    $this->assertNull(Postcode::normalise($input));
  }

  /**
   * Tests extracting the outward code.
   */
  public function testOutwardCode(): void {
    $this->assertSame('S10', Postcode::outwardCode('S10 2TN'));
    $this->assertSame('SW1A', Postcode::outwardCode('SW1A 1AA'));
  }

}
