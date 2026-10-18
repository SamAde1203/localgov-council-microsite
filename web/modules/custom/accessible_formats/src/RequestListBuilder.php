<?php

declare(strict_types=1);

namespace Drupal\accessible_formats;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\accessible_formats\Entity\AccessibleFormatRequest;

/**
 * Lists accessible format requests, newest first, for the content team.
 */
final class RequestListBuilder extends EntityListBuilder {

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = parent::createInstance($container, $entity_type);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    return $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort('completed', 'ASC')
      ->sort('created', 'DESC')
      ->pager($this->limit)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'reference' => $this->t('Reference'),
      'document' => $this->t('Document'),
      'format' => $this->t('Format'),
      'name' => $this->t('Name'),
      'received' => $this->t('Received'),
      'status' => $this->t('Status'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof AccessibleFormatRequest);
    $format = (string) $entity->get('format')->value;
    return [
      'reference' => $entity->reference(),
      'document' => $entity->label(),
      'format' => AccessibleFormatRequest::FORMATS[$format] ?? $format,
      'name' => $entity->get('name')->value,
      'received' => $this->dateFormatter->format((int) $entity->get('created')->value, 'short'),
      'status' => $entity->get('completed')->value ? $this->t('Completed') : $this->t('Waiting'),
    ] + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are no requests yet.');
    return $build;
  }

}
