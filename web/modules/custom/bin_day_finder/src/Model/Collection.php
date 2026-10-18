<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Model;

/**
 * A single upcoming bin collection.
 */
final class Collection {

  /**
   * Constructs a Collection.
   *
   * @param string $binId
   *   The bin machine name, for example "blue".
   * @param string $label
   *   The human-readable bin name.
   * @param string $description
   *   What goes in the bin.
   * @param \DateTimeImmutable $date
   *   The collection date (midnight, site timezone).
   * @param int $daysUntil
   *   Whole days from today until the collection. 0 means today.
   */
  public function __construct(
    public readonly string $binId,
    public readonly string $label,
    public readonly string $description,
    public readonly \DateTimeImmutable $date,
    public readonly int $daysUntil,
  ) {}

}
