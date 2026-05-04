<?php
/**
 * Plugin Name: Woodline Product Configurator
 * Description: Custom product configurator for Woodline Timber Shop - gates and garage doors with width/height/material pricing
 * Version: 1.0.0
 * Author: Woodline Timber
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

define('WLC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WLC_PLUGIN_URL', plugin_dir_url(__FILE__));

class Woodline_Configurator {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'render_configurator']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_configuration'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_item_price']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
        add_action('wp_ajax_wlc_get_price', [$this, 'ajax_get_price']);
        add_action('wp_ajax_nopriv_wlc_get_price', [$this, 'ajax_get_price']);
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'change_button_text']);

        // Hide default price on single product
        add_filter('woocommerce_get_price_html', [$this, 'hide_default_price'], 10, 2);
    }

    public function enqueue_assets() {
        if (!is_product()) return;

        wp_enqueue_style('wlc-configurator', WLC_PLUGIN_URL . 'assets/css/configurator.css', [], '1.0.0');
        wp_enqueue_script('wlc-configurator', WLC_PLUGIN_URL . 'assets/js/configurator.js', ['jquery'], '1.0.0', true);
        wp_localize_script('wlc-configurator', 'wlc_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wlc_nonce'),
        ]);
    }

    public function hide_default_price($price_html, $product) {
        if (is_product() && $product->get_id() === get_queried_object_id()) {
            return '';
        }
        return $price_html;
    }

    public function change_button_text($text) {
        return 'Add to Cart';
    }

    public function get_pricing_data() {
        return include WLC_PLUGIN_DIR . 'includes/pricing-data.php';
    }

    public function render_configurator() {
        static $rendered = false;
        if ($rendered) return;
        $rendered = true;

        $pricing = $this->get_pricing_data();
        $pricing_json = wp_json_encode($pricing);
        ?>
        <div id="wlc-configurator">
            <h3 class="wlc-title">Configure Your Order</h3>

            <div class="wlc-form">
                <div class="wlc-field">
                    <label for="wlc-product-type">Product Type</label>
                    <select id="wlc-product-type" name="wlc_product_type">
                        <option value="">-- Select --</option>
                        <option value="gates">Gates</option>
                        <option value="garage_doors">Garage Doors</option>
                    </select>
                </div>

                <div class="wlc-field" id="wlc-style-field" style="display:none;">
                    <label for="wlc-style">Style</label>
                    <select id="wlc-style" name="wlc_style">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div class="wlc-field" id="wlc-material-field" style="display:none;">
                    <label for="wlc-material">Material</label>
                    <select id="wlc-material" name="wlc_material">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div class="wlc-field" id="wlc-width-field" style="display:none;">
                    <label for="wlc-width">Width</label>
                    <select id="wlc-width" name="wlc_width">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div class="wlc-field" id="wlc-height-field" style="display:none;">
                    <label for="wlc-height">Height</label>
                    <select id="wlc-height" name="wlc_height">
                        <option value="">-- Select --</option>
                    </select>
                </div>
            </div>

            <div class="wlc-price-display" id="wlc-price-display" style="display:none;">
                <span class="wlc-price-label">Your Price:</span>
                <span class="wlc-price-amount" id="wlc-price-amount">&pound;0.00</span>
                <span class="wlc-price-vat">inc. VAT</span>
            </div>

            <div class="wlc-no-price" id="wlc-no-price" style="display:none;">
                <p>Price not available for this combination. Please call <strong>0800 3334445</strong> for a quote.</p>
            </div>

            <input type="hidden" id="wlc-configured-price" name="wlc_configured_price" value="">
            <input type="hidden" id="wlc-config-summary" name="wlc_config_summary" value="">
        </div>

        <script type="text/javascript">
            var wlcPricingData = <?php echo $pricing_json; ?>;
        </script>
        <?php
    }

    public function validate_configuration($passed, $product_id, $quantity) {
        if (empty($_POST['wlc_product_type'])) {
            wc_add_notice('Please select a product type.', 'error');
            return false;
        }
        if (empty($_POST['wlc_style'])) {
            wc_add_notice('Please select a style.', 'error');
            return false;
        }
        if (empty($_POST['wlc_material'])) {
            wc_add_notice('Please select a material.', 'error');
            return false;
        }
        if (empty($_POST['wlc_width'])) {
            wc_add_notice('Please select a width.', 'error');
            return false;
        }
        if (empty($_POST['wlc_height'])) {
            wc_add_notice('Please select a height.', 'error');
            return false;
        }
        if (empty($_POST['wlc_configured_price']) || floatval($_POST['wlc_configured_price']) <= 0) {
            wc_add_notice('Price could not be determined for this configuration. Please contact us.', 'error');
            return false;
        }
        return $passed;
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (!empty($_POST['wlc_product_type'])) {
            $cart_item_data['wlc_config'] = [
                'product_type' => sanitize_text_field($_POST['wlc_product_type']),
                'style' => sanitize_text_field($_POST['wlc_style']),
                'material' => sanitize_text_field($_POST['wlc_material']),
                'width' => sanitize_text_field($_POST['wlc_width']),
                'height' => sanitize_text_field($_POST['wlc_height']),
                'price' => floatval($_POST['wlc_configured_price']),
            ];
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (!empty($cart_item['wlc_config'])) {
            $config = $cart_item['wlc_config'];
            $labels = [
                'gates' => 'Gates',
                'garage_doors' => 'Garage Doors',
                'flat_top' => 'Flat Top',
                'swan_neck' => 'Swan Neck',
                'pine' => 'Pine',
                'oak' => 'Oak',
                'iroko' => 'Iroko',
                'acoya' => 'Acoya',
            ];

            $item_data[] = ['key' => 'Type', 'value' => $labels[$config['product_type']] ?? $config['product_type']];
            $item_data[] = ['key' => 'Style', 'value' => $labels[$config['style']] ?? $config['style']];
            $item_data[] = ['key' => 'Material', 'value' => $labels[$config['material']] ?? $config['material']];
            $item_data[] = ['key' => 'Width', 'value' => $config['width']];
            $item_data[] = ['key' => 'Height', 'value' => $config['height']];
        }
        return $item_data;
    }

    public function set_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        foreach ($cart->get_cart() as $cart_item) {
            if (!empty($cart_item['wlc_config']['price'])) {
                $cart_item['data']->set_price($cart_item['wlc_config']['price']);
            }
        }
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (!empty($values['wlc_config'])) {
            $config = $values['wlc_config'];
            $labels = [
                'gates' => 'Gates',
                'garage_doors' => 'Garage Doors',
                'flat_top' => 'Flat Top',
                'swan_neck' => 'Swan Neck',
                'pine' => 'Pine',
                'oak' => 'Oak',
                'iroko' => 'Iroko',
                'acoya' => 'Acoya',
            ];

            $item->add_meta_data('Type', $labels[$config['product_type']] ?? $config['product_type']);
            $item->add_meta_data('Style', $labels[$config['style']] ?? $config['style']);
            $item->add_meta_data('Material', $labels[$config['material']] ?? $config['material']);
            $item->add_meta_data('Width', $config['width']);
            $item->add_meta_data('Height', $config['height']);
        }
    }

    public function ajax_get_price() {
        check_ajax_referer('wlc_nonce', 'nonce');

        $product_type = sanitize_text_field($_POST['product_type'] ?? '');
        $style = sanitize_text_field($_POST['style'] ?? '');
        $material = sanitize_text_field($_POST['material'] ?? '');
        $width = sanitize_text_field($_POST['width'] ?? '');
        $height = sanitize_text_field($_POST['height'] ?? '');

        $pricing = $this->get_pricing_data();

        $price = null;
        if (isset($pricing[$product_type][$style][$material])) {
            $data = $pricing[$product_type][$style][$material];
            $w_idx = array_search($width, $data['widths']);
            $h_idx = array_search($height, $data['heights']);
            if ($w_idx !== false && $h_idx !== false && isset($data['prices'][$h_idx][$w_idx])) {
                $price = $data['prices'][$h_idx][$w_idx];
            }
        }

        wp_send_json_success(['price' => $price]);
    }
}

Woodline_Configurator::instance();
