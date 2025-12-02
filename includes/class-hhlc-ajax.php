<?php
/**
 * AJAX Handlers Class
 *
 * Handles all AJAX requests for linen count operations
 *
 * @package HotelHub_Housekeeping_LinenCount
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HHLC_Ajax
 */
class HHLC_Ajax {

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
        // AJAX handlers for logged-in users
        add_action('wp_ajax_hhlc_submit_linen_count', array($this, 'submit_linen_count'));
        add_action('wp_ajax_hhlc_get_linen_counts', array($this, 'get_linen_counts'));
        add_action('wp_ajax_hhlc_unlock_linen_count', array($this, 'unlock_linen_count'));
    }

    /**
     * Submit linen count
     */
    public function submit_linen_count() {
        // Verify nonce
        if (!check_ajax_referer('hhlc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Check permissions
        if (!$this->check_permission('hhlc_access_module')) {
            wp_send_json_error('Permission denied');
            return;
        }

        // Get parameters
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $room_id = isset($_POST['room_id']) ? sanitize_text_field($_POST['room_id']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $counts = isset($_POST['counts']) ? json_decode(stripslashes($_POST['counts']), true) : array();
        $booking_ref = isset($_POST['booking_ref']) ? sanitize_text_field($_POST['booking_ref']) : '';

        // Validate
        if (!$location_id || !$room_id || !$date || !is_array($counts)) {
            wp_send_json_error('Invalid parameters');
            return;
        }

        // Check if module is enabled for this location
        $settings = HHLC_Settings::instance();
        if (!$settings->is_enabled_for_location($location_id)) {
            wp_send_json_error('Module not enabled for this location');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';
        $current_user_id = get_current_user_id();
        $current_time = current_time('mysql');

        $wpdb->query('START TRANSACTION');

        try {
            // Check if there's an existing submission
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name}
                WHERE location_id = %d AND room_id = %s AND service_date = %s AND is_locked = 1",
                $location_id, $room_id, $date
            ));

            // If locked and not allowed to edit, reject
            if ($existing > 0 && !HHLC_Core::user_can_edit_submitted()) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Count already submitted and locked. You do not have permission to edit.');
                return;
            }

            $is_update = $existing > 0;

            // Insert or update each linen item count
            foreach ($counts as $item_id => $count) {
                $count = max(0, intval($count));

                if ($is_update) {
                    // Update existing record
                    $wpdb->replace(
                        $table_name,
                        array(
                            'location_id' => $location_id,
                            'room_id' => $room_id,
                            'linen_item_id' => sanitize_text_field($item_id),
                            'count' => $count,
                            'submitted_by' => $current_user_id,
                            'submitted_at' => $wpdb->get_var($wpdb->prepare(
                                "SELECT submitted_at FROM {$table_name}
                                WHERE location_id = %d AND room_id = %s AND linen_item_id = %s AND service_date = %s
                                LIMIT 1",
                                $location_id, $room_id, $item_id, $date
                            )),
                            'service_date' => $date,
                            'booking_ref' => $booking_ref,
                            'is_locked' => true,
                            'last_updated_by' => $current_user_id,
                            'last_updated_at' => $current_time
                        ),
                        array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s')
                    );
                } else {
                    // Insert new record
                    $wpdb->replace(
                        $table_name,
                        array(
                            'location_id' => $location_id,
                            'room_id' => $room_id,
                            'linen_item_id' => sanitize_text_field($item_id),
                            'count' => $count,
                            'submitted_by' => $current_user_id,
                            'submitted_at' => $current_time,
                            'service_date' => $date,
                            'booking_ref' => $booking_ref,
                            'is_locked' => true,
                            'last_updated_by' => null,
                            'last_updated_at' => null
                        ),
                        array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s')
                    );
                }
            }

            $wpdb->query('COMMIT');

            $user = wp_get_current_user();
            $response_data = array(
                'message' => $is_update ? 'Linen count updated successfully' : 'Linen count submitted successfully',
                'submitted_by' => $user->display_name,
                'submitted_at' => date('H:i', strtotime($current_time)),
                'is_update' => $is_update
            );

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('HHLC Error: ' . $e->getMessage());
            wp_send_json_error('Database error: ' . $e->getMessage());
        }
    }

    /**
     * Get linen counts for a room
     */
    public function get_linen_counts() {
        // Verify nonce
        if (!check_ajax_referer('hhlc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Check permissions
        if (!$this->check_permission('hhlc_access_module')) {
            wp_send_json_error('Permission denied');
            return;
        }

        // Get parameters
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $room_id = isset($_POST['room_id']) ? sanitize_text_field($_POST['room_id']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (!$location_id || !$room_id || !$date) {
            wp_send_json_error('Invalid parameters');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT lc.*, u1.display_name as submitted_by_name, u2.display_name as updated_by_name
            FROM {$table_name} lc
            LEFT JOIN {$wpdb->users} u1 ON lc.submitted_by = u1.ID
            LEFT JOIN {$wpdb->users} u2 ON lc.last_updated_by = u2.ID
            WHERE lc.location_id = %d AND lc.room_id = %s AND lc.service_date = %s",
            $location_id, $room_id, $date
        ));

        wp_send_json_success(array(
            'counts' => $results,
            'has_data' => !empty($results)
        ));
    }

    /**
     * Unlock linen count for editing
     */
    public function unlock_linen_count() {
        // Verify nonce
        if (!check_ajax_referer('hhlc_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token');
            return;
        }

        // Check permissions
        if (!$this->check_permission('hhlc_access_module')) {
            wp_send_json_error('Permission denied');
            return;
        }

        // Check if user can edit submitted counts
        if (!HHLC_Core::user_can_edit_submitted()) {
            wp_send_json_error('You do not have permission to edit submitted counts');
            return;
        }

        // Get parameters
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $room_id = isset($_POST['room_id']) ? sanitize_text_field($_POST['room_id']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (!$location_id || !$room_id || !$date) {
            wp_send_json_error('Invalid parameters');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        $updated = $wpdb->update(
            $table_name,
            array('is_locked' => false),
            array(
                'location_id' => $location_id,
                'room_id' => $room_id,
                'service_date' => $date
            ),
            array('%d'),
            array('%d', '%s', '%s')
        );

        if ($updated !== false) {
            wp_send_json_success(array('message' => 'Count unlocked for editing'));
        } else {
            wp_send_json_error('Failed to unlock count');
        }
    }

    /**
     * Check user permission
     */
    private function check_permission($permission) {
        if (function_exists('wfa_user_can')) {
            return wfa_user_can($permission);
        }
        return current_user_can('edit_posts');
    }
}
