<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Service;

use Drupal\bin_day_finder\Model\Collection;
use Drupal\bin_day_finder\Model\PostcodeResult;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Works out collection dates from the configured rounds.
 *
 * Each postcode outward code belongs to a round with a fixed weekday. Bins
 * are collected every week, or fortnightly on alternating "A" and "B" weeks
 * counted from a configured anchor date.
 */
final class CollectionScheduleProvider implements CollectionScheduleProviderInterface {

  private const WEEKDAYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
  ];

  /**
   * Constructs a CollectionScheduleProvider.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getUpcoming(PostcodeResult $postcode): array {
    $schedule = $this->configFactory->get('bin_day_finder.schedule');
    $timezone = new \DateTimeZone(date_default_timezone_get());

    $today = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->setTimezone($timezone)
      ->setTime(0, 0);
    $anchor = new \DateTimeImmutable((string) $schedule->get('anchor_date'), $timezone);

    $weekday = $this->dayForOutwardCode(
      $postcode->outwardCode(),
      (array) $schedule->get('rounds'),
      (string) $schedule->get('default_day'),
    );
    // "monday" means today if today is a Monday, otherwise the next one.
    $nextRoundDay = $today->modify($weekday);

    $collections = [];
    foreach ((array) $schedule->get('bins') as $bin) {
      $date = $nextRoundDay;
      $week = strtoupper((string) ($bin['week'] ?? 'EVERY'));
      if ($week === 'A' || $week === 'B') {
        // At most one extra week is ever needed to reach the right week.
        if ($this->weekLetter($anchor, $date) !== $week) {
          $date = $date->modify('+1 week');
        }
      }
      $collections[] = new Collection(
        binId: (string) $bin['id'],
        label: (string) $bin['label'],
        description: (string) ($bin['description'] ?? ''),
        date: $date,
        daysUntil: (int) $today->diff($date)->days,
      );
    }

    // Soonest first. usort() is stable, so bins on the same day keep their
    // configured order.
    usort($collections, static fn (Collection $a, Collection $b): int => $a->date <=> $b->date);
    return $collections;
  }

  /**
   * Finds the collection weekday for an outward code.
   */
  private function dayForOutwardCode(string $outwardCode, array $rounds, string $default): string {
    foreach ($rounds as $round) {
      $codes = array_map('strtoupper', (array) ($round['outward_codes'] ?? []));
      if (in_array(strtoupper($outwardCode), $codes, TRUE)) {
        return $this->validWeekday((string) $round['day']);
      }
    }
    return $this->validWeekday($default);
  }

  /**
   * Guards against bad configuration reaching DateTime::modify().
   */
  private function validWeekday(string $day): string {
    $day = strtolower(trim($day));
    if (!in_array($day, self::WEEKDAYS, TRUE)) {
      throw new \UnexpectedValueException(sprintf('"%s" is not a valid collection day.', $day));
    }
    return $day;
  }

  /**
   * Returns "A" or "B" depending on whole weeks elapsed since the anchor.
   */
  private function weekLetter(\DateTimeImmutable $anchor, \DateTimeImmutable $date): string {
    $days = (int) $anchor->diff($date)->format('%r%a');
    $weeks = intdiv($days - (($days % 7 + 7) % 7), 7);
    return $weeks % 2 === 0 ? 'A' : 'B';
  }

}
