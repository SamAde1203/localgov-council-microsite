/**
 * @file
 * Moves keyboard and screen reader focus to the results after a search.
 *
 * The form is a normal POST (no AJAX), so without this a screen reader user
 * would land back at the top of the page with no clue that results appeared.
 */
((Drupal, once) => {
  Drupal.behaviors.binDayFinderResults = {
    attach(context) {
      once('bin-day-results', '[data-bin-day-results]', context).forEach(
        (results) => {
          const heading = results.querySelector('#bin-day-results-title');
          if (heading) {
            heading.focus();
          }
        },
      );
    },
  };
})(Drupal, once);
