/**
 * @file
 * tabledrag.js overrides and functionality extensions.
 */

(($, Drupal) => {
  /**
   * Extends core's Tabledrag functionality.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.exampleThemeTableDrag = {
    attach(context, settings) {
            function initTableDrag(table, base) {
        if (table.length) {
          // Create the new tableDrag instance. Save in the Drupal variable
          // to allow other scripts access to the object.
          Drupal.tableDrag[base] = new Drupal.tableDrag(
            table[0],
            settings.tableDrag[base]
          );
        }
      }

      Object.keys(settings.tableDrag || {}).forEach((base) => {
        initTableDrag($(once('tabledrag', `#${base}`, context)), base);
      });

  /**
   * Determine if an element is visible.
   *
   * @param {HTMLElement} elem
   *   The element to check.
   *
   * @return {boolean}
   *  True if the element is visible.
   */
  Drupal.elementIsVisible = function (elem) {
    return !!(
      elem.offsetWidth ||
      elem.offsetHeight ||
      elem.getClientRects().length
    );
  };

  /**
   * Determine if an element is hidden.
   *
   * @param {HTMLElement} elem
   *   The element to check.
   *
   * @return {boolean}
   *  True if the element is hidden.
   */
  Drupal.elementIsHidden = function (elem) {
    return !Drupal.elementIsVisible(elem);
  };

    },
  };

  $.extend(
    Drupal.theme,
    /** @lends Drupal.theme */ {
      /**
       * @return {string}
       *   Markup for the warning.
       */
      tableDragChangedWarning() {
        return `<div class="tabledrag-changed-warning messages messages--warning" role="alert">${Drupal.theme(
          'tableDragChangedMarker',
        )} ${Drupal.t('You have unsaved changes.')}</div>`;
      },

      /**
       * The button for toggling table row weight visibility.
       *
       * @return {string}
       *   HTML markup for the weight toggle button and its container.
       */
      tableDragToggle: () =>
        `<div class="tabledrag-toggle-weight-wrapper" data-drupal-selector="tabledrag-toggle-weight-wrapper">
            <button type="button" class="link tabledrag-toggle-weight" data-drupal-selector="tabledrag-toggle-weight"></button>
            </div>`,

    }
  );

})(jQuery, Drupal);
