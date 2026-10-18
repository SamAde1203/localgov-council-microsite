<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Service;

use Drupal\bin_day_finder\Exception\InvalidPostcodeException;
use Drupal\bin_day_finder\Model\Postcode;
use Drupal\bin_day_finder\Model\PostcodeResult;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Looks up postcodes using the postcodes.io REST API.
 *
 * Successful lookups are cached so repeat searches do not hit the API.
 * Failures of the remote service degrade gracefully to a format-only check.
 */
final class PostcodeLookup implements PostcodeLookupInterface {

  /**
   * Constructs a PostcodeLookup service.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The module's logger channel.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function lookup(string $input): PostcodeResult {
    $postcode = Postcode::normalise($input);
    if ($postcode === NULL) {
      throw new InvalidPostcodeException('The postcode format is not valid.');
    }

    $settings = $this->configFactory->get('bin_day_finder.settings');
    if (!$settings->get('lookup_enabled')) {
      return new PostcodeResult($postcode, verified: FALSE);
    }

    $cid = 'bin_day_finder:postcode:' . str_replace(' ', '', $postcode);
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $url = rtrim((string) $settings->get('api_base_url'), '/') . '/postcodes/' . rawurlencode($postcode);

    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => (int) $settings->get('request_timeout') ?: 5,
        'headers' => ['Accept' => 'application/json'],
      ]);
    }
    catch (ClientException $e) {
      if ($e->getResponse()->getStatusCode() === 404) {
        throw new InvalidPostcodeException('The postcode does not exist.', 0, $e);
      }
      return $this->degraded($postcode, $e);
    }
    catch (GuzzleException $e) {
      return $this->degraded($postcode, $e);
    }

    $body = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($body) || !isset($body['result']) || !is_array($body['result'])) {
      return $this->degraded($postcode, new \UnexpectedValueException('Unexpected response body.'));
    }

    $data = $body['result'];
    $result = new PostcodeResult(
      postcode: $data['postcode'] ?? $postcode,
      district: $data['admin_district'] ?? NULL,
      ward: $data['admin_ward'] ?? NULL,
    );

    $lifetime = (int) $settings->get('cache_lifetime');
    if ($lifetime > 0) {
      $this->cache->set(
        $cid,
        $result,
        $this->time->getRequestTime() + $lifetime,
        ['config:bin_day_finder.settings'],
      );
    }

    return $result;
  }

  /**
   * Logs a lookup failure and returns an unverified result.
   */
  private function degraded(string $postcode, \Throwable $e): PostcodeResult {
    $this->logger->warning('Postcode lookup failed for @postcode, falling back to a format check: @message', [
      '@postcode' => $postcode,
      '@message' => $e->getMessage(),
    ]);
    return new PostcodeResult($postcode, verified: FALSE);
  }

}
