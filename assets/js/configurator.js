(function($) {
    'use strict';

    if (!window.wlcConfig) return;

    var config = window.wlcConfig;
    var pricing = config.pricing || {};
    var labels = config.labels || {};
    var ironmongeryOptions = config.ironmongery || [];
    var treatmentOptions = config.treatments || [];
    var postOptions = config.posts || [];

    var basePrice = 0;

    function populateSelect(el, options, labelMap) {
        var placeholder = el.find('option:first').text() || '-- Select --';
        el.html('<option value="">' + placeholder + '</option>');
        $.each(options, function(i, val) {
            var label = (labelMap && labelMap[val]) ? labelMap[val] : val;
            el.append('<option value="' + val + '">' + label + '</option>');
        });
    }

    function populateAddOnSelect(el, items) {
        var placeholder = el.find('option:first').text() || 'None';
        el.html('<option value="">' + placeholder + '</option>');
        $.each(items, function(i, item) {
            el.append('<option value="' + item.name + '" data-price="' + item.price + '">' + item.name + ' - £' + parseFloat(item.price).toFixed(2) + '</option>');
        });
    }

    function showField(id) { $(id).slideDown(200); }
    function hideField(id) { $(id).slideUp(200); $(id).find('select').val(''); }

    function getSelectedAddOnPrice(selectId) {
        var sel = $(selectId).find(':selected');
        return sel.length && sel.data('price') ? parseFloat(sel.data('price')) : 0;
    }

    function updateTotal() {
        if (basePrice <= 0) return;

        var ironPrice = getSelectedAddOnPrice('#wlc-ironmongery');
        var treatPrice = getSelectedAddOnPrice('#wlc-treatment');
        var postPrice = getSelectedAddOnPrice('#wlc-posts');
        var total = basePrice + ironPrice + treatPrice + postPrice;

        var formatted = '£' + total.toFixed(2);
        $('#wlc-total-amount').text(formatted);
        $('#wlc-configured-price').val(total);

        var material = $('#wlc-material').val();
        var width = $('#wlc-width').val();
        var height = $('#wlc-height').val();
        var typeLabel = labels[config.product_type] || config.product_type;
        var styleLabel = labels[config.style] || config.style;
        var matLabel = labels[material] || material;
        var summary = typeLabel + ' - ' + styleLabel + ' - ' + matLabel + ' | ' + width + ' x ' + height;

        var ironSel = $('#wlc-ironmongery').val();
        if (ironSel) summary += ' | Ironmongery: ' + ironSel;
        var treatSel = $('#wlc-treatment').val();
        if (treatSel) summary += ' | Treatment: ' + treatSel;
        var postSel = $('#wlc-posts').val();
        if (postSel) summary += ' | Posts: ' + postSel;

        $('#wlc-config-summary').val(summary);

        if (ironSel) {
            $('#wlc-step-ironmongery-status').text('£' + ironPrice.toFixed(2));
        } else {
            $('#wlc-step-ironmongery-status').text('Optional');
        }
        if (treatSel) {
            $('#wlc-step-treatment-status').text('£' + treatPrice.toFixed(2));
        } else {
            $('#wlc-step-treatment-status').text('Optional');
        }
        if (postSel) {
            $('#wlc-step-posts-status').text('£' + postPrice.toFixed(2));
        } else {
            $('#wlc-step-posts-status').text('Optional');
        }
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
        basePrice = 0;
        updateButtonState(false);

        hideAddOnSteps();

        if (!material || !width || !height) return;

        var matData = pricing[material];
        if (!matData) return;

        var wIdx = matData.widths.indexOf(width);
        var hIdx = matData.heights.indexOf(height);
        if (wIdx === -1 || hIdx === -1) return;

        var price = matData.prices[hIdx][wIdx];

        if (price !== null && price !== undefined) {
            basePrice = parseFloat(price);
            $('#wlc-price-amount').text('£' + basePrice.toFixed(2));
            $('#wlc-inline-price').fadeIn(200);

            showAddOnSteps();
            updateTotal();

            $('#wlc-total').fadeIn(200);
            updateButtonState(true);

            var matLabel = labels[material] || material;
            $('#wlc-step-size-status').text(matLabel + ' / ' + width + ' x ' + height);
        } else {
            $('#wlc-no-price').fadeIn(200);
            updateButtonState(false);
        }
    }

    function showAddOnSteps() {
        if (ironmongeryOptions.length > 0) $('#wlc-step-ironmongery').slideDown(200);
        if (treatmentOptions.length > 0) $('#wlc-step-treatment').slideDown(200);
        if (postOptions.length > 0) $('#wlc-step-posts').slideDown(200);
    }

    function hideAddOnSteps() {
        $('#wlc-step-ironmongery').slideUp(200);
        $('#wlc-step-treatment').slideUp(200);
        $('#wlc-step-posts').slideUp(200);
        $('#wlc-ironmongery').val('');
        $('#wlc-treatment').val('');
        $('#wlc-posts').val('');
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
        var observer = new MutationObserver(function() { removeDuplicateConfigurators(); });
        observer.observe(document.body, { childList: true, subtree: true });

        updateButtonState(false);

        var materials = Object.keys(pricing);
        populateSelect($('#wlc-material'), materials, labels);

        if (ironmongeryOptions.length > 0) {
            populateAddOnSelect($('#wlc-ironmongery'), ironmongeryOptions);
        }
        if (treatmentOptions.length > 0) {
            populateAddOnSelect($('#wlc-treatment'), treatmentOptions);
        }
        if (postOptions.length > 0) {
            populateAddOnSelect($('#wlc-posts'), postOptions);
        }

        $('#wlc-material').on('change', function() {
            hideField('#wlc-width-field');
            hideField('#wlc-height-field');
            $('#wlc-inline-price').hide();
            $('#wlc-total').hide();
            $('#wlc-no-price').hide();
            $('#wlc-step-size-status').text('');
            basePrice = 0;
            updateButtonState(false);
            hideAddOnSteps();

            var material = $(this).val();
            if (!material || !pricing[material]) return;

            populateSelect($('#wlc-width'), pricing[material].widths);
            showField('#wlc-width-field');
        });

        $('#wlc-width').on('change', function() {
            hideField('#wlc-height-field');
            $('#wlc-inline-price').hide();
            $('#wlc-total').hide();
            $('#wlc-no-price').hide();
            basePrice = 0;
            updateButtonState(false);
            hideAddOnSteps();

            var material = $('#wlc-material').val();
            if (!material || !$(this).val() || !pricing[material]) return;

            populateSelect($('#wlc-height'), pricing[material].heights);
            showField('#wlc-height-field');
        });

        $('#wlc-height').on('change', function() { updatePrice(); });
        $('#wlc-ironmongery, #wlc-treatment, #wlc-posts').on('change', function() { updateTotal(); });
    });

})(jQuery);
