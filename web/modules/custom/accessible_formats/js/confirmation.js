/**
 * @file
 * Moves focus to the confirmation after a request is sent.
 *
 * The form posts back to the same page, so without this a screen reader user
 * would start again at the top of the page and might not hear the reference.
 */
((Drupal, once) => {
  Drupal.behaviors.accessibleFormatsConfirmation = {
    attach(context) {
      once('accessible-formats-confirmation', '#accessible-format-confirmation', context).forEach((element) => {
        element.focus();
      });
    },
  };
})(Drupal, once);
