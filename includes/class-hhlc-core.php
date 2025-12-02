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
    public function register_module($modules_manager) {
        $modules_manager->register_module($this);
    }

    /**
     * Register permissions with WFA
     */
    public function register_permissions($permissions_manager) {
        // Register permission: Access module
        $permissions_manager->register_permission(
            'hhlc_access_module',
            __('Access Linen Count Module', 'hhlc'),
            __('Allow users to view and submit linen counts', 'hhlc'),
            'Housekeeping - Linen Count'
        );

        // Register permission: Edit submitted counts
        $permissions_manager->register_permission(
            'hhlc_edit_submitted',
            __('Edit Submitted Linen Counts', 'hhlc'),
            __('Allow users to edit linen counts after submission', 'hhlc'),
            'Housekeeping - Linen Count'
        );

        // Register permission: View reports
        $permissions_manager->register_permission(
            'hhlc_view_reports',
            __('View Linen Count Reports', 'hhlc'),
            __('Allow users to access linen count reports', 'hhlc'),
            'Housekeeping - Linen Count'
        );
    }

    /**
     * Module interface methods required by HHA_Modules
     */

    public function get_id() {
        return 'linen_count';
    }

    public function get_name() {
        return __('Spoilt Linen Count', 'hhlc');
    }

    public function get_description() {
        return __('Track and report soiled linen counts', 'hhlc');
    }

    public function get_department() {
        return 'housekeeping';
    }

    public function get_icon() {
        return 'dry_cleaning';
    }

    public function get_color() {
        return '#8b4513';
    }

    public function get_version() {
        return HHLC_VERSION;
    }

    public function get_requires() {
        return array('daily_list');
    }

    public function get_callback() {
        return null;
    }


    public function get_supports() {
        return array('multi_location', 'reports', 'real_time_sync');
    }

    public function get_config() {
        return array(
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'description' => $this->get_description(),
            'department' => $this->get_department(),
            'icon' => $this->get_icon(),
            'color' => $this->get_color(),
            'version' => $this->get_version(),
            'permissions' => array(
                'hhlc_access_module',
                'hhlc_edit_submitted',
                'hhlc_view_reports'
            ),
            'settings_pages' => array(
                array(
                    'slug' => 'hhlc-settings',
                    'title' => __('Linen Count Settings', 'hhlc'),
                    'menu_title' => __('Linen Count', 'hhlc'),
                    'callback' => array('HHLC_Settings', 'render_settings_card')
                )
            )
        );
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
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hhlc_ajax_nonce'),
            'user_id' => get_current_user_id(),
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
