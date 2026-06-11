/* GrossistKit Dashboard JS */
(function ($) {
  'use strict';

  var rowIndex = 1;

  // ─── Add product row ──────────────────────────────────────────────────────
  $(document).on('click', '#gk-add-product', function () {
    var $first = $('#gk-product-rows .gk-product-row:first');
    var $clone = $first.clone();

    // Update name indices
    $clone.find('select').attr('name', 'gk_products[' + rowIndex + '][id]').val('');
    $clone.find('input[type="number"]').attr('name', 'gk_products[' + rowIndex + '][qty]').val(1);

    $('#gk-product-rows').append($clone);
    rowIndex++;
  });

  // ─── Remove product row ───────────────────────────────────────────────────
  $(document).on('click', '.gk-remove-product-row', function () {
    var $rows = $('#gk-product-rows .gk-product-row');
    if ($rows.length > 1) {
      $(this).closest('.gk-product-row').remove();
    }
  });

  // ─── Auto-dismiss notices ─────────────────────────────────────────────────
  setTimeout(function () {
    $('.gk-notice').fadeOut(400);
  }, 5000);

}(jQuery));
