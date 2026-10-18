<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Exception;

/**
 * Thrown when a postcode is badly formatted or does not exist.
 */
class InvalidPostcodeException extends \InvalidArgumentException {}
