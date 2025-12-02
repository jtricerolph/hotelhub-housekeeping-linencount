<?php
/**
 * Core Module Class
 *
 * Handles module registration, permissions, and asset management
 *
 * @package HotelHub_Housekeeping_LinenCount
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HHLC_Core
 */
class HHLC_Core {

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
        // Register module with Hotel Hub
        add_filter('hha_register_modules', array($this, 'register_module'));

        // Register permissions
        add_filter('wfa_register_permissions', array($this, 'register_permissions'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Register module with Hotel Hub
     */
    public function register_module($modules) {
        $modules['linen_count'] = array(
            'id' => 'linen_count',
            'name' => 'Spoilt Linen Count',
            'description' => 'Track and report soiled linen counts',
            'department' => 'housekeeping',
            'icon' => 'dry_cleaning',
            'color' => '#8b4513', // Brown color for linen
            'version' => HHLC_VERSION,
            'requires' => array('daily_list'),
            'callback' => null, // No separate page, integrates into daily list modal
            'settings_callback' => array(HHLC_Settings::instance(), 'render_settings_card'),
            'supports' => array('multi_location', 'reports', 'real_time_sync')
        );

        return $modules;
    }

    /**
     * Register permissions with WFA
     */
    public function register_permissions($permissions) {
        $permissions['hhlc_access_module'] = array(
            'label' => 'Access Linen Count Module',
            'description' => 'Allow users to view and submit linen counts',
            'department' => 'housekeeping',
            'default_roles' => array('housekeeping', 'housekeeping_supervisor', 'administrator')
        );

        $permissions['hhlc_edit_submitted'] = array(
            'label' => 'Edit Submitted Linen Counts',
            'description' => 'Allow users to edit linen counts after submission',
            'department' => 'housekeeping',
            'default_roles' => array('housekeeping_supervisor', 'administrator')
        );

        $permissions['hhlc_view_reports'] = array(
            'label' => 'View Linen Count Reports',
            'description' => 'Allow users to access linen count reports',
            'department' => 'housekeeping',
            'default_roles' => array('housekeeping_supervisor', 'manager', 'administrator')
        );

        return $permissions;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Only enqueue on Hotel Hub pages
        if (!is_page() && !is_singular()) {
            return;
        }

        // Check if user has access to the module
        if (!$this->user_can_access()) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'hhlc-linen-count',
            HHLC_PLUGIN_URL . 'assets/css/linen-count.css',
            array(),
            HHLC_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'hhlc-linen-count',
            HHLC_PLUGIN_URL . 'assets/js/linen-count.js',
            array('jquery', 'heartbeat'),
            HHLC_VERSION,
            true
        );

        // Localize script
        wp_localize_script('hhlc-linen-count', 'hhlcAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hhlc_ajax_nonce'),
            'userId' => get_current_user_id(),
            'strings' => array(
                'submitting' => __('Submitting...', 'hhlc'),
                'submitCount' => __('Submit Count', 'hhlc'),
                'edit' => __('Edit', 'hhlc'),
                'unlocking' => __('Unlocking...', 'hhlc'),
                'countSubmitted' => __('Count submitted successfully', 'hhlc'),
                'countUnlocked' => __('Count unlocked for editing', 'hhlc'),
                'networkError' => __('Network error. Please try again.', 'hhlc'),
                'submissionError' => __('Failed to submit count', 'hhlc'),
                'confirm' => __('Are you sure?', 'hhlc')
            )
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on settings or reports pages
        if (strpos($hook, 'hhlc') === false && strpos($hook, 'housekeeping') === false) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'hhlc-admin',
            HHLC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            HHLC_VERSION
        );

        // jQuery UI Sortable for settings
        wp_enqueue_script('jquery-ui-sortable');

        // Color picker for settings
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /**
     * Check if current user can access the module
     */
    private function user_can_access() {
        if (function_exists('wfa_user_can')) {
            return wfa_user_can('hhlc_access_module');
        }
        // Fallback to WordPress capabilities
        return current_user_can('edit_posts');
    }

    /**
     * Check if user can edit submitted counts
     */
    public static function user_can_edit_submitted() {
        if (function_exists('wfa_user_can')) {
            return wfa_user_can('hhlc_edit_submitted');
        }
        return current_user_can('edit_others_posts');
    }

    /**
     * Check if user can view reports
     */
    public static function user_can_view_reports() {
        if (function_exists('wfa_user_can')) {
            return wfa_user_can('hhlc_view_reports');
        }
        return current_user_can('view_reports');
    }
}
