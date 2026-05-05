(function($) {
    'use strict';

    if (!window.wlcConfig) return;

    var config = window.wlcConfig;
    var pricing = config.pricing || {};
    var labels = config.labels || {};

    function populateSelect(el, options, labelMap) {
        var placeholder = el.find('option:first').text() || '-- Select --';
        el.html('<option value="">' + placeholder + '</option>');
        $.each(options, function(i, val) {
            var label = (labelMap && labelMap[val]) ? labelMap[val] : val;
            el.append('<option value="' + val + '">' + label + '</option>');
        });
    }

    function showField(id) {
        $(id).slideDown(200);
    }

    function hideField(id) {
        $(id).slideUp(200);
        $(id).find('select').val('');
    }

    function updatePrice() {
        var material = $('#wlc-material').val();
        var width = $('#wlc-width').val();
        var height = $('#wlc-height').val();

        $('#wlc-inline-price').hide();
        $('#wlc-total').hide();
        $('#wlc-no-price').hide();
        $('#wlc-configured-price').val('');
        $('#wlc-config-summary').val('');
        updateButtonState(false);

        if (!material || !width || !height) return;

        var matData = pricing[material];
        if (!matData) return;

        var wIdx = matData.widths.indexOf(width);
        var hIdx = matData.heights.indexOf(height);

        if (wIdx === -1 || hIdx === -1) return;

        var price = matData.prices[hIdx][wIdx];

        if (price !== null && price !== undefined) {
            var formatted = '£' + parseFloat(price).toFixed(2);
            $('#wlc-price-amount').text(formatted);
            $('#wlc-inline-price').fadeIn(200);
            $('#wlc-total-amount').text(formatted);
            $('#wlc-total').fadeIn(200);
            $('#wlc-configured-price').val(price);

            var typeLabel = labels[config.product_type] || config.product_type;
            var styleLabel = labels[config.style] || config.style;
            var matLabel = labels[material] || material;
            var summary = typeLabel + ' - ' + styleLabel + ' - ' + matLabel + ' | ' + width + ' x ' + height;
            $('#wlc-config-summary').val(summary);

            updateButtonState(true);
            $('#wlc-step-1-status').text(matLabel + ' / ' + width + ' x ' + height);
        } else {
            $('#wlc-no-price').fadeIn(200);
            updateButtonState(false);
        }
    }

    function updateButtonState(enabled) {
        var btn = $('form.cart .single_add_to_cart_button');
        if (enabled) {
            btn.prop('disabled', false).removeClass('disabled');
        } else {
            btn.prop('disabled', true).addClass('disabled');
        }
    }

    $(document).ready(function() {
        $('[id="wlc-configurator"]').first().attr('data-wlc-original', 'true');
        function removeDuplicateConfigurators() {
            $('[id="wlc-configurator"]').not('[data-wlc-original]').remove();
        }
        removeDuplicateConfigurators();
        setTimeout(removeDuplicateConfigurators, 300);
        setTimeout(removeDuplicateConfigurators, 1000);
        setTimeout(removeDuplicateConfigurators, 3000);
        var observer = new MutationObserver(function() {
            removeDuplicateConfigurators();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        updateButtonState(false);

        var materials = Object.keys(pricing);
        populateSelect($('#wlc-material'), materials, labels);

        $('#wlc-material').on('change', function() {
            var material = $(this).val();

            hideField('#wlc-width-field');
            hideField('#wlc-height-field');
            $('#wlc-inline-price').hide();
            $('#wlc-total').hide();
            $('#wlc-no-price').hide();
            $('#wlc-step-1-status').text('');
            updateButtonState(false);

            if (!material || !pricing[material]) return;

            var matData = pricing[material];
            populateSelect($('#wlc-width'), matData.widths);
            showField('#wlc-width-field');
        });

        $('#wlc-width').on('change', function() {
            var material = $('#wlc-material').val();

            hideField('#wlc-height-field');
            $('#wlc-inline-price').hide();
            $('#wlc-total').hide();
            $('#wlc-no-price').hide();
            updateButtonState(false);

            if (!material || !$(this).val()) return;
            if (!pricing[material]) return;

            var matData = pricing[material];
            populateSelect($('#wlc-height'), matData.heights);
            showField('#wlc-height-field');

            updatePrice();
        });

        $('#wlc-height').on('change', function() {
            updatePrice();
        });

        $('#wlc-step-ironmongery, #wlc-step-treatment').show();
    });

})(jQuery);
