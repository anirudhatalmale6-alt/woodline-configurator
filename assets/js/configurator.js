(function($) {
    'use strict';

    var pricing = window.wlcPricingData || {};

    var labels = {
        flat_top: 'Flat Top',
        swan_neck: 'Swan Neck',
        pine: 'Pine',
        oak: 'Oak',
        iroko: 'Iroko',
        acoya: 'Acoya',
        gates: 'Gates',
        garage_doors: 'Garage Doors'
    };

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

    function showStep(id) {
        $(id).slideDown(250);
    }

    function hideStep(id) {
        $(id).slideUp(250);
        $(id).find('select').val('');
    }

    function updateStepStatus(stepNum, text) {
        $('#wlc-step-' + stepNum + '-status').text(text);
    }

    function updatePrice() {
        var type = $('#wlc-product-type').val();
        var style = $('#wlc-style').val();
        var material = $('#wlc-material').val();
        var width = $('#wlc-width').val();
        var height = $('#wlc-height').val();

        $('#wlc-price-display').hide();
        $('#wlc-no-price').hide();
        $('#wlc-configured-price').val('');
        updateButtonState(false);

        if (!type || !style || !material || !width || !height) {
            hideStep('#wlc-step-3');
            return;
        }

        showStep('#wlc-step-3');

        var data = pricing[type];
        if (!data || !data[style] || !data[style][material]) return;

        var matData = data[style][material];
        var wIdx = matData.widths.indexOf(width);
        var hIdx = matData.heights.indexOf(height);

        if (wIdx === -1 || hIdx === -1) return;

        var price = matData.prices[hIdx][wIdx];

        if (price !== null && price !== undefined) {
            $('#wlc-price-amount').text('£' + parseFloat(price).toFixed(2));
            $('#wlc-price-display').fadeIn(200);
            $('#wlc-configured-price').val(price);

            var summary = (labels[type] || type) + ' - ' + (labels[style] || style) + ' - ' + (labels[material] || material) + ' | ' + width + ' x ' + height;
            $('#wlc-config-summary').val(summary);
            updateButtonState(true);
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
        // Remove duplicate configurators (theme sticky bar clones)
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

        $('#wlc-product-type').on('change', function() {
            var type = $(this).val();

            hideField('#wlc-style-field');
            hideField('#wlc-material-field');
            hideStep('#wlc-step-2');
            hideStep('#wlc-step-3');
            updateStepStatus(1, '');

            if (!type || !pricing[type]) return;

            var styles = Object.keys(pricing[type]);
            if (type === 'garage_doors') {
                populateSelect($('#wlc-style'), styles, labels);
                $('#wlc-style').val('flat_top');
                showField('#wlc-style-field');
                var materials = Object.keys(pricing[type]['flat_top']);
                populateSelect($('#wlc-material'), materials, labels);
                showField('#wlc-material-field');
            } else {
                populateSelect($('#wlc-style'), styles, labels);
                showField('#wlc-style-field');
            }

            updatePrice();
        });

        $('#wlc-style').on('change', function() {
            var type = $('#wlc-product-type').val();
            var style = $(this).val();

            hideField('#wlc-material-field');
            hideStep('#wlc-step-2');
            hideStep('#wlc-step-3');

            if (!type || !style || !pricing[type] || !pricing[type][style]) return;

            var materials = Object.keys(pricing[type][style]);
            populateSelect($('#wlc-material'), materials, labels);
            showField('#wlc-material-field');

            updatePrice();
        });

        $('#wlc-material').on('change', function() {
            var type = $('#wlc-product-type').val();
            var style = $('#wlc-style').val();
            var material = $(this).val();

            hideField('#wlc-height-field');
            hideStep('#wlc-step-3');

            if (!type || !style || !material) {
                hideStep('#wlc-step-2');
                return;
            }
            if (!pricing[type] || !pricing[type][style] || !pricing[type][style][material]) return;

            var matData = pricing[type][style][material];
            populateSelect($('#wlc-width'), matData.widths);
            showStep('#wlc-step-2');

            updateStepStatus(1, (labels[type] || type) + ' / ' + (labels[style] || style) + ' / ' + (labels[material] || material));

            updatePrice();
        });

        $('#wlc-width').on('change', function() {
            var type = $('#wlc-product-type').val();
            var style = $('#wlc-style').val();
            var material = $('#wlc-material').val();

            hideStep('#wlc-step-3');

            if (!type || !style || !material) return;
            if (!pricing[type] || !pricing[type][style] || !pricing[type][style][material]) return;

            var matData = pricing[type][style][material];
            populateSelect($('#wlc-height'), matData.heights);
            showField('#wlc-height-field');

            updatePrice();
        });

        $('#wlc-height').on('change', function() {
            updatePrice();
        });
    });

})(jQuery);
