/* isprithul core · admin: project photos, portrait picker, drag-to-reorder projects */
(function ($) {
  'use strict';

  /* ---------- project photos (first = cover) ---------- */
  $('[data-isp-gallery]').each(function () {
    var box = $(this), input = box.find('input[name="isp_gallery"]'), list = box.find('.isp-gallery__list'), frame;
    function sync() {
      input.val(list.children('li').map(function () { return $(this).data('id'); }).get().join(','));
    }
    function item(att) {
      var sizes = att.sizes || {}, src = (sizes.thumbnail || sizes.medium || sizes.full || {}).url || att.url;
      return $('<li>').attr('data-id', att.id).append(
        $('<img alt="">').attr('src', src),
        $('<button type="button" class="isp-gallery__remove" aria-label="Remove this photo from the project">×</button>'),
        $('<a class="isp-gallery__edit" target="_blank" rel="noopener">alt text</a>').attr('href', att.editLink || '#')
      );
    }
    list.sortable({ items: 'li', tolerance: 'pointer', stop: sync });
    box.on('click', '.isp-gallery__remove', function () { $(this).closest('li').remove(); sync(); });
    box.on('click', '[data-isp-gallery-add]', function (e) {
      e.preventDefault();
      if (!frame) {
        frame = wp.media({ title: 'Add project photos', library: { type: 'image' }, multiple: 'add', button: { text: 'Add to project' } });
        frame.on('select', function () {
          var have = input.val().split(',');
          frame.state().get('selection').each(function (m) {
            var att = m.toJSON();
            if (have.indexOf(String(att.id)) === -1) list.append(item(att));
          });
          sync();
        });
      }
      frame.open();
    });
  });

  /* ---------- single photo picker (About portrait) ---------- */
  $('[data-isp-image]').each(function () {
    var box = $(this), input = box.find('input[type=hidden]'), preview = box.find('.isp-image__preview'), remove = box.find('[data-isp-image-remove]'), frame;
    box.on('click', '[data-isp-image-pick]', function (e) {
      e.preventDefault();
      if (!frame) {
        frame = wp.media({ title: 'Choose a photo', library: { type: 'image' }, multiple: false, button: { text: 'Use this photo' } });
        frame.on('select', function () {
          var att = frame.state().get('selection').first().toJSON(), sizes = att.sizes || {};
          input.val(att.id);
          preview.html($('<img alt="">').attr('src', (sizes.thumbnail || sizes.full || {}).url || att.url));
          remove.prop('hidden', false);
        });
      }
      frame.open();
    });
    remove.on('click', function () { input.val('0'); preview.empty(); remove.prop('hidden', true); });
  });

  /* ---------- Projects list: drag rows to reorder ---------- */
  var rows = $('body.post-type-isp_project.edit-php #the-list');
  if (rows.length && rows.find('.isp-drag').length && window.ISPAdmin) {
    rows.sortable({
      items: 'tr', handle: '.isp-drag', axis: 'y', cursor: 'grabbing',
      helper: function (e, tr) {
        var cells = tr.children();
        return tr.clone().children().each(function (i) { $(this).width(cells.eq(i).outerWidth()); }).end();
      },
      update: function () {
        var ids = rows.children('tr').map(function () { return this.id.replace('post-', ''); }).get();
        rows.addClass('isp-saving');
        $.post(window.ajaxurl, { action: 'isp_reorder', nonce: ISPAdmin.reorderNonce, ids: ids })
          .always(function () { rows.removeClass('isp-saving'); })
          .fail(function () { window.alert('The new order could not be saved. Please reload the page and try again.'); });
      }
    });
  }
})(jQuery);
