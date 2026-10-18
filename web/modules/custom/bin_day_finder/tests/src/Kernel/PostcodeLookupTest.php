<?php

declare(strict_types=1);

namespace Drupal\Tests\bin_day_finder\Kernel;

use Drupal\bin_day_finder\Exception\InvalidPostcodeException;
use Drupal\bin_day_finder\Service\PostcodeLookup;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the postcode lookup service against a mocked postcodes.io.
 */
#[CoversClass(PostcodeLookup::class)]
#[Group('bin_day_finder')]
#[RunTestsInSeparateProcesses]
final class PostcodeLookupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['bin_day_finder'];

  /**
   * Requests made to the mocked HTTP client.
   */
  private array $history = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['bin_day_finder']);
  }

  /**
   * Swaps the HTTP client for one that returns the given responses in turn.
   */
  private function mockResponses(array $responses): PostcodeLookup {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($this->history));
    $this->container->set('http_client', new Client(['handler' => $stack]));
    // Rebuild the service so it receives the mocked client.
    $this->container->set('bin_day_finder.postcode_lookup', NULL);
    return $this->container->get('bin_day_finder.postcode_lookup');
  }

  /**
   * Tests a successful lookup and that it is cached.
   */
  public function testSuccessfulLookupIsCached(): void {
    $lookup = $this->mockResponses([
      new Response(200, [], json_encode([
        'status' => 200,
        'result' => [
          'postcode' => 'S1 2HH',
          'admin_district' => 'Sheffield',
          'admin_ward' => 'City',
        ],
      ])),
    ]);

    $result = $lookup->lookup('s12hh');
    $this->assertSame('S1 2HH', $result->postcode);
    $this->assertSame('Sheffield', $result->district);
    $this->assertSame('City', $result->ward);
    $this->assertTrue($result->verified);
    $this->assertSame('https://api.postcodes.io/postcodes/S1%202HH', (string) $this->history[0]['request']->getUri());

    // The mock has no more responses, so a second request would throw.
    $again = $lookup->lookup('S1 2HH');
    $this->assertSame('Sheffield', $again->district);
    $this->assertCount(1, $this->history);
  }

  /**
   * Tests that a 404 from the API means the postcode does not exist.
   */
  public function testUnknownPostcodeIsRejected(): void {
    $lookup = $this->mockResponses([
      new Response(404, [], json_encode(['status' => 404, 'error' => 'Postcode not found'])),
    ]);

    $this->expectException(InvalidPostcodeException::class);
    $lookup->lookup('ZZ9 9ZZ');
  }

  /**
   * Tests that a badly formatted postcode never reaches the API.
   */
  public function testBadFormatSkipsApi(): void {
    $lookup = $this->mockResponses([]);

    try {
      $lookup->lookup('not a postcode');
      $this->fail('Expected an InvalidPostcodeException.');
    }
    catch (InvalidPostcodeException) {
      $this->assertCount(0, $this->history);
    }
  }

  /**
   * Tests graceful degradation when the API is down.
   */
  public function testServiceOutageFallsBackToFormatCheck(): void {
    $lookup = $this->mockResponses([
      new ConnectException('Connection timed out', new Request('GET', 'https://api.postcodes.io')),
    ]);

    $result = $lookup->lookup('S10 2TN');
    $this->assertSame('S10 2TN', $result->postcode);
    $this->assertFalse($result->verified);
    $this->assertNull($result->district);
  }

  /**
   * Tests that a server error also degrades gracefully.
   */
  public function testServerErrorFallsBackToFormatCheck(): void {
    $lookup = $this->mockResponses([new Response(503)]);

    $this->assertFalse($lookup->lookup('S10 2TN')->verified);
  }

  /**
   * Tests that the lookup can be switched off in settings.
   */
  public function testLookupCanBeDisabled(): void {
    $this->config('bin_day_finder.settings')->set('lookup_enabled', FALSE)->save();
    $lookup = $this->mockResponses([]);

    $result = $lookup->lookup('S1 2HH');
    $this->assertFalse($result->verified);
    $this->assertCount(0, $this->history);
  }

}
