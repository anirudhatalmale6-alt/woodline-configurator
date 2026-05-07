<?php
/**
 * Plugin Name: Woodline Product Configurator
 * Description: Custom product configurator for Woodline Timber Shop - gates and garage doors with width/height/material pricing
 * Version: 3.0.0
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

    private $labels = [
        'driveway_gates' => 'Driveway Gates',
        'single_gates' => 'Single Gates',
        'garage_doors' => 'Garage Doors',
        'flat_top' => 'Flat Top',
        'swan_neck' => 'Swan Neck',
        'bow_top' => 'Bow Top',
        'swan_neck_palisade' => 'Swan Neck Palisade',
        'palisade_flat_top' => 'Palisade Flat Top',
        'palisade_bow_top' => 'Palisade Bow Top',
        'full_board' => 'Full Board',
        'curved_head' => 'Curved Head',
        'single_pane' => 'Single Pane',
        'pine' => 'Pine',
        'oak' => 'Oak',
        'iroko' => 'Iroko',
        'acoya' => 'Acoya',
    ];

    private $product_types = [
        'driveway_gates' => 'Driveway Gates',
        'single_gates' => 'Single Gates',
        'garage_doors' => 'Garage Doors',
    ];

    private $styles = [
        'driveway_gates' => [
            'flat_top' => 'Flat Top',
            'swan_neck' => 'Swan Neck',
            'bow_top' => 'Bow Top',
            'swan_neck_palisade' => 'Swan Neck Palisade',
        ],
        'single_gates' => [
            'flat_top' => 'Flat Top',
            'bow_top' => 'Bow Top',
            'palisade_flat_top' => 'Palisade Flat Top',
            'palisade_bow_top' => 'Palisade Bow Top',
        ],
        'garage_doors' => [
            'full_board' => 'Full Board',
            'curved_head' => 'Curved Head',
            'single_pane' => 'Single Pane',
        ],
    ];

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'render_configurator']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_configuration'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_item_price']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'change_button_text']);
        add_filter('woocommerce_get_price_html', [$this, 'hide_default_price'], 10, 2);
        add_filter('single_product_archive_thumbnail_size', [$this, 'use_full_thumbnail']);

        add_action('add_meta_boxes', [$this, 'add_product_meta_box']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);

    }

    public function enqueue_assets() {
        if (is_product() || is_shop() || is_product_category() || is_product_tag()) {
            wp_enqueue_style('wlc-configurator', WLC_PLUGIN_URL . 'assets/css/configurator.css', [], '3.5.0');
        }
        if (is_product()) {
            wp_enqueue_script('wlc-configurator', WLC_PLUGIN_URL . 'assets/js/configurator.js', ['jquery'], '3.1.0', true);
        }
    }

    public function use_full_thumbnail() {
        return 'large';
    }

    public function hide_default_price($price_html, $product) {
        $product_type = get_post_meta($product->get_id(), '_wlc_product_type', true);
        if (!$product_type) return $price_html;

        if (is_product() && $product->get_id() === get_queried_object_id()) {
            return '';
        }

        $style = get_post_meta($product->get_id(), '_wlc_style', true);
        if ($product_type && $style) {
            $min = $this->get_min_price($product_type, $style);
            if ($min > 0) {
                return '<span class="wlc-from-price">From <strong>&pound;' . number_format($min, 2) . '</strong></span>';
            }
        }
        return $price_html;
    }

    private function get_min_price($product_type, $style) {
        $pricing = $this->get_pricing_data();
        if (!isset($pricing[$product_type][$style])) return 0;
        $min = PHP_INT_MAX;
        foreach ($pricing[$product_type][$style] as $material => $data) {
            if (!isset($data['prices'])) continue;
            foreach ($data['prices'] as $row) {
                foreach ($row as $price) {
                    if ($price > 0 && $price < $min) {
                        $min = $price;
                    }
                }
            }
        }
        return $min < PHP_INT_MAX ? $min : 0;
    }

    public function change_button_text($text) {
        global $product;
        if ($product && get_post_meta($product->get_id(), '_wlc_product_type', true)) {
            return 'Add to Cart';
        }
        return $text;
    }

    public function get_pricing_data() {
        return include WLC_PLUGIN_DIR . 'includes/pricing-data.php';
    }

    private function get_gate_class($product_type) {
        if ($product_type === 'driveway_gates') return 'double';
        if ($product_type === 'single_gates') return 'single';
        return '';
    }

    public function add_product_meta_box() {
        add_meta_box('wlc_config_meta', 'Woodline Configurator Settings', [$this, 'render_meta_box'], 'product', 'side', 'default');
    }

    public function render_meta_box($post) {
        $product_type = get_post_meta($post->ID, '_wlc_product_type', true);
        $style = get_post_meta($post->ID, '_wlc_style', true);
        wp_nonce_field('wlc_save_meta', 'wlc_meta_nonce');
        ?>
        <p>
            <label><strong>Product Type:</strong></label><br>
            <select name="_wlc_product_type" style="width:100%">
                <option value="">None (no configurator)</option>
                <?php foreach ($this->product_types as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($product_type, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><strong>Style:</strong></label><br>
            <select name="_wlc_style" style="width:100%">
                <option value="">None</option>
                <?php foreach ($this->styles as $type => $type_styles): ?>
                    <?php foreach ($type_styles as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($style, $key); ?>><?php echo esc_html($label); ?> (<?php echo esc_html($this->product_types[$type]); ?>)</option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function save_product_meta($post_id) {
        if (!isset($_POST['wlc_meta_nonce']) || !wp_verify_nonce($_POST['wlc_meta_nonce'], 'wlc_save_meta')) return;
        update_post_meta($post_id, '_wlc_product_type', sanitize_text_field($_POST['_wlc_product_type'] ?? ''));
        update_post_meta($post_id, '_wlc_style', sanitize_text_field($_POST['_wlc_style'] ?? ''));
    }

    public function render_configurator() {
        static $rendered = false;
        if ($rendered) return;
        $rendered = true;

        global $product;
        $product_id = $product->get_id();
        $product_type = get_post_meta($product_id, '_wlc_product_type', true);
        $style = get_post_meta($product_id, '_wlc_style', true);

        if (!$product_type) return;

        $pricing = $this->get_pricing_data();
        $gate_class = $this->get_gate_class($product_type);
        $show_ironmongery = ($gate_class !== '');
        $show_treatment = ($gate_class !== '');

        $available_data = [];
        if (isset($pricing[$product_type][$style])) {
            $available_data = $pricing[$product_type][$style];
        }

        $ironmongery_options = [];
        if ($show_ironmongery && isset($pricing['ironmongery'][$gate_class])) {
            $ironmongery_options = $pricing['ironmongery'][$gate_class];
        }

        $treatment_options = [];
        if ($show_treatment && isset($pricing['treatments'][$gate_class])) {
            $treatment_options = $pricing['treatments'][$gate_class];
        }

        $post_options = $pricing['posts'] ?? [];

        $config = [
            'product_type' => $product_type,
            'style' => $style,
            'gate_class' => $gate_class,
            'pricing' => $available_data,
            'ironmongery' => $ironmongery_options,
            'treatments' => $treatment_options,
            'posts' => $post_options,
            'labels' => $this->labels,
        ];
        $config_json = wp_json_encode($config);

        $step_num = 1;
        ?>
        <div id="wlc-configurator">
            <input type="hidden" name="wlc_product_type" value="<?php echo esc_attr($product_type); ?>">
            <input type="hidden" name="wlc_style" value="<?php echo esc_attr($style); ?>">

            <div class="wlc-step" id="wlc-step-size">
                <div class="wlc-step-header">
                    <span class="wlc-step-number"><?php echo $step_num++; ?></span>
                    <span class="wlc-step-title">Size &amp; Wood Type</span>
                    <span class="wlc-step-status" id="wlc-step-size-status"></span>
                </div>
                <div class="wlc-step-body">
                    <div class="wlc-row" id="wlc-material-field">
                        <label for="wlc-material">Wood Type</label>
                        <select id="wlc-material" name="wlc_material">
                            <option value="">Select wood type...</option>
                        </select>
                    </div>
                    <div class="wlc-row" id="wlc-width-field" style="display:none;">
                        <label for="wlc-width">Width</label>
                        <select id="wlc-width" name="wlc_width">
                            <option value="">Select width...</option>
                        </select>
                    </div>
                    <div class="wlc-row" id="wlc-height-field" style="display:none;">
                        <label for="wlc-height">Height</label>
                        <select id="wlc-height" name="wlc_height">
                            <option value="">Select height...</option>
                        </select>
                    </div>
                    <div class="wlc-inline-price" id="wlc-inline-price" style="display:none;">
                        <span class="wlc-price-text">Gate price:</span>
                        <span class="wlc-price-amount" id="wlc-price-amount">&pound;0.00</span>
                    </div>
                </div>
            </div>

            <?php if ($show_ironmongery): ?>
            <div class="wlc-step" id="wlc-step-ironmongery" style="display:none;">
                <div class="wlc-step-header">
                    <span class="wlc-step-number"><?php echo $step_num++; ?></span>
                    <span class="wlc-step-title">Ironmongery</span>
                    <span class="wlc-step-status" id="wlc-step-ironmongery-status">Optional</span>
                </div>
                <div class="wlc-step-body">
                    <div class="wlc-row">
                        <label for="wlc-ironmongery">Select Kit</label>
                        <select id="wlc-ironmongery" name="wlc_ironmongery">
                            <option value="">None (no ironmongery)</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($show_treatment): ?>
            <div class="wlc-step" id="wlc-step-treatment" style="display:none;">
                <div class="wlc-step-header">
                    <span class="wlc-step-number"><?php echo $step_num++; ?></span>
                    <span class="wlc-step-title">Choose a Treatment</span>
                    <span class="wlc-step-status" id="wlc-step-treatment-status">Optional</span>
                </div>
                <div class="wlc-step-body">
                    <div class="wlc-row">
                        <label for="wlc-treatment">Treatment</label>
                        <select id="wlc-treatment" name="wlc_treatment">
                            <option value="">None (no treatment)</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($show_ironmongery): ?>
            <div class="wlc-step" id="wlc-step-posts" style="display:none;">
                <div class="wlc-step-header">
                    <span class="wlc-step-number"><?php echo $step_num++; ?></span>
                    <span class="wlc-step-title">Posts</span>
                    <span class="wlc-step-status" id="wlc-step-posts-status">Optional</span>
                </div>
                <div class="wlc-step-body">
                    <div class="wlc-row">
                        <label for="wlc-posts">Select Posts</label>
                        <select id="wlc-posts" name="wlc_posts">
                            <option value="">None (no posts)</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="wlc-total" id="wlc-total" style="display:none;">
                <span class="wlc-total-text">Total price for your customised product:</span>
                <span class="wlc-total-amount" id="wlc-total-amount">&pound;0.00</span>
                <span class="wlc-total-vat">inc. VAT</span>
            </div>

            <div class="wlc-no-price" id="wlc-no-price" style="display:none;">
                <p>Price on request for this combination. Please call <strong>0800 3334445</strong> for a quote.</p>
            </div>

            <input type="hidden" id="wlc-configured-price" name="wlc_configured_price" value="">
            <input type="hidden" id="wlc-config-summary" name="wlc_config_summary" value="">
        </div>

        <script type="text/javascript">
            var wlcConfig = <?php echo $config_json; ?>;
        </script>
        <?php
    }

    public function validate_configuration($passed, $product_id, $quantity) {
        $product_type = get_post_meta($product_id, '_wlc_product_type', true);
        if (!$product_type) return $passed;

        if (empty($_POST['wlc_material'])) {
            wc_add_notice('Please select a wood type.', 'error');
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
            wc_add_notice('Price could not be determined. Please contact us.', 'error');
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
                'ironmongery' => sanitize_text_field($_POST['wlc_ironmongery'] ?? ''),
                'treatment' => sanitize_text_field($_POST['wlc_treatment'] ?? ''),
                'posts' => sanitize_text_field($_POST['wlc_posts'] ?? ''),
                'price' => floatval($_POST['wlc_configured_price']),
            ];
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (!empty($cart_item['wlc_config'])) {
            $config = $cart_item['wlc_config'];
            $item_data[] = ['key' => 'Wood Type', 'value' => $this->labels[$config['material']] ?? $config['material']];
            $item_data[] = ['key' => 'Width', 'value' => $config['width']];
            $item_data[] = ['key' => 'Height', 'value' => $config['height']];
            if (!empty($config['ironmongery'])) {
                $item_data[] = ['key' => 'Ironmongery', 'value' => $config['ironmongery']];
            }
            if (!empty($config['treatment'])) {
                $item_data[] = ['key' => 'Treatment', 'value' => $config['treatment']];
            }
            if (!empty($config['posts'])) {
                $item_data[] = ['key' => 'Posts', 'value' => $config['posts']];
            }
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
            $item->add_meta_data('Wood Type', $this->labels[$config['material']] ?? $config['material']);
            $item->add_meta_data('Width', $config['width']);
            $item->add_meta_data('Height', $config['height']);
            if (!empty($config['ironmongery'])) {
                $item->add_meta_data('Ironmongery', $config['ironmongery']);
            }
            if (!empty($config['treatment'])) {
                $item->add_meta_data('Treatment', $config['treatment']);
            }
            if (!empty($config['posts'])) {
                $item->add_meta_data('Posts', $config['posts']);
            }
        }
    }
}

Woodline_Configurator::instance();
