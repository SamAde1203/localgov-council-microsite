<?php

declare(strict_types=1);

namespace Drupal\Tests\bin_day_finder\Unit;

use Drupal\bin_day_finder\Model\Collection;
use Drupal\bin_day_finder\Model\PostcodeResult;
use Drupal\bin_day_finder\Service\CollectionScheduleProvider;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the collection date calculations.
 */
#[CoversClass(CollectionScheduleProvider::class)]
#[Group('bin_day_finder')]
final class CollectionScheduleProviderTest extends UnitTestCase {

  /**
   * The timezone in force before the test.
   */
  private string $originalTimezone;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->originalTimezone = date_default_timezone_get();
    date_default_timezone_set('Europe/London');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    date_default_timezone_set($this->originalTimezone);
    parent::tearDown();
  }

  /**
   * Builds a provider with a fixed "now" and test schedule.
   */
  private function provider(string $now): CollectionScheduleProvider {
    $config = $this->getConfigFactoryStub([
      'bin_day_finder.schedule' => [
        // Monday 5 January 2026 starts an "A" week.
        'anchor_date' => '2026-01-05',
        'default_day' => 'wednesday',
        'rounds' => [
          ['day' => 'monday', 'outward_codes' => ['S1', 'S2']],
          ['day' => 'friday', 'outward_codes' => ['S10']],
        ],
        'bins' => [
          ['id' => 'black', 'label' => 'Black bin', 'description' => 'General', 'week' => 'A'],
          ['id' => 'blue', 'label' => 'Blue bin', 'description' => 'Recycling', 'week' => 'B'],
          ['id' => 'food', 'label' => 'Food caddy', 'description' => 'Food', 'week' => 'every'],
        ],
      ],
    ]);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')
      ->willReturn((new \DateTimeImmutable($now, new \DateTimeZone('Europe/London')))->getTimestamp());

    return new CollectionScheduleProvider($config, $time);
  }

  /**
   * Flattens collections into "id:Y-m-d" strings for easy assertions.
   */
  private function summarise(array $collections): array {
    return array_map(
      static fn (Collection $c): string => $c->binId . ':' . $c->date->format('Y-m-d'),
      $collections,
    );
  }

  /**
   * Tests alternating fortnightly bins and a weekly bin.
   */
  public function testAlternatingWeeks(): void {
    // Wednesday 14 October 2026. The next Monday is 19 October, which is
    // 41 weeks after the anchor, so a "B" week.
    $collections = $this->provider('2026-10-14 10:00')
      ->getUpcoming(new PostcodeResult('S1 2HH'));

    $this->assertSame([
      'blue:2026-10-19',
      'food:2026-10-19',
      'black:2026-10-26',
    ], $this->summarise($collections));
    $this->assertSame(5, $collections[0]->daysUntil);
  }

  /**
   * Tests that a collection due today is shown as today.
   */
  public function testCollectionToday(): void {
    // Monday 26 October 2026 is an "A" week.
    $collections = $this->provider('2026-10-26 06:30')
      ->getUpcoming(new PostcodeResult('S2 4SU'));

    $this->assertSame([
      'black:2026-10-26',
      'food:2026-10-26',
      'blue:2026-11-02',
    ], $this->summarise($collections));
    $this->assertSame(0, $collections[0]->daysUntil);
  }

  /**
   * Tests that unknown areas fall back to the default day.
   */
  public function testUnknownAreaUsesDefaultDay(): void {
    $collections = $this->provider('2026-10-14 10:00')
      ->getUpcoming(new PostcodeResult('LS1 4AP'));

    foreach ($collections as $collection) {
      $this->assertSame('Wednesday', $collection->date->format('l'));
    }
  }

  /**
   * Tests that dates are correct across the switch to GMT.
   */
  public function testClocksChange(): void {
    // "Now" is Saturday 24 October 2026 (BST). British Summer Time ends on
    // Sunday 25 October, so the next Friday round, 30 October, is in GMT.
    // 30 October is 42 weeks after the anchor: an "A" week.
    $collections = $this->provider('2026-10-24 12:00')
      ->getUpcoming(new PostcodeResult('S10 2TN'));

    $this->assertSame([
      'black:2026-10-30',
      'food:2026-10-30',
      'blue:2026-11-06',
    ], $this->summarise($collections));
    $this->assertSame(6, $collections[0]->daysUntil);
    foreach ($collections as $collection) {
      $this->assertSame('00:00', $collection->date->format('H:i'));
    }
  }

}
