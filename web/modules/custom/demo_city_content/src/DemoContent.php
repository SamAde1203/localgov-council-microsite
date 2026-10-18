<?php

declare(strict_types=1);

namespace Drupal\demo_city_content;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates the Demo City Council demonstration content.
 *
 * Content is built with the Entity API so every field is visible in one
 * place. All of it is fictional.
 */
final class DemoContent implements ContainerInjectionInterface {

  private const FORMAT = 'wysiwyg';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected FileSystemInterface $fileSystem,
    protected ModuleExtensionList $moduleList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Creates all the content and sets up the home page.
   */
  public function build(): void {
    $this->searchButton();
    $this->bins();
    $this->councilTax();
    $this->housing();
    $this->roads();
    $this->budgetPublication();
    $this->aboutThisWebsite();
    $this->homePage();
  }

  /**
   * Bins and recycling.
   */
  private function bins(): void {
    $landing = $this->landing('Bins and recycling',
      'Find your bin day, report a missed collection and check what goes in each bin.',
      '<p>Find your bin collection day, report a missed bin and find out what goes in each bin.</p>');

    $pages = [
      $this->servicePage($landing, 'Find your bin collection day',
        'Enter your postcode to see your next black, blue and brown bin collections.',
        '<p>We collect black bins and brown bins one week, and blue bins the next.</p>'
        . '<p><a href="/bin-day">Find your bin collection day</a> by entering your postcode.</p>'
        . '<h2>When to put your bins out</h2><p>Put your bins out by 7am on your collection day, with the lid closed. Bring them back in the same day.</p>',
        [['Find your bin collection day', 'internal:/bin-day']]),
      $this->servicePage($landing, 'What goes in each bin',
        'Check which bin to use for paper, plastic, glass, food and garden waste.',
        '<h2>Blue bin</h2><ul><li>paper and cardboard</li><li>food and drink cans</li><li>glass bottles and jars</li><li>plastic bottles</li></ul>'
        . '<h2>Black bin</h2><p>General household waste that cannot be recycled.</p>'
        . '<h2>Brown bin</h2><p>Garden waste, such as grass cuttings, hedge trimmings and leaves. You need a garden waste subscription.</p>'),
      $this->servicePage($landing, 'Report a missed bin collection',
        'Tell us if we did not empty your bin on your collection day.',
        '<p>Report a missed bin by 5pm on the working day after your collection day.</p>'
        . '<p>Before you report it, check:</p><ul><li>your <a href="/bin-day">collection day</a></li><li>that your bin was out by 7am</li><li>that the lid was closed</li></ul>'),
      $this->servicePage($landing, 'Order a new or replacement bin',
        'Order a bin if yours is lost, stolen or damaged, or you have moved into a new home.',
        '<p>A replacement black or blue bin is free if yours was damaged during a collection.</p>'
        . '<p>We deliver new bins within 10 working days.</p>'),
    ];
    $this->setDestinations($landing, $pages);
  }

  /**
   * Council Tax, including a downloadable PDF.
   */
  private function councilTax(): void {
    $landing = $this->landing('Council Tax',
      'Pay your Council Tax, check your band and apply for a discount.',
      '<p>Pay your Council Tax, check your band and find out about discounts and support.</p>');

    $charges = $this->document('council-tax-charges-2026-27.pdf', 'Council Tax charges 2026 to 2027');

    $pages = [
      $this->servicePage($landing, 'Council Tax bands and charges',
        'See how much Council Tax you will pay in 2026 to 2027 for each band.',
        '<p>Your Council Tax band depends on the value of your home on 1 April 1991.</p>'
        . '<table><caption>Council Tax charges 2026 to 2027</caption><thead><tr><th scope="col">Band</th><th scope="col">Annual charge</th></tr></thead><tbody>'
        . '<tr><td>A</td><td>£1,520.12</td></tr><tr><td>B</td><td>£1,773.47</td></tr><tr><td>C</td><td>£2,026.83</td></tr><tr><td>D</td><td>£2,280.18</td></tr>'
        . '<tr><td>E</td><td>£2,786.89</td></tr><tr><td>F</td><td>£3,293.59</td></tr><tr><td>G</td><td>£3,800.30</td></tr><tr><td>H</td><td>£4,560.36</td></tr>'
        . '</tbody></table>'
        . '<p>The same charges are available to download:</p>'
        . '<drupal-media data-entity-type="media" data-entity-uuid="' . $charges->uuid() . '"></drupal-media>'),
      $this->servicePage($landing, 'Pay your Council Tax',
        'Pay online, by Direct Debit or by phone.',
        '<p>The easiest way to pay is by Direct Debit. You can choose to pay on the 1st, 15th or 28th of the month.</p>'
        . '<h2>Other ways to pay</h2><ul><li>online with a debit or credit card</li><li>by phone using our automated line</li><li>at any Post Office with your bill</li></ul>'),
      $this->servicePage($landing, 'Discounts and exemptions',
        'Find out if you can pay less Council Tax.',
        '<p>You may be able to get a discount if:</p><ul><li>you live on your own (25% off)</li><li>everyone in your home is a full-time student</li><li>you are on a low income</li></ul>'),
    ];
    $this->setDestinations($landing, $pages);
  }

  /**
   * Housing, including a multi-page guide.
   */
  private function housing(): void {
    $landing = $this->landing('Housing',
      'Apply for council housing, report a repair and get help if you are homeless.',
      '<p>Apply for council housing, report a repair to your council home and get help with housing problems.</p>');

    $repair = $this->servicePage($landing, 'Report a repair to your council home',
      'Report a repair if you rent your home from the council.',
      '<p>Report emergency repairs by phone at any time, day or night. Emergencies include:</p>'
      . '<ul><li>no heating or hot water</li><li>a gas leak (call the National Gas Emergency Service first)</li><li>flooding or a burst pipe</li></ul>'
      . '<p>Report other repairs online. We aim to fix routine repairs within 20 working days.</p>');

    $guide = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'localgov_guides_overview',
      'title' => 'Apply for council housing',
      'body' => [
        'value' => '<p>This guide explains who can apply for council housing, how to apply and how homes are offered.</p>',
        'summary' => 'Who can apply for council housing, how to apply and how homes are offered.',
        'format' => self::FORMAT,
      ],
      'localgov_guides_section_title' => 'Overview',
      'localgov_services_parent' => $landing->id(),
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);
    $guide->save();

    $sections = [
      'Who can apply' => '<p>You can apply if you are 16 or over and have lived in Demo City for at least 2 years.</p>'
      . '<p>You may be able to apply sooner if you have a strong local connection, such as close family or work in the city.</p>',
      'How to apply' => '<p>Apply online. You need:</p><ul><li>details of everyone who will live with you</li>'
      . '<li>your income and savings</li><li>your addresses for the last 5 years</li></ul>'
      . '<p>If you need help to apply, phone us or visit a library.</p>',
      'How homes are offered' => '<p>We place your application in a band from A to D, based on how urgently you need to move.</p>'
      . '<p>Each week we advertise available homes. You can bid for up to 3 homes a week.</p>',
    ];
    $pages = [];
    foreach ($sections as $title => $body) {
      $page = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'localgov_guides_page',
        'title' => $title,
        'body' => ['value' => $body, 'format' => self::FORMAT],
        'localgov_guides_section_title' => $title,
        'localgov_guides_parent' => $guide->id(),
        'status' => NodeInterface::PUBLISHED,
        'moderation_state' => 'published',
      ]);
      $page->save();
      $pages[] = ['target_id' => $page->id()];
    }
    $guide->set('localgov_guides_pages', $pages);
    $guide->save();

    $this->setDestinations($landing, [$repair, $guide]);
  }

  /**
   * Roads, parking and travel.
   */
  private function roads(): void {
    $landing = $this->landing('Roads, parking and travel',
      'Report a pothole, apply for a parking permit and find out about roadworks.',
      '<p>Report problems on roads and pavements, apply for parking permits and find out about roadworks.</p>');

    $pages = [
      $this->servicePage($landing, 'Report a pothole',
        'Tell us about a pothole or damaged pavement.',
        '<p>Report a pothole if it is on a road or pavement in Demo City.</p>'
        . '<p>We inspect dangerous potholes within 24 hours and fix them within 5 working days.</p>'),
      $this->servicePage($landing, 'Apply for a resident parking permit',
        'Apply for a permit to park in a resident parking zone.',
        '<p>A resident parking permit costs £60 a year for your first vehicle.</p>'
        . '<p>You need your vehicle registration number and proof that you live in the zone.</p>'),
    ];
    $this->setDestinations($landing, $pages);
  }

  /**
   * A publication that is an HTML document first, with a PDF version.
   */
  private function budgetPublication(): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $chapters = [
      'Annual budget 2026 to 2027' => '<p>The council plans to spend £412 million on services in 2026 to 2027.</p>'
      . '<p>Most of the budget pays for care and support for adults and children.</p>',
      'Where the money goes' => '<table><caption>Planned spending 2026 to 2027</caption>'
      . '<thead><tr><th scope="col">Service</th><th scope="col">Spending</th></tr></thead><tbody>'
      . '<tr><td>Adult social care</td><td>£148 million</td></tr>'
      . '<tr><td>Children\'s services</td><td>£96 million</td></tr>'
      . '<tr><td>Waste and recycling</td><td>£31 million</td></tr>'
      . '<tr><td>Roads and transport</td><td>£27 million</td></tr>'
      . '<tr><td>Housing support</td><td>£22 million</td></tr>'
      . '<tr><td>Other services</td><td>£88 million</td></tr></tbody></table>',
      'Where the money comes from' => '<p>Council Tax pays for about half of our services. '
      . 'The rest comes from business rates, government grants and fees and charges.</p>',
    ];

    $root = NULL;
    $weight = 0;
    foreach ($chapters as $title => $body) {
      $page = $storage->create([
        'type' => 'localgov_publication_page',
        'title' => $title,
        'localgov_publication_content' => [$this->textParagraph($body)],
        'localgov_published_date' => '2026-03-01',
        'status' => NodeInterface::PUBLISHED,
        'moderation_state' => 'published',
      ]);
      $page->book = $root
        ? ['bid' => $root->id(), 'pid' => $root->id(), 'weight' => $weight]
        : ['bid' => 'new'];
      $page->save();
      $root ??= $page;
      $weight++;
    }

    $pdf = $this->document('annual-budget-2026-27.pdf', 'Annual budget 2026 to 2027 (PDF version)');
    $storage->create([
      'type' => 'localgov_publication_cover_page',
      'title' => 'Annual budget 2026 to 2027',
      'body' => [
        'value' => '<p>How the council plans to spend its money in 2026 to 2027.</p>',
        'summary' => 'How the council plans to spend its money in 2026 to 2027.',
        'format' => self::FORMAT,
      ],
      'localgov_publication' => [['target_id' => $root->id()]],
      'localgov_documents' => [['target_id' => $pdf->id()]],
      'localgov_publication_content' => [$this->textParagraph('<p>Read the budget online. The web version works with screen readers and on phones. A PDF version is also available.</p>')],
      'localgov_published_date' => '2026-03-01',
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ])->save();
  }

  /**
   * About this website, including the accessibility statement.
   *
   * UK public sector websites must publish an accessibility statement.
   * This one follows the GOV.UK sample statement.
   */
  private function aboutThisWebsite(): void {
    $landing = $this->landing('About this website',
      'Accessibility, privacy and how this website works.',
      '<p>Information about this website, including how accessible it is.</p>');

    $statement = $this->servicePage($landing, 'Accessibility statement',
      'How accessible this website is, what to do if you have a problem and how to ask for another format.',
      '<p>This accessibility statement applies to the Demo City Council website. '
      . 'Demo City Council is fictional: this website is a portfolio project.</p>'
      . '<h2>Using this website</h2><p>We want as many people as possible to be able to use this website. '
      . 'For example, you should be able to:</p><ul>'
      . '<li>zoom in up to 400% without the text spilling off the screen</li>'
      . '<li>navigate the whole website using just a keyboard</li>'
      . '<li>listen to most of the website using a screen reader</li></ul>'
      . '<h2>How accessible this website is</h2><p>We test every page against the Web Content Accessibility '
      . 'Guidelines (WCAG) version 2.2 AA standard, with automated checks on every change. '
      . 'We know some parts are not fully accessible:</p><ul>'
      . '<li>some older PDF documents may not work well with screen readers</li>'
      . '<li>we have not yet tested the website with every screen reader</li></ul>'
      . '<h2>Asking for information in a different format</h2><p>If you need a document in a different format, '
      . 'such as large print, Easy Read, audio or braille, use the "Request an accessible format" link under the document. '
      . 'We will reply within 10 working days.</p>'
      . '<h2>Enforcement procedure</h2><p>The Equality and Human Rights Commission (EHRC) is responsible for enforcing '
      . 'the accessibility regulations. If you are not happy with how we respond to your complaint, '
      . 'contact the Equality Advisory and Support Service (EASS).</p>'
      . '<h2>Preparation of this statement</h2><p>This statement was prepared in October 2026. '
      . 'The website was last tested in October 2026. The results are in the accessibility audit report published with the code.</p>');
    $this->setDestinations($landing, [$statement]);
  }

  /**
   * Labels the search button "Search" instead of "Apply".
   *
   * Accessibility audit finding A2: "Apply" does not say what the button does.
   */
  private function searchButton(): void {
    $view = $this->configFactory->getEditable('views.view.localgov_sitewide_search');
    foreach ($view->get('display') as $id => $display) {
      if (isset($display['display_options']['exposed_form']['options']['submit_button'])) {
        $view->set("display.$id.display_options.exposed_form.options.submit_button", 'Search');
      }
    }
    $view->save();
  }

  /**
   * Sets up the home page.
   */
  private function homePage(): void {
    $this->configFactory->getEditable('system.site')
      ->set('page.front', '/home')
      ->set('slogan', 'Demonstration site for a portfolio project')
      ->save();

    $block = $this->entityTypeManager->getStorage('block')->load('demo_city_localgov_home_welcome_block_scarfolk');
    if ($block) {
      $block->getPlugin()->setConfigurationValue('localgov_home_welcome_message', [
        'value' => '<h2>Welcome to Demo City Council</h2><p>Find information and services from the council. Start with one of the topics below.</p><p><strong>This is a demonstration site for a portfolio project. It is not a real council.</strong></p><p>Read our <a href="/about-website/accessibility-statement">accessibility statement</a>.</p>',
        'format' => self::FORMAT,
      ]);
      $block->save();
    }
  }

  /**
   * Creates a published services landing page.
   */
  private function landing(string $title, string $summary, string $body): NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'localgov_services_landing',
      'title' => $title,
      'body' => ['value' => $body, 'summary' => $summary, 'format' => self::FORMAT],
      'localgov_phone' => '0300 123 4567',
      'localgov_email_address' => 'contact@example.com',
      'localgov_opening_hours' => [
        'value' => '<p>Monday to Friday, 9am to 5pm</p>',
        'format' => self::FORMAT,
      ],
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);
    $node->save();
    assert($node instanceof NodeInterface);
    return $node;
  }

  /**
   * Creates a published services page under a landing page.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   The landing page.
   * @param string $title
   *   The page title.
   * @param string $summary
   *   One-line summary, shown in lists.
   * @param string $body
   *   Body HTML.
   * @param array $tasks
   *   Call-to-action links as [title, uri] pairs.
   */
  private function servicePage(NodeInterface $parent, string $title, string $summary, string $body, array $tasks = []): NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'localgov_services_page',
      'title' => $title,
      'body' => ['value' => $body, 'summary' => $summary, 'format' => self::FORMAT],
      'localgov_services_parent' => $parent->id(),
      'localgov_common_tasks' => array_map(fn (array $t) => ['title' => $t[0], 'uri' => $t[1]], $tasks),
      'status' => NodeInterface::PUBLISHED,
      'moderation_state' => 'published',
    ]);
    $node->save();
    assert($node instanceof NodeInterface);
    return $node;
  }

  /**
   * Lists the given pages on a landing page.
   *
   * @param \Drupal\node\NodeInterface $landing
   *   The landing page.
   * @param \Drupal\node\NodeInterface[] $pages
   *   The pages to list, in order.
   */
  private function setDestinations(NodeInterface $landing, array $pages): void {
    $landing->set('localgov_destinations', array_map(fn (NodeInterface $p) => ['target_id' => $p->id()], $pages));
    $landing->save();
  }

  /**
   * Creates a document media item from a file shipped with this module.
   */
  private function document(string $filename, string $name): object {
    $source = $this->moduleList->getPath('demo_city_content') . '/files/' . $filename;
    $directory = 'public://documents';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $uri = $this->fileSystem->copy($source, $directory . '/' . $filename, FileExists::Replace);

    $file = $this->entityTypeManager->getStorage('file')->create([
      'uri' => $uri,
      'filename' => $filename,
      'status' => 1,
    ]);
    $file->save();

    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'document',
      'name' => $name,
      'field_media_document' => [
        'target_id' => $file->id(),
        'description' => $name,
      ],
      'status' => 1,
    ]);
    $media->save();
    return $media;
  }

  /**
   * Creates a text paragraph.
   */
  private function textParagraph(string $html): object {
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'localgov_text',
      'localgov_text' => ['value' => $html, 'format' => self::FORMAT],
    ]);
    $paragraph->save();
    return $paragraph;
  }

}
