<?php

declare(strict_types=1);

namespace Drupal\Tests\accessible_formats\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\accessible_formats\Entity\AccessibleFormatRequest;
use Drupal\accessible_formats\Hook\AccessibleFormatsHooks;
use Drupal\file\Entity\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the request entity and the document notice.
 */
#[Group('accessible_formats')]
final class AccessibleFormatsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'accessible_formats',
    'file',
    'options',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('accessible_format_request');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['accessible_formats']);
  }

  /**
   * A saved request gets a reference residents can quote.
   */
  public function testRequestReference(): void {
    $file = $this->createFile('council-tax-guide.pdf');
    $request = AccessibleFormatRequest::create([
      'file' => $file->id(),
      'document_name' => $file->getFilename(),
      'name' => 'Ada Resident',
      'email' => 'ada@example.com',
      'format' => 'large_print',
    ]);
    $request->save();

    $this->assertSame(sprintf('AF-%05d', $request->id()), $request->reference());
    $this->assertSame('council-tax-guide.pdf', $request->label());
    $this->assertFalse((bool) $request->get('completed')->value);
  }

  /**
   * Only the configured document types get the notice.
   */
  #[DataProvider('providerExtensions')]
  public function testNeedsNotice(string $filename, bool $expected): void {
    $hooks = $this->container->get(AccessibleFormatsHooks::class);
    $extensions = $this->config('accessible_formats.settings')->get('extensions');
    $this->assertSame($expected, $hooks->needsNotice($filename, $extensions));
  }

  /**
   * Data provider for testNeedsNotice().
   */
  public static function providerExtensions(): array {
    return [
      'pdf' => ['budget.pdf', TRUE],
      'upper case' => ['MINUTES.PDF', TRUE],
      'word' => ['form.docx', TRUE],
      'spreadsheet' => ['data.xlsx', TRUE],
      'image' => ['photo.jpg', FALSE],
      'csv is already plain text' => ['data.csv', FALSE],
      'no extension' => ['README', FALSE],
    ];
  }

  /**
   * The preprocess hook adds a link to the request form for a PDF.
   */
  public function testPreprocessAddsNotice(): void {
    $file = $this->createFile('local-plan.pdf');
    $variables = ['file' => $file];
    $this->container->get(AccessibleFormatsHooks::class)->preprocessFileLink($variables);

    $notice = $variables['accessible_format_notice'];
    $this->assertSame('accessible_format_notice', $notice['#theme']);
    $this->assertStringEndsWith('/accessible-format/request/' . $file->id(), $notice['#url']);
    $this->assertSame('local-plan.pdf', $notice['#document']);
  }

  /**
   * Images and other non-document files are left alone.
   */
  public function testPreprocessSkipsImages(): void {
    $variables = ['file' => $this->createFile('map.png')];
    $this->container->get(AccessibleFormatsHooks::class)->preprocessFileLink($variables);
    $this->assertArrayNotHasKey('accessible_format_notice', $variables);
  }

  /**
   * Creates a permanent public file entity.
   */
  private function createFile(string $filename): File {
    file_put_contents('public://' . $filename, 'Test document');
    $file = File::create([
      'uri' => 'public://' . $filename,
      'filename' => $filename,
    ]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

}
