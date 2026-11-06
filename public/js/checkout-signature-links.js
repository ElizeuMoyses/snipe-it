(function ($) {
    'use strict';

    $(function () {
        var modal = $('#checkout-signature-modal');
        if (!modal.length) return;

        modal.on('click', '.checkout-copy-link', function () {
            var button = this;
            var item = $(button).closest('.checkout-signature-item');
            var input = item.find('.checkout-signature-link')[0];

            function selectLink() {
                input.focus();
                input.select();
                input.setSelectionRange(0, input.value.length);
            }

            function copied() {
                item.find('.checkout-link-status').removeClass('text-danger').addClass('text-success')
                    .text(modal.attr('data-copied'));
                button.focus();
            }

            function fallbackCopy() {
                selectLink();
                var succeeded = false;
                try {
                    succeeded = document.execCommand('copy');
                } catch (error) {
                    // Keep the selection available for manual copying.
                }
                if (succeeded) {
                    copied();
                } else {
                    item.find('.checkout-link-status').removeClass('text-success').addClass('text-danger')
                        .text(modal.attr('data-copy-error'));
                }
            }

            if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(copied).catch(fallbackCopy);
            } else {
                fallbackCopy();
            }
        });

        modal.one('shown.bs.modal', function () {
            modal.find('.checkout-copy-link').first().trigger('focus');
        });
        modal.modal('show');
    });
})(jQuery);
