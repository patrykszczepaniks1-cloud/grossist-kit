/* GrossistKit Dashboard JS */
(function ($) {
  'use strict';

  /* ── Org number auto-format XXXXXX-XXXX ── */
  $(document).on('input', '.gk-org-input', function () {
    var v   = $(this).val().replace(/[^0-9]/g, '');
    if (v.length > 10) v = v.slice(0, 10);
    if (v.length > 6)  v = v.slice(0, 6) + '-' + v.slice(6);
    $(this).val(v);
  });


  var rowIndex = 1;
  var currentGroup = 'bas';

  /* ── Customer group → update all product rows ── */
  $(document).on('change', '#gk-customer-select', function () {
    currentGroup = $(this).find(':selected').data('group') || 'bas';
    refreshAllRows();
  });

  /* ── Product select → update row display ── */
  $(document).on('change', '.gk-product-select', function () {
    refreshRow($(this).closest('.gk-product-row'));
  });

  function refreshRow($row) {
    var pid      = $row.find('.gk-product-select').val();
    var products = window.gkProducts || {};

    if (!pid || !products[pid]) {
      $row.find('.gk-sku-cell').text('–');
      $row.find('.gk-price-cell').text('–');
      $row.find('.gk-stock-cell').text('–');
      return;
    }

    var d     = products[pid];
    var price = d.price[currentGroup] !== undefined ? d.price[currentGroup] : d.price['bas'];
    var stock = d.stock !== null && d.stock !== undefined ? d.stock + ' st' : '∞';

    $row.find('.gk-sku-cell').text(d.sku || '–');
    $row.find('.gk-price-cell').text(fmtPrice(price));
    $row.find('.gk-stock-cell').text(stock);
  }

  function refreshAllRows() {
    $('#gk-product-rows .gk-product-row').each(function () { refreshRow($(this)); });
  }

  function fmtPrice(p) {
    if (p === '' || p === null || p === undefined) return '–';
    return parseFloat(p).toFixed(2).replace('.', ',') + ' kr';
  }

  /* ── Add product row ── */
  $(document).on('click', '#gk-add-product', function () {
    var $clone = $('#gk-product-rows .gk-product-row:first').clone();
    $clone.find('select').attr('name', 'gk_products[' + rowIndex + '][id]').val('');
    $clone.find('.gk-qty-input').attr('name', 'gk_products[' + rowIndex + '][qty]').val(1);
    $clone.find('.gk-sku-cell, .gk-price-cell, .gk-stock-cell').text('–');
    $('#gk-product-rows').append($clone);
    rowIndex++;
  });

  /* ── Remove product row ── */
  $(document).on('click', '.gk-remove-row', function () {
    if ($('#gk-product-rows .gk-product-row').length > 1) {
      $(this).closest('.gk-product-row').remove();
    }
  });

  /* ── Expand / collapse ── */
  $(document).on('click', '.gk-expand-trigger', function () {
    var $btn    = $(this);
    var $target = $('#' + $btn.data('target'));
    var open    = $target.is(':visible');
    $target.slideToggle(160);
    $btn.toggleClass('open', !open);
    $btn.find('.material-icons-round').text(open ? 'expand_more' : 'expand_less');
  });

  /* ── Customer search filter ── */
  $(document).on('input', '#gk-customer-filter', function () {
    var q = $(this).val().toLowerCase().trim();
    $('#gk-customer-list tbody tr.gk-customer-tr').each(function () {
      var hay = $(this).data('search') || '';
      $(this).toggle(q === '' || hay.indexOf(q) !== -1);
    });
  });

  /* ── Edit customer modal ── */
  $(document).on('click', '.gk-open-edit', function () {
    var d = $(this).data();
    $('#gk-edit-user-id').val(d.id);
    $('#gk-edit-company').val(d.company || '');
    $('#gk-edit-firstname').val(d.firstname || '');
    $('#gk-edit-lastname').val(d.lastname || '');
    $('#gk-edit-email').val(d.email || '');
    $('#gk-edit-phone').val(d.phone || '');
    $('#gk-edit-city').val(d.city || '');
    $('#gk-edit-org').val(d.org || '');
    $('#gk-edit-group').val(d.group || 'bas');
    $('#gk-edit-modal').fadeIn(150);
    $('body').css('overflow', 'hidden');
  });

  function closeModal() {
    $('#gk-edit-modal').fadeOut(150);
    $('body').css('overflow', '');
  }

  $(document).on('click', '#gk-modal-close, #gk-modal-cancel', closeModal);
  $(document).on('click', '#gk-edit-modal', function (e) {
    if ($(e.target).is('#gk-edit-modal')) closeModal();
  });
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  /* ── Auto-dismiss notices ── */
  setTimeout(function () { $('.gk-notice').slideUp(300); }, 5000);

}(jQuery));
