<?php
/**
 * Display Class
 *
 * Handles the display of linen count interface in the daily list modal
 *
 * @package HotelHub_Housekeeping_LinenCount
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HHLC_Display
 */
class HHLC_Display {

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
        // Hook into the daily list modal
        add_action('hhdl_modal_sections', array($this, 'render_modal_section'), 10, 5);
    }

    /**
     * Render the linen count section in the modal
     *
     * @param int    $location_id   Hotel Hub location ID
     * @param string $room_id       Room/site identifier
     * @param string $date          Date in Y-m-d format
     * @param array  $room_details  Full room details array (optional)
     * @param array  $booking_data  Booking data from NewBook API (optional)
     */
    public function render_modal_section($location_id, $room_id, $date, $room_details = array(), $booking_data = array()) {
        // Always render the section, but show configuration messages if needed
        $settings = HHLC_Settings::instance();

        // Check if user has permission
        $has_permission = $this->user_can_access();

        // Check if module is enabled for this location
        $is_enabled = $settings->is_enabled_for_location($location_id);

        // Get linen items configuration
        $linen_items = $settings->get_linen_items($location_id);
        $has_items = !empty($linen_items);

        // Get existing counts for this room and date
        $existing_counts = $this->get_room_linen_counts($location_id, $room_id, $date);
        $is_locked = !empty($existing_counts) && isset($existing_counts[0]->is_locked) ? $existing_counts[0]->is_locked : false;

        // Check if user can edit
        $can_edit = HHLC_Core::user_can_edit_submitted();
        ?>
        <section class="hhlc-linen-section">
            <div class="hhdl-section-header">
                <h3>
                    <span class="material-symbols-outlined">dry_cleaning</span>
                    Spoilt Linen Count
                </h3>
            </div>

            <?php if (!$has_permission): ?>
                <p class="hhlc-notice hhlc-notice-warning">
                    You don't have permission to access the Linen Count module.
                </p>
            <?php elseif (!$is_enabled): ?>
                <p class="hhlc-notice hhlc-notice-info">
                    Linen Count module is not enabled for this location.
                    <a href="<?php echo admin_url('admin.php?page=hhlc-settings'); ?>">Enable in settings</a>
                </p>
            <?php elseif (!$has_items): ?>
                <p class="hhlc-notice hhlc-notice-info">
                    No linen items configured for this location.
                    <a href="<?php echo admin_url('admin.php?page=hhlc-settings'); ?>">Add linen items</a>
                </p>
            <?php else: ?>
            <!-- Linen Count Interface -->

            <div class="hhlc-linen-controls <?php echo $is_locked ? 'locked' : ''; ?>"
                 data-location="<?php echo esc_attr($location_id); ?>"
                 data-room="<?php echo esc_attr($room_id); ?>"
                 data-date="<?php echo esc_attr($date); ?>"
                 data-can-edit="<?php echo $can_edit ? 'true' : 'false'; ?>">

                <div class="hhlc-linen-items-grid">
                    <?php foreach ($linen_items as $item):
                        $current_count = $this->get_item_count($existing_counts, $item['id']);
                    ?>
                    <div class="hhlc-linen-item" data-item-id="<?php echo esc_attr($item['id']); ?>">
                        <div class="linen-shortcode" title="<?php echo esc_attr($item['name']); ?>">
                            <?php echo esc_html($item['shortcode']); ?>
                        </div>
                        <button class="linen-count-up" <?php echo $is_locked ? 'disabled' : ''; ?>>▲</button>
                        <div class="linen-count-display">
                            <input type="number"
                                   class="linen-count-value"
                                   value="<?php echo esc_attr($current_count); ?>"
                                   min="0"
                                   readonly
                                   data-original="<?php echo esc_attr($current_count); ?>" />
                        </div>
                        <button class="linen-count-down" <?php echo $is_locked ? 'disabled' : ''; ?>>▼</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="hhlc-linen-actions">
                    <?php if ($is_locked): ?>
                        <?php if ($can_edit): ?>
                        <button type="button" class="button hhlc-edit-linen-count">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </button>
                        <?php else: ?>
                        <button type="button" class="button" disabled>
                            <span class="dashicons dashicons-lock"></span> Locked
                        </button>
                        <?php endif; ?>
                    <?php else: ?>
                    <button type="button" class="button button-primary hhlc-submit-linen-count">
                        <span class="dashicons dashicons-yes"></span> Submit Count
                    </button>
                    <?php endif; ?>
                    <span class="hhlc-linen-status"></span>
                </div>

                <?php if (!empty($existing_counts)): ?>
                <div class="hhlc-linen-metadata">
                    <small>
                        <strong>Submitted by:</strong> <?php echo esc_html($this->get_user_display_name($existing_counts[0]->submitted_by)); ?>
                        at <?php echo esc_html(date('H:i', strtotime($existing_counts[0]->submitted_at))); ?>
                        <?php if ($existing_counts[0]->last_updated_by): ?>
                            <br><strong>Last edited by:</strong> <?php echo esc_html($this->get_user_display_name($existing_counts[0]->last_updated_by)); ?>
                            at <?php echo esc_html(date('H:i', strtotime($existing_counts[0]->last_updated_at))); ?>
                        <?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; // End permission/enabled/items checks ?>
        </section>
        <?php
    }

    /**
     * Get linen counts for a room
     */
    private function get_room_linen_counts($location_id, $room_id, $date) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
            WHERE location_id = %d AND room_id = %s AND service_date = %s",
            $location_id, $room_id, $date
        ));

        return $results;
    }

    /**
     * Get count for a specific item
     */
    private function get_item_count($counts, $item_id) {
        foreach ($counts as $count) {
            if ($count->linen_item_id == $item_id) {
                return $count->count;
            }
        }
        return 0;
    }

    /**
     * Get user display name
     */
    private function get_user_display_name($user_id) {
        $user = get_user_by('id', $user_id);
        return $user ? $user->display_name : 'Unknown';
    }

    /**
     * Check if current user can access
     */
    private function user_can_access() {
        if (function_exists('wfa_user_can')) {
            return wfa_user_can('hhlc_access_module');
        }
        return current_user_can('edit_posts');
    }

    /**
     * Render the main reports page
     * This is the new frontend view for spoilt linen reports
     */
    public static function render_reports_page() {
        // Check permissions
        if (!function_exists('wfa_user_can') || !wfa_user_can('hhlc_access_module')) {
            echo '<div class="hhlc-no-permission">';
            echo '<p>You don\'t have permission to access the Linen Count module.</p>';
            echo '</div>';
            return;
        }

        // Get current location
        $location_id = isset($_GET['location']) ? intval($_GET['location']) : 0;
        if (!$location_id && function_exists('hha_get_current_location')) {
            $location_id = hha_get_current_location();
        }

        // Get current date
        $current_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

        // Check if module is enabled for this location
        $settings = HHLC_Settings::instance();
        if (!$settings->is_enabled_for_location($location_id)) {
            echo '<div class="hhlc-not-enabled">';
            echo '<p>Linen Count module is not enabled for this location.</p>';
            echo '<a href="' . admin_url('admin.php?page=hhlc-settings') . '" class="button">Enable in Settings</a>';
            echo '</div>';
            return;
        }

        // Get linen items
        $linen_items = $settings->get_linen_items($location_id);
        if (empty($linen_items)) {
            echo '<div class="hhlc-no-items">';
            echo '<p>No linen items configured for this location.</p>';
            echo '<a href="' . admin_url('admin.php?page=hhlc-settings') . '" class="button">Configure Linen Items</a>';
            echo '</div>';
            return;
        }

        ?>
        <div class="hhlc-reports-container" data-location="<?php echo esc_attr($location_id); ?>">
            <!-- Tab Navigation -->
            <nav class="hhlc-reports-tabs">
                <button class="hhlc-tab-button active" data-tab="today-counts">Today's Counts</button>
                <button class="hhlc-tab-button" data-tab="today-totals">Today's Totals</button>
                <button class="hhlc-tab-button" data-tab="report">Report</button>
            </nav>

            <!-- Tab Content -->
            <div class="hhlc-tab-content active" id="today-counts-tab">
                <div class="hhlc-loading">
                    <span class="spinner is-active"></span>
                    <p>Loading today's counts...</p>
                </div>
            </div>

            <div class="hhlc-tab-content" id="today-totals-tab">
                <div class="hhlc-loading">
                    <span class="spinner is-active"></span>
                    <p>Loading today's totals...</p>
                </div>
            </div>

            <div class="hhlc-tab-content" id="report-tab">
                <div class="hhlc-date-range-selector">
                    <label>
                        <span>From:</span>
                        <input type="date" id="hhlc-date-from" value="<?php echo esc_attr(date('Y-m-d', strtotime('-7 days'))); ?>">
                    </label>
                    <label>
                        <span>To:</span>
                        <input type="date" id="hhlc-date-to" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                    </label>
                    <button type="button" class="button button-primary" id="hhlc-load-report">Load Report</button>
                </div>
                <div class="hhlc-report-results">
                    <p class="hhlc-notice">Select a date range and click "Load Report" to view data.</p>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <?php self::render_edit_modal(); ?>
        <?php
    }

    /**
     * Render the edit modal for linen counts
     * Reuses the existing modal section code
     */
    private static function render_edit_modal() {
        ?>
        <div class="hhlc-edit-modal-overlay" id="hhlc-edit-modal" style="display: none;">
            <div class="hhlc-edit-modal">
                <div class="hhlc-edit-modal-header">
                    <h3>
                        <span class="material-symbols-outlined">dry_cleaning</span>
                        <span id="hhlc-modal-room-title">Edit Spoilt Linen Count</span>
                    </h3>
                    <button class="hhlc-modal-close" aria-label="Close">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="hhlc-edit-modal-body">
                    <div class="hhlc-loading">
                        <span class="spinner is-active"></span>
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get linen section HTML for edit modal
     * This is called via AJAX to populate the edit modal
     */
    public static function get_linen_section_html($location_id, $room_id, $date) {
        $instance = self::instance();

        // Get settings and items
        $settings = HHLC_Settings::instance();
        $linen_items = $settings->get_linen_items($location_id);

        // Get existing counts
        $existing_counts = $instance->get_room_linen_counts($location_id, $room_id, $date);
        $is_locked = !empty($existing_counts) && isset($existing_counts[0]->is_locked) ? $existing_counts[0]->is_locked : false;

        // Check if user can edit
        $can_edit = HHLC_Core::user_can_edit_submitted();

        ob_start();
        ?>
        <div class="hhlc-linen-controls <?php echo $is_locked ? 'locked' : ''; ?>"
             data-location="<?php echo esc_attr($location_id); ?>"
             data-room="<?php echo esc_attr($room_id); ?>"
             data-date="<?php echo esc_attr($date); ?>"
             data-can-edit="<?php echo $can_edit ? 'true' : 'false'; ?>">

            <div class="hhlc-linen-items-grid">
                <?php foreach ($linen_items as $item):
                    $current_count = $instance->get_item_count($existing_counts, $item['id']);
                ?>
                <div class="hhlc-linen-item" data-item-id="<?php echo esc_attr($item['id']); ?>">
                    <div class="linen-shortcode" title="<?php echo esc_attr($item['name']); ?>">
                        <?php echo esc_html($item['shortcode']); ?>
                    </div>
                    <button class="linen-count-up" <?php echo $is_locked ? 'disabled' : ''; ?>>▲</button>
                    <div class="linen-count-display">
                        <input type="number"
                               class="linen-count-value"
                               value="<?php echo esc_attr($current_count); ?>"
                               min="0"
                               readonly
                               data-original="<?php echo esc_attr($current_count); ?>" />
                    </div>
                    <button class="linen-count-down" <?php echo $is_locked ? 'disabled' : ''; ?>>▼</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="hhlc-linen-actions">
                <?php if ($is_locked): ?>
                    <?php if ($can_edit): ?>
                    <button type="button" class="button hhlc-edit-linen-count">
                        <span class="dashicons dashicons-edit"></span> Edit
                    </button>
                    <?php else: ?>
                    <button type="button" class="button" disabled>
                        <span class="dashicons dashicons-lock"></span> Locked
                    </button>
                    <?php endif; ?>
                <?php else: ?>
                <button type="button" class="button button-primary hhlc-submit-linen-count">
                    <span class="dashicons dashicons-yes"></span> Submit Count
                </button>
                <?php endif; ?>
                <span class="hhlc-linen-status"></span>
            </div>

            <?php if (!empty($existing_counts)): ?>
            <div class="hhlc-linen-metadata">
                <small>
                    <strong>Submitted by:</strong> <?php echo esc_html($instance->get_user_display_name($existing_counts[0]->submitted_by)); ?>
                    at <?php echo esc_html(date('H:i', strtotime($existing_counts[0]->submitted_at))); ?>
                    <?php if ($existing_counts[0]->last_updated_by): ?>
                        <br><strong>Last edited by:</strong> <?php echo esc_html($instance->get_user_display_name($existing_counts[0]->last_updated_by)); ?>
                        at <?php echo esc_html(date('H:i', strtotime($existing_counts[0]->last_updated_at))); ?>
                    <?php endif; ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
