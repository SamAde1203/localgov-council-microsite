<?php

declare(strict_types=1);

namespace Drupal\accessible_formats\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\accessible_formats\Entity\AccessibleFormatRequest;
use Drupal\accessible_formats\Hook\AccessibleFormatsHooks;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets a resident ask for a document in a format they can use.
 *
 * Injected services are protected and not readonly, because Drupal
 * serialises form objects between requests (see DependencySerializationTrait).
 */
final class RequestForm extends FormBase {

  private const FLOOD_EVENT = 'accessible_formats.request';
  private const FLOOD_WINDOW = 3600;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FloodInterface $flood,
    protected MailManagerInterface $mailManager,
    protected AccessibleFormatsHooks $hooks,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('flood'),
      $container->get('plugin.manager.mail'),
      $container->get(AccessibleFormatsHooks::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'accessible_formats_request_form';
  }

  /**
   * Allows requests for documents the visitor can download.
   *
   * The file must be permanent and of a type that shows the notice, so the
   * form cannot be used for images or other files.
   */
  public function access(FileInterface $file, AccountInterface $account): AccessResultInterface {
    $config = $this->config('accessible_formats.settings');
    $is_document = $this->hooks->needsNotice($file->getFilename(), (string) $config->get('extensions'));
    return AccessResult::allowedIf($file->isPermanent() && $is_document)
      ->andIf($file->access('download', $account, TRUE))
      ->addCacheableDependency($file)
      ->addCacheableDependency($config);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FileInterface $file = NULL): array {
    $form_state->set('file_id', $file?->id());
    $document = $file?->getFilename() ?? '';
    $settings = $this->config('accessible_formats.settings');

    if ($reference = $form_state->get('reference')) {
      return [
        'confirmation' => [
          '#theme' => 'accessible_format_confirmation',
          '#reference' => $reference,
          '#email' => $form_state->get('email'),
          '#days' => (int) $settings->get('response_days'),
          '#document' => $document,
          '#attached' => ['library' => ['accessible_formats/notice', 'accessible_formats/confirmation']],
        ],
      ];
    }

    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Tell us which format you need for %document and we will send it to you within @days working days.', [
        '%document' => $document,
        '@days' => (int) $settings->get('response_days'),
      ]),
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full name'),
      '#required' => TRUE,
      '#required_error' => $this->t('Enter your full name'),
      '#maxlength' => 255,
      '#attributes' => ['autocomplete' => 'name'],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#description' => $this->t('We will only use this to send you the document.'),
      '#required' => TRUE,
      '#required_error' => $this->t('Enter your email address'),
      '#attributes' => ['autocomplete' => 'email', 'spellcheck' => 'false'],
    ];

    $form['format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Which format do you need?'),
      '#required_error' => $this->t('Select the format you need'),
      '#options' => [
        'html' => $this->t('An accessible web page'),
        'large_print' => $this->t('Large print'),
        'easy_read' => $this->t('Easy Read'),
        'audio' => $this->t('Audio'),
        'braille' => $this->t('Braille'),
        'other' => $this->t('Another format'),
      ],
      '#required' => TRUE,
    ];

    $form['details'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Anything else we should know (optional)'),
      '#description' => $this->t('If you chose "Another format", tell us which one.'),
      '#rows' => 4,
      '#maxlength' => 2000,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send request'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $limit = (int) $this->config('accessible_formats.settings')->get('flood_limit') ?: 5;
    if (!$this->flood->isAllowed(self::FLOOD_EVENT, $limit, self::FLOOD_WINDOW)) {
      $form_state->setErrorByName('name', $this->t('You have sent too many requests. Try again in an hour, or phone us.'));
      return;
    }

    if ($form_state->getValue('format') === 'other' && trim((string) $form_state->getValue('details')) === '') {
      $form_state->setErrorByName('details', $this->t('Tell us which format you need'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);

    $file = $this->entityTypeManager->getStorage('file')->load($form_state->get('file_id'));
    assert($file instanceof FileInterface);

    $request = $this->entityTypeManager->getStorage('accessible_format_request')->create([
      'file' => $file->id(),
      'document_name' => $file->getFilename(),
      'name' => trim((string) $form_state->getValue('name')),
      'email' => trim((string) $form_state->getValue('email')),
      'format' => $form_state->getValue('format'),
      'details' => trim((string) $form_state->getValue('details')),
    ]);
    assert($request instanceof AccessibleFormatRequest);
    $request->save();

    $this->logger('accessible_formats')->notice('Accessible format request @reference received for @document.', [
      '@reference' => $request->reference(),
      '@document' => $file->getFilename(),
    ]);

    $notify = (string) $this->config('accessible_formats.settings')->get('notify_email');
    if ($notify !== '') {
      $this->mailManager->mail('accessible_formats', 'new_request', $notify, $this->currentUser()->getPreferredLangcode(), [
        'reference' => $request->reference(),
        'name' => $request->get('name')->value,
        'email' => $request->get('email')->value,
        'document' => $file->getFilename(),
        'format' => AccessibleFormatRequest::FORMATS[$request->get('format')->value],
        'details' => $request->get('details')->value,
      ]);
    }

    $form_state->set('reference', $request->reference());
    $form_state->set('email', $request->get('email')->value);
    $form_state->setRebuild();
  }

}
