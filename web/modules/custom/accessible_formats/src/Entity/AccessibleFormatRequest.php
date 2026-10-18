<?php

declare(strict_types=1);

namespace Drupal\accessible_formats\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\accessible_formats\RequestListBuilder;

/**
 * A resident's request for a document in another format.
 */
#[ContentEntityType(
  id: 'accessible_format_request',
  label: new TranslatableMarkup('Accessible format request'),
  label_collection: new TranslatableMarkup('Accessible format requests'),
  label_singular: new TranslatableMarkup('accessible format request'),
  label_plural: new TranslatableMarkup('accessible format requests'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'document_name',
  ],
  handlers: [
    'list_builder' => RequestListBuilder::class,
    'form' => [
      'edit' => ContentEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/accessible-format-requests',
    'edit-form' => '/admin/content/accessible-format-requests/{accessible_format_request}/edit',
    'delete-form' => '/admin/content/accessible-format-requests/{accessible_format_request}/delete',
  ],
  admin_permission: 'administer accessible format requests',
  base_table: 'accessible_format_request',
)]
final class AccessibleFormatRequest extends ContentEntityBase implements EntityChangedInterface {

  use EntityChangedTrait;

  /**
   * The formats a resident can ask for, keyed by machine name.
   */
  public const FORMATS = [
    'html' => 'An accessible web page',
    'large_print' => 'Large print',
    'easy_read' => 'Easy Read',
    'audio' => 'Audio',
    'braille' => 'Braille',
    'other' => 'Another format',
  ];

  /**
   * Returns the reference given to the resident, for example AF-00012.
   */
  public function reference(): string {
    return sprintf('AF-%05d', (int) $this->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['file'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Document'))
      ->setSetting('target_type', 'file')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Stored separately so the request still makes sense if the file is
    // later replaced or deleted.
    $fields['document_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Document name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Email address'))
      ->setRequired(TRUE);

    $fields['format'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Format needed'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', self::FORMATS);

    $fields['details'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Details'));

    $fields['completed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Completed'))
      ->setDescription(new TranslatableMarkup('Tick when the document has been sent.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => ['display_label' => TRUE],
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Received'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'));

    return $fields;
  }

}
