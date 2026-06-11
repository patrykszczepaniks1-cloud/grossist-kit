/* GrossistKit Dashboard JS */
(function ($) {
  'use strict';

  var rowIndex = 1;
  var currentGroup = 'bas';

  // ─── Customer select → update group ──────────────────────────────────────
  $(document).on('change', '#gk-customer-select', function () {
    currentGroup = $(this).find(':selected').data('group') || 'bas';
    updateAllPrices();
  });

  // ─── Product select → update SKU + price ─────────────────────────────────
  $(document).on('change', '.gk-product-select', function () {
    var $row  = $(this).closest('.gk-product-row');
    var pid   = $(this).val();
    updateRowDisplay($row, pid);
  });

  function updateRowDisplay($row, pid) {
    var products = window.gkProducts || {};
    if (!pid || !products[pid]) {
      $row.find('.gk-sku-display').text('–');
      $row.find('.gk-price-display').text('–');
      return;
    }
    var data  = products[pid];
    var price = data.price[currentGroup] !== undefined ? data.price[currentGroup] : data.price['default'];
    $row.find('.gk-sku-display').text(data.sku || '–');
    $row.find('.gk-price-display').text(formatPrice(price));
  }

  function updateAllPrices() {
    $('#gk-product-rows .gk-product-row').each(function () {
      var pid = $(this).find('.gk-product-select').val();
      updateRowDisplay($(this), pid);
    });
  }

  function formatPrice(p) {
    if (!p && p !== 0) return '–';
    return parseFloat(p).toFixed(2).replace('.', ',') + ' kr';
  }

  // ─── Add product row ──────────────────────────────────────────────────────
  $(document).on('click', '#gk-add-product', function () {
    var $first = $('#gk-product-rows .gk-product-row:first').clone();
    $first.find('select').attr('name', 'gk_products[' + rowIndex + '][id]').val('');
    $first.find('input[type="number"]').attr('name', 'gk_products[' + rowIndex + '][qty]').val(1);
    $first.find('.gk-sku-display').text('–');
    $first.find('.gk-price-display').text('–');
    $('#gk-product-rows').append($first);
    rowIndex++;
  });

  // ─── Remove product row ───────────────────────────────────────────────────
  $(document).on('click', '.gk-remove-row', function () {
    if ($('#gk-product-rows .gk-product-row').length > 1) {
      $(this).closest('.gk-product-row').remove();
    }
  });

  // ─── Expand/collapse ──────────────────────────────────────────────────────
  $(document).on('click', '.gk-expand-btn', function () {
    var target = '#' + $(this).data('target');
    var $el    = $(target);
    var isOpen = $el.is(':visible');
    $el.slideToggle(180);
    $(this).text( isOpen
      ? $(this).text().replace('▴', '▾')
      : $(this).text().replace('▾', '▴')
    );
  });

  // ─── Auto-dismiss notices ─────────────────────────────────────────────────
  setTimeout(function () { $('.gk-notice').fadeOut(400); }, 5000);

}(jQuery));
