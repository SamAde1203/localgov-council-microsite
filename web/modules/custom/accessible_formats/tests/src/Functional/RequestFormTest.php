<?php

declare(strict_types=1);

namespace Drupal\Tests\accessible_formats\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\accessible_formats\Entity\AccessibleFormatRequest;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the request journey a resident follows.
 */
#[Group('accessible_formats')]
#[RunTestsInSeparateProcesses]
final class RequestFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['accessible_formats'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A permanent public PDF.
   */
  private FileInterface $document;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    file_put_contents('public://annual-budget-2026.pdf', 'Test document');
    $this->document = File::create([
      'uri' => 'public://annual-budget-2026.pdf',
      'filename' => 'annual-budget-2026.pdf',
    ]);
    $this->document->setPermanent();
    $this->document->save();
  }

  /**
   * A resident can send a request and gets a reference number.
   */
  public function testResidentCanRequestFormat(): void {
    $this->drupalGet('accessible-format/request/' . $this->document->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('annual-budget-2026.pdf');

    $this->submitForm([
      'name' => 'Ada Resident',
      'email' => 'ada@example.com',
      'format' => 'large_print',
    ], 'Send request');

    $requests = AccessibleFormatRequest::loadMultiple();
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertSame('large_print', $request->get('format')->value);
    $this->assertSame('annual-budget-2026.pdf', $request->label());

    $this->assertSession()->pageTextContains('Request received');
    $this->assertSession()->pageTextContains($request->reference());
    $this->assertSession()->pageTextContains('within 10 working days');
  }

  /**
   * Choosing "Another format" needs details.
   */
  public function testOtherFormatNeedsDetails(): void {
    $this->drupalGet('accessible-format/request/' . $this->document->id());
    $this->submitForm([
      'name' => 'Ada Resident',
      'email' => 'ada@example.com',
      'format' => 'other',
    ], 'Send request');

    $this->assertSession()->pageTextContains('Tell us which format you need');
    $this->assertSession()->pageTextNotContains('Request received');
    $this->assertCount(0, AccessibleFormatRequest::loadMultiple());
  }

  /**
   * Requests are rate limited per visitor.
   */
  public function testFloodLimit(): void {
    $this->config('accessible_formats.settings')->set('flood_limit', 1)->save();
    $values = ['name' => 'Ada', 'email' => 'ada@example.com', 'format' => 'audio'];

    $this->drupalGet('accessible-format/request/' . $this->document->id());
    $this->submitForm($values, 'Send request');
    $this->drupalGet('accessible-format/request/' . $this->document->id());
    $this->submitForm($values, 'Send request');

    $this->assertSession()->pageTextContains('You have sent too many requests');
    $this->assertCount(1, AccessibleFormatRequest::loadMultiple());
  }

  /**
   * Temporary uploads cannot be requested.
   */
  public function testTemporaryFileIsNotAvailable(): void {
    $this->document->setTemporary();
    $this->document->save();
    $this->drupalGet('accessible-format/request/' . $this->document->id());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Images and other non-document files cannot be requested.
   */
  public function testImageIsNotAvailable(): void {
    file_put_contents('public://map.png', 'Test image');
    $image = File::create(['uri' => 'public://map.png', 'filename' => 'map.png']);
    $image->setPermanent();
    $image->save();
    $this->drupalGet('accessible-format/request/' . $image->id());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Only staff with the permission can see the list of requests.
   */
  public function testRequestListAccess(): void {
    $this->drupalGet('admin/content/accessible-format-requests');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer accessible format requests', 'access administration pages']));
    $this->drupalGet('admin/content/accessible-format-requests');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no requests yet.');
  }

}
