<?php

declare(strict_types=1);

namespace Drupal\bin_day_finder\Form;

use Drupal\bin_day_finder\Exception\InvalidPostcodeException;
use Drupal\bin_day_finder\Model\Collection;
use Drupal\bin_day_finder\Service\CollectionScheduleProviderInterface;
use Drupal\bin_day_finder\Service\PostcodeLookupInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Postcode form that shows the resident's next bin collections.
 *
 * Works without JavaScript: the form posts back to the same page and is
 * rebuilt with the results underneath.
 *
 * Injected services are protected and not readonly on purpose. When a form
 * is rebuilt, Drupal serialises the form object and re-injects services in
 * DependencySerializationTrait::__wakeup(), which runs in a parent class and
 * so cannot write to private or readonly properties declared here.
 */
final class BinDayLookupForm extends FormBase {

  private const FLOOD_EVENT = 'bin_day_finder.lookup';
  private const FLOOD_WINDOW = 3600;

  /**
   * Constructs a BinDayLookupForm.
   *
   * @param \Drupal\bin_day_finder\Service\PostcodeLookupInterface $postcodeLookup
   *   The postcode lookup service.
   * @param \Drupal\bin_day_finder\Service\CollectionScheduleProviderInterface $schedule
   *   The collection schedule provider.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service, used to rate-limit lookups.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(
    protected PostcodeLookupInterface $postcodeLookup,
    protected CollectionScheduleProviderInterface $schedule,
    protected FloodInterface $flood,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('bin_day_finder.postcode_lookup'),
      $container->get('bin_day_finder.schedule'),
      $container->get('flood'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bin_day_finder_lookup';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'bin-day-form';

    $form['postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#description' => $this->t('For example, S1 2HH'),
      '#required' => TRUE,
      '#size' => 10,
      '#maxlength' => 10,
      '#attributes' => [
        'autocomplete' => 'postal-code',
        'spellcheck' => 'false',
        'class' => ['bin-day-form__postcode'],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Find collection days'),
    ];

    if ($result = $form_state->get('result')) {
      $form['results'] = [
        '#theme' => 'bin_day_finder_results',
        '#postcode' => $result['postcode'],
        '#district' => $result['district'],
        '#verified' => $result['verified'],
        '#collections' => $result['collections'],
        '#weight' => 100,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Never show the previous postcode's dates alongside a new search's
    // error message.
    $form_state->set('result', NULL);
    unset($form['results']);

    $limit = (int) $this->config('bin_day_finder.settings')->get('flood_limit') ?: 20;
    if (!$this->flood->isAllowed(self::FLOOD_EVENT, $limit, self::FLOOD_WINDOW)) {
      $form_state->setErrorByName('postcode', $this->t('You have made too many searches. Try again in an hour.'));
      return;
    }
    $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);

    $input = (string) $form_state->getValue('postcode');
    if (trim($input) === '') {
      // The #required check has already reported this.
      return;
    }

    try {
      $form_state->set('postcode_result', $this->postcodeLookup->lookup($input));
    }
    catch (InvalidPostcodeException) {
      $form_state->setErrorByName('postcode', $this->t('Enter a real UK postcode, like S1 2HH'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\bin_day_finder\Model\PostcodeResult $postcode */
    $postcode = $form_state->get('postcode_result');

    $form_state->set('result', [
      'postcode' => $postcode->postcode,
      'district' => $postcode->district,
      'verified' => $postcode->verified,
      'collections' => array_map(
        fn (Collection $collection): array => $this->present($collection),
        $this->schedule->getUpcoming($postcode),
      ),
    ]);
    $form_state->setRebuild();
  }

  /**
   * Converts a Collection into template variables.
   */
  private function present(Collection $collection): array {
    $relative = match ($collection->daysUntil) {
      0 => $this->t('Today'),
      1 => $this->t('Tomorrow'),
      default => NULL,
    };

    return [
      'id' => $collection->binId,
      'label' => $collection->label,
      'description' => $collection->description,
      'date_iso' => $collection->date->format('Y-m-d'),
      'date_label' => $this->dateFormatter->format(
        $collection->date->getTimestamp(),
        'custom',
        'l j F Y',
      ),
      'relative' => $relative,
    ];
  }

}
