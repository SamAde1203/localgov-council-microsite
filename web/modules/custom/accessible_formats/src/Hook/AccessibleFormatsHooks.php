<?php

declare(strict_types=1);

namespace Drupal\accessible_formats\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\file\FileInterface;

/**
 * Hook implementations for the Accessible Formats module.
 */
final class AccessibleFormatsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'accessible_format_notice' => [
        'variables' => ['url' => NULL, 'document' => NULL],
      ],
      'accessible_format_confirmation' => [
        'variables' => [
          'reference' => NULL,
          'email' => NULL,
          'days' => NULL,
          'document' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for file_link.
   *
   * Adds an `accessible_format_notice` variable under documents such as PDFs
   * and Word files. A theme prints it in its file-link.html.twig template.
   */
  #[Hook('preprocess_file_link')]
  public function preprocessFileLink(array &$variables): void {
    $config = $this->configFactory->get('accessible_formats.settings');

    $file = $variables['file'] ?? NULL;
    if (!$file instanceof FileInterface || $file->isNew() || !$this->needsNotice($file->getFilename(), (string) $config->get('extensions'))) {
      return;
    }

    $variables['accessible_format_notice'] = [
      '#theme' => 'accessible_format_notice',
      '#url' => Url::fromRoute('accessible_formats.request', ['file' => $file->id()])->toString(),
      '#document' => $file->getFilename(),
      '#attached' => ['library' => ['accessible_formats/notice']],
      '#cache' => ['tags' => ['config:accessible_formats.settings']],
    ];
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if ($key !== 'new_request') {
      return;
    }
    $message['subject'] = $this->t('Accessible format request @reference', ['@reference' => $params['reference']]);
    $message['body'][] = $this->t('@name asked for "@document" as: @format.', [
      '@name' => $params['name'],
      '@document' => $params['document'],
      '@format' => $params['format'],
    ]);
    if (!empty($params['details'])) {
      $message['body'][] = $this->t('Details: @details', ['@details' => $params['details']]);
    }
    $message['body'][] = $this->t('Reply to: @email', ['@email' => $params['email']]);
  }

  /**
   * Whether a file name has one of the configured extensions.
   *
   * @param string $filename
   *   The file name, for example "budget-2026.pdf".
   * @param string $extensions
   *   Extensions separated by spaces, for example "pdf docx".
   */
  public function needsNotice(string $filename, string $extensions): bool {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === '') {
      return FALSE;
    }
    $allowed = preg_split('/\s+/', strtolower(trim($extensions)), -1, PREG_SPLIT_NO_EMPTY);
    return in_array($extension, $allowed, TRUE);
  }

}
