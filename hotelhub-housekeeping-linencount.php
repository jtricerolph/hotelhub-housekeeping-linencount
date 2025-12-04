<?php
/**
 * Plugin Name: Hotel Hub Module - Housekeeping - Spoilt Linen Count
 * Plugin URI: https://github.com/jtricerolph/hotelhub-housekeeping-linencount
 * Description: Soiled linen count tracking module for housekeeping with real-time sync and reporting
 * Version: 1.2.0
 * Author: JTR
 * License: GPL v2 or later
 * Text Domain: hhlc
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * Requires Plugins: hotel-hub-app, hotelhubmodule-housekeeping-dailylist
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HHLC_VERSION', '1.2.0');
define('HHLC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HHLC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HHLC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class HotelHub_Housekeeping_LinenCount {

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
        $this->check_dependencies();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Check for required dependencies
     */
    private function check_dependencies() {
        add_action('admin_init', array($this, 'check_required_plugins'));
    }

    /**
     * Check if required plugins are active
     */
    public function check_required_plugins() {
        $required_plugins = array(
            'hotel-hub-app/hotel-hub-app.php' => 'Hotel Hub App',
            'hotelhubmodule-housekeeping-dailylist/hotelhubmodule-housekeeping-dailylist.php' => 'Hotel Hub Housekeeping Daily List'
        );

        $missing_plugins = array();

        foreach ($required_plugins as $plugin_file => $plugin_name) {
            if (!is_plugin_active($plugin_file)) {
                $missing_plugins[] = $plugin_name;
            }
        }

        if (!empty($missing_plugins)) {
            add_action('admin_notices', function() use ($missing_plugins) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Hotel Hub Linen Count Module</strong> requires the following plugins to be installed and activated:
                    </p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <?php foreach ($missing_plugins as $plugin_name): ?>
                            <li><?php echo esc_html($plugin_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php
            });

            // Deactivate this plugin
            deactivate_plugins(HHLC_PLUGIN_BASENAME);
        }
    }

    /**
     * Load required files
     */
    private function load_dependencies() {

        require_once HHLC_PLUGIN_DIR . 'includes/class-hhlc-core.php';
        require_once HHLC_PLUGIN_DIR . 'includes/class-hhlc-settings.php';
        require_once HHLC_PLUGIN_DIR . 'includes/class-hhlc-ajax.php';
        require_once HHLC_PLUGIN_DIR . 'includes/class-hhlc-display.php';
        require_once HHLC_PLUGIN_DIR . 'includes/class-hhlc-heartbeat.php';
        require_once HHLC_PLUGIN_DIR . 'admin/class-hhlc-reports.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize core functionality
        add_action('plugins_loaded', array($this, 'init'), 15); // Priority 15 to run after Hotel Hub
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if Hotel Hub is available
        if (!function_exists('hha') || !class_exists('HHDL_Core')) {
            return;
        }

        // Initialize core components
        HHLC_Core::instance();
        HHLC_Settings::instance();
        HHLC_Ajax::instance();
        HHLC_Display::instance();
        HHLC_Heartbeat::instance();
        HHLC_Reports::instance();
    }

    /**
     * Activation hook
     */
    public function activate() {
        // Check dependencies before activation
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!is_plugin_active('hotel-hub-app/hotel-hub-app.php')) {
            wp_die(
                'Hotel Hub Linen Count Module requires Hotel Hub App to be installed and activated.',
                'Plugin Dependency Error',
                array('back_link' => true)
            );
        }

        if (!is_plugin_active('hotelhubmodule-housekeeping-dailylist/hotelhubmodule-housekeeping-dailylist.php')) {
            wp_die(
                'Hotel Hub Linen Count Module requires Hotel Hub Housekeeping Daily List to be installed and activated.',
                'Plugin Dependency Error',
                array('back_link' => true)
            );
        }

        $this->create_tables();
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Linen counts table
        $linen_counts_table = $wpdb->prefix . 'hhlc_linen_counts';

        $sql = "CREATE TABLE {$linen_counts_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL,
            room_id VARCHAR(50) NOT NULL,
            linen_item_id VARCHAR(50) NOT NULL COMMENT 'ID of the linen item',
            count INT(11) UNSIGNED NOT NULL DEFAULT 0,
            submitted_by BIGINT(20) UNSIGNED NOT NULL COMMENT 'WordPress user ID',
            submitted_at DATETIME NOT NULL,
            service_date DATE NOT NULL,
            booking_ref VARCHAR(100) DEFAULT NULL,
            is_locked BOOLEAN DEFAULT TRUE COMMENT 'Whether the count is locked from editing',
            last_updated_by BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'User who last edited',
            last_updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_room_item_date (location_id, room_id, linen_item_id, service_date),
            KEY location_date_idx (location_id, service_date),
            KEY room_date_idx (room_id, service_date),
            KEY submitted_by_idx (submitted_by),
            KEY last_updated_by_idx (last_updated_by)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Store database version
        update_option('hhlc_db_version', HHLC_VERSION);
    }

    /**
     * Deactivation hook
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize plugin
 */
function hhlc_init() {
    return HotelHub_Housekeeping_LinenCount::instance();
}

// Start the plugin
hhlc_init();
