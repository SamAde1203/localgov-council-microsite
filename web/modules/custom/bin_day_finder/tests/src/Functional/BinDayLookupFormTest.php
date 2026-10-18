<?php

declare(strict_types=1);

namespace Drupal\Tests\bin_day_finder\Functional;

use Drupal\bin_day_finder\Form\BinDayLookupForm;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the bin day lookup page as a resident would use it.
 */
#[CoversClass(BinDayLookupForm::class)]
#[Group('bin_day_finder')]
#[RunTestsInSeparateProcesses]
final class BinDayLookupFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['bin_day_finder', 'block', 'inline_form_errors'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Tests must not depend on a live third-party API.
    $this->config('bin_day_finder.settings')->set('lookup_enabled', FALSE)->save();
    // As on a real site, residents who are not logged in can view content.
    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['access content']);
  }

  /**
   * Tests the page, a valid search and an invalid search.
   */
  public function testLookup(): void {
    $this->drupalGet('/bin-day');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Find your bin collection day');

    // The postcode field has a visible label and the right autocomplete hint.
    $field = $this->assertSession()->fieldExists('Postcode');
    $this->assertSame('postal-code', $field->getAttribute('autocomplete'));

    // A valid postcode, typed casually.
    $this->submitForm(['postcode' => ' s1 2hh '], 'Find collection days');
    $this->assertSession()->pageTextContains('Collections for S1 2HH');
    $this->assertSession()->pageTextContains('Black bin');
    $this->assertSession()->pageTextContains('Blue bin');
    $this->assertSession()->pageTextContains('Brown bin');
    $this->assertSession()->elementsCount('css', '.bin-card time[datetime]', 3);
    // The lookup service is off, so residents are told the result is based
    // on the postcode area only.
    $this->assertSession()->pageTextContains('We could not confirm this postcode right now');

    // An invalid postcode shows a clear inline error and no results.
    $this->submitForm(['postcode' => 'Sheffield'], 'Find collection days');
    $this->assertSession()->pageTextContains('Enter a real UK postcode, like S1 2HH');
    $this->assertSession()->elementNotExists('css', '.bin-day-results');
    $this->assertSession()->elementAttributeContains('css', 'input[name="postcode"]', 'aria-invalid', 'true');
  }

  /**
   * Tests rate limiting.
   */
  public function testFloodProtection(): void {
    $this->config('bin_day_finder.settings')->set('flood_limit', 2)->save();
    $this->drupalGet('/bin-day');

    $this->submitForm(['postcode' => 'S1 2HH'], 'Find collection days');
    $this->submitForm(['postcode' => 'S1 2HH'], 'Find collection days');
    $this->assertSession()->pageTextContains('Collections for S1 2HH');

    $this->submitForm(['postcode' => 'S1 2HH'], 'Find collection days');
    $this->assertSession()->pageTextContains('You have made too many searches');
  }

  /**
   * Tests the settings form is protected and saves values.
   */
  public function testSettingsForm(): void {
    $this->drupalGet('/admin/config/services/bin-day-finder');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer bin day finder']));
    $this->drupalGet('/admin/config/services/bin-day-finder');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm(['cache_lifetime' => 3600], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSame(3600, $this->config('bin_day_finder.settings')->get('cache_lifetime'));
  }

  /**
   * Tests the block can be placed and shows the form.
   */
  public function testBlock(): void {
    $this->drupalPlaceBlock('bin_day_finder_block', ['region' => 'content']);
    $this->drupalGet('/user/login');
    $this->assertSession()->buttonExists('Find collection days');

    // The block posts to the full page, where the results are shown.
    $this->assertSession()->elementAttributeContains('css', 'form.bin-day-form', 'action', '/bin-day');
    $this->submitForm(['postcode' => 'S10 2TN'], 'Find collection days');
    $this->assertSession()->addressEquals('/bin-day');
    $this->assertSession()->pageTextContains('Collections for S10 2TN');
  }

}
