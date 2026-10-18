<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Plugin\Block;

use Drupal\bin_day_finder\Form\BinDayLookupForm;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Places the bin day lookup form in any region.
 */
#[Block(
  id: 'bin_day_finder_block',
  admin_label: new TranslatableMarkup('Bin day finder'),
  category: new TranslatableMarkup('Council services'),
)]
final class BinDayFinderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a BinDayFinderBlock.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $form = $this->formBuilder->getForm(BinDayLookupForm::class);
    // Submit to the full page so results have room to display, whatever
    // region the block is placed in.
    $form['#action'] = Url::fromRoute('bin_day_finder.lookup')->toString();
    return $form;
  }

}
