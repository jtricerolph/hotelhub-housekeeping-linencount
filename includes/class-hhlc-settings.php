<?php
/**
 * Settings Management Class
 *
 * Handles linen items configuration per location
 *
 * @package HotelHub_Housekeeping_LinenCount
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HHLC_Settings
 */
class HHLC_Settings {

    /**
     * Settings option name
     */
    const OPTION_NAME = 'hhlc_location_settings';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        error_log('HHLC Settings: init_hooks called');
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Save settings
        add_action('admin_post_hhlc_save_settings', array($this, 'save_settings'));

        // Hook into Hotel Hub settings page
        add_filter('hha_module_settings_sections', array($this, 'add_settings_section'), 10, 2);
        
        error_log('HHLC Settings: Hooks registered');
    }

    /**
     * Register settings with WordPress
     */
    public function register_settings() {
        error_log('HHLC Settings: register_settings called');
        register_setting('hhlc_settings_group', self::OPTION_NAME, array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }

    /**
     * Add settings section to Hotel Hub settings page
     */
    public function add_settings_section($sections, $location_id) {
        error_log('HHLC Settings: add_settings_section called with location_id: ' . $location_id);
        error_log('HHLC Settings: Existing sections: ' . print_r(array_keys($sections), true));
        
        $sections['linen_count'] = array(
            'title' => 'Linen Count Configuration',
            'callback' => array($this, 'render_settings_section'),
            'location_id' => $location_id
        );
        
        error_log('HHLC Settings: Added linen_count section');
        return $sections;
    }

    /**
     * Render settings card for module
     */
    public static function render_settings_card($location_id = null) {
        // Debug: Method called
        error_log('HHLC: render_settings_card called with location_id: ' . var_export($location_id, true));
        echo '<!-- HHLC: render_settings_card called -->';
        
        // Get location_id from various sources if not provided
        if (empty($location_id)) {
            if (isset($_GET['location_id'])) {
                $location_id = intval($_GET['location_id']);
            } elseif (function_exists('hha') && method_exists(hha(), 'get_current_location_id')) {
                $location_id = hha()->get_current_location_id();
            } else {
                $location_id = 0;
            }
        }
        
        // Debug output
        error_log('HHLC: Final location_id = ' . $location_id);
        echo '<!-- HHLC Debug: location_id = ' . esc_html($location_id) . ' -->';
        
        try {
            self::render_settings_section($location_id);
            error_log('HHLC: render_settings_section completed');
        } catch (Exception $e) {
            error_log('HHLC Error: ' . $e->getMessage());
            echo '<!-- HHLC Error: ' . esc_html($e->getMessage()) . ' -->';
        }
    }

    /**
     * Render settings section
     */
    private static function render_settings_section($location_id) {
        $settings = self::get_location_settings($location_id);
        $linen_items = isset($settings['linen_items']) ? $settings['linen_items'] : array();
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : true;
        ?>
        <div class="hhlc-settings-section">
            <!-- Enable/Disable Toggle -->
            <div class="hhlc-setting-row">
                <label class="hhlc-toggle">
                    <input type="checkbox"
                           name="hhlc_location_settings[<?php echo esc_attr($location_id); ?>][enabled]"
                           value="1"
                           <?php checked($enabled, true); ?> />
                    <span class="hhlc-toggle-slider"></span>
                    <span class="hhlc-toggle-label">Enable Linen Count Module</span>
                </label>
            </div>

            <!-- Linen Items Configuration -->
            <div class="hhlc-linen-items-config" style="<?php echo $enabled ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
                <h4>Linen Items</h4>
                <p class="description">Configure the linen items to track. Items will appear in the order specified below.</p>

                <div class="hhlc-linen-items-list" id="linen-items-<?php echo esc_attr($location_id); ?>">
                    <?php if (!empty($linen_items)): ?>
                        <?php foreach ($linen_items as $index => $item): ?>
                            <?php self::render_linen_item_row($location_id, $index, $item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" class="button hhlc-add-linen-item" data-location="<?php echo esc_attr($location_id); ?>">
                    <span class="dashicons dashicons-plus-alt"></span> Add Linen Item
                </button>

                <!-- Hidden field to store JSON -->
                <input type="hidden"
                       name="hhlc_location_settings[<?php echo esc_attr($location_id); ?>][linen_items_json]"
                       id="linen-items-json-<?php echo esc_attr($location_id); ?>"
                       value="<?php echo esc_attr(json_encode($linen_items)); ?>" />
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var locationId = '<?php echo esc_js($location_id); ?>';

            // Make the list sortable
            $('#linen-items-' + locationId).sortable({
                handle: '.hhlc-drag-handle',
                placeholder: 'hhlc-sortable-placeholder',
                update: function() {
                    updateLinenItemsJson();
                }
            });

            // Add new linen item
            $(document).on('click', '.hhlc-add-linen-item[data-location="' + locationId + '"]', function() {
                var index = $('#linen-items-' + locationId + ' .hhlc-linen-item-row').length;
                var timestamp = Date.now();
                var newRow = createLinenItemRow(index, timestamp);
                $('#linen-items-' + locationId).append(newRow);
                updateLinenItemsJson();
            });

            // Remove linen item
            $(document).on('click', '#linen-items-' + locationId + ' .hhlc-remove-linen-item', function() {
                if (confirm('Are you sure you want to remove this linen item?')) {
                    $(this).closest('.hhlc-linen-item-row').remove();
                    updateLinenItemsJson();
                }
            });

            // Update JSON when fields change
            $(document).on('input change', '#linen-items-' + locationId + ' input', function() {
                updateLinenItemsJson();
            });

            // Function to create a new row
            function createLinenItemRow(index, timestamp) {
                var template = '<div class="hhlc-linen-item-row" data-index="' + index + '">' +
                    '<span class="hhlc-drag-handle dashicons dashicons-move"></span>' +
                    '<input type="hidden" class="linen-item-id" value="item_' + timestamp + '" />' +
                    '<input type="text" class="linen-item-name" placeholder="Name (e.g., Pillow Case)" value="" />' +
                    '<input type="text" class="linen-item-shortcode" placeholder="Shortcode (e.g., PC)" value="" size="8" />' +
                    '<input type="text" class="linen-item-size" placeholder="Size (e.g., King)" value="" size="10" />' +
                    '<input type="number" class="linen-item-pack-qty" placeholder="Pack Qty" value="1" min="1" />' +
                    '<input type="number" class="linen-item-target-stock" placeholder="Target Stock" value="0" min="0" />' +
                    '<button type="button" class="button hhlc-remove-linen-item">' +
                    '<span class="dashicons dashicons-trash"></span>' +
                    '</button>' +
                    '</div>';
                return template;
            }

            // Function to update the JSON field
            function updateLinenItemsJson() {
                var items = [];
                $('#linen-items-' + locationId + ' .hhlc-linen-item-row').each(function() {
                    var row = $(this);
                    var item = {
                        id: row.find('.linen-item-id').val(),
                        name: row.find('.linen-item-name').val(),
                        shortcode: row.find('.linen-item-shortcode').val(),
                        size: row.find('.linen-item-size').val(),
                        pack_qty: row.find('.linen-item-pack-qty').val(),
                        target_stock_qty: row.find('.linen-item-target-stock').val()
                    };

                    // Only add if name or shortcode is not empty
                    if (item.name || item.shortcode) {
                        items.push(item);
                    }
                });
                $('#linen-items-json-' + locationId).val(JSON.stringify(items));
            }
        });
        </script>
        <?php
    }

    /**
     * Render a single linen item row
     */
    private static function render_linen_item_row($location_id, $index, $item) {
        $defaults = array(
            'id' => '',
            'name' => '',
            'shortcode' => '',
            'size' => '',
            'pack_qty' => 1,
            'target_stock_qty' => 0
        );
        $item = wp_parse_args($item, $defaults);
        ?>
        <div class="hhlc-linen-item-row" data-index="<?php echo esc_attr($index); ?>">
            <span class="hhlc-drag-handle dashicons dashicons-move"></span>
            <input type="hidden" class="linen-item-id" value="<?php echo esc_attr($item['id']); ?>" />
            <input type="text" class="linen-item-name" placeholder="Name (e.g., Pillow Case)" value="<?php echo esc_attr($item['name']); ?>" />
            <input type="text" class="linen-item-shortcode" placeholder="Shortcode (e.g., PC)" value="<?php echo esc_attr($item['shortcode']); ?>" size="8" />
            <input type="text" class="linen-item-size" placeholder="Size (e.g., King)" value="<?php echo esc_attr($item['size']); ?>" size="10" />
            <input type="number" class="linen-item-pack-qty" placeholder="Pack Qty" value="<?php echo esc_attr($item['pack_qty']); ?>" min="1" />
            <input type="number" class="linen-item-target-stock" placeholder="Target Stock" value="<?php echo esc_attr($item['target_stock_qty']); ?>" min="0" />
            <button type="button" class="button hhlc-remove-linen-item">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    public function save_settings() {
        // Check nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'hhlc_save_settings')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Get POST data
        $location_settings = isset($_POST['hhlc_location_settings']) ? $_POST['hhlc_location_settings'] : array();

        // Get existing settings
        $all_settings = get_option(self::OPTION_NAME, array());

        // Update each location
        foreach ($location_settings as $location_id => $settings) {
            $location_id = intval($location_id);

            $sanitized_settings = array(
                'enabled' => isset($settings['enabled']) ? true : false,
                'linen_items' => array()
            );

            // Parse and sanitize linen items
            if (isset($settings['linen_items_json'])) {
                $json = stripslashes($settings['linen_items_json']);
                $items = json_decode($json, true);

                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!empty($item['name']) || !empty($item['shortcode'])) {
                            $sanitized_settings['linen_items'][] = array(
                                'id' => sanitize_text_field($item['id']),
                                'name' => sanitize_text_field($item['name']),
                                'shortcode' => sanitize_text_field($item['shortcode']),
                                'size' => sanitize_text_field($item['size']),
                                'pack_qty' => max(1, intval($item['pack_qty'])),
                                'target_stock_qty' => max(0, intval($item['target_stock_qty']))
                            );
                        }
                    }
                }
            }

            $all_settings[$location_id] = $sanitized_settings;
        }

        // Save to database
        update_option(self::OPTION_NAME, $all_settings);

        // Redirect back to settings page
        $redirect_url = add_query_arg(
            array(
                'page' => 'hha-settings',
                'updated' => 'true'
            ),
            admin_url('admin.php')
        );
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }

        $sanitized = array();

        foreach ($settings as $location_id => $location_settings) {
            $location_id = intval($location_id);
            $sanitized[$location_id] = array(
                'enabled' => isset($location_settings['enabled']) ? (bool) $location_settings['enabled'] : false,
                'linen_items' => array()
            );

            if (isset($location_settings['linen_items']) && is_array($location_settings['linen_items'])) {
                foreach ($location_settings['linen_items'] as $item) {
                    if (!empty($item['name']) || !empty($item['shortcode'])) {
                        $sanitized[$location_id]['linen_items'][] = array(
                            'id' => sanitize_text_field($item['id']),
                            'name' => sanitize_text_field($item['name']),
                            'shortcode' => sanitize_text_field($item['shortcode']),
                            'size' => sanitize_text_field($item['size']),
                            'pack_qty' => max(1, intval($item['pack_qty'])),
                            'target_stock_qty' => max(0, intval($item['target_stock_qty']))
                        );
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get settings for a location
     */
    public static function get_location_settings($location_id) {
        $all_settings = get_option(self::OPTION_NAME, array());
        return isset($all_settings[$location_id]) ? $all_settings[$location_id] : array();
    }

    /**
     * Get linen items for a location
     */
    public function get_linen_items($location_id) {
        $settings = self::get_location_settings($location_id);
        return isset($settings['linen_items']) ? $settings['linen_items'] : array();
    }

    /**
     * Check if module is enabled for location
     */
    public function is_enabled_for_location($location_id) {
        $settings = self::get_location_settings($location_id);
        return isset($settings['enabled']) ? $settings['enabled'] : true;
    }
}
