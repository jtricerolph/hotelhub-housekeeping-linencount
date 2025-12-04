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
        add_action('wp_ajax_hhlc_autosave_linen_count', array($this, 'autosave_linen_count'));

        // Report AJAX handlers
        add_action('wp_ajax_hhlc_get_today_counts', array($this, 'get_today_counts'));
        add_action('wp_ajax_hhlc_get_today_totals', array($this, 'get_today_totals'));
        add_action('wp_ajax_hhlc_get_date_range_report', array($this, 'get_date_range_report'));
        add_action('wp_ajax_hhlc_get_room_linen_data', array($this, 'get_room_linen_data'));
        add_action('wp_ajax_hhlc_submit_all_unsubmitted', array($this, 'submit_all_unsubmitted'));
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

            // Fire action hook for activity logging
            do_action('hhlc_linen_submitted', $location_id, $room_id, $date, $counts, $booking_ref, $user->display_name);

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
     * Auto-save linen count (saves as unlocked draft)
     */
    public function autosave_linen_count() {
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
        $item_id = isset($_POST['item_id']) ? sanitize_text_field($_POST['item_id']) : '';
        $count = isset($_POST['count']) ? max(0, intval($_POST['count'])) : 0;
        $booking_ref = isset($_POST['booking_ref']) ? sanitize_text_field($_POST['booking_ref']) : '';

        // Validate
        if (!$location_id || !$room_id || !$date || !$item_id) {
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

        // Check if there's an existing locked submission
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_locked, submitted_by, submitted_at FROM {$table_name}
            WHERE location_id = %d AND room_id = %s AND linen_item_id = %s AND service_date = %s",
            $location_id, $room_id, $item_id, $date
        ));

        try {
            if ($existing) {
                // Update existing record
                // If locked, we're editing a submitted count
                if ($existing->is_locked) {
                    // Keep it locked but update the count and edit metadata
                    $wpdb->update(
                        $table_name,
                        array(
                            'count' => $count,
                            'last_updated_by' => $current_user_id,
                            'last_updated_at' => $current_time
                        ),
                        array('id' => $existing->id),
                        array('%d', '%d', '%s'),
                        array('%d')
                    );
                } else {
                    // Update unlocked draft
                    $wpdb->update(
                        $table_name,
                        array(
                            'count' => $count,
                            'last_updated_by' => $current_user_id,
                            'last_updated_at' => $current_time,
                            'booking_ref' => $booking_ref
                        ),
                        array('id' => $existing->id),
                        array('%d', '%d', '%s', '%s'),
                        array('%d')
                    );
                }
            } else {
                // Insert new unlocked draft record
                $wpdb->insert(
                    $table_name,
                    array(
                        'location_id' => $location_id,
                        'room_id' => $room_id,
                        'linen_item_id' => $item_id,
                        'count' => $count,
                        'submitted_by' => $current_user_id,
                        'submitted_at' => $current_time,
                        'service_date' => $date,
                        'booking_ref' => $booking_ref,
                        'is_locked' => false,
                        'last_updated_by' => $current_user_id,
                        'last_updated_at' => $current_time
                    ),
                    array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s')
                );
            }

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            $user = wp_get_current_user();
            wp_send_json_success(array(
                'message' => 'Auto-saved',
                'saved_by' => $user->display_name,
                'saved_at' => date('H:i', strtotime($current_time)),
                'timestamp' => $current_time
            ));

        } catch (Exception $e) {
            error_log('HHLC Auto-save Error: ' . $e->getMessage());
            wp_send_json_error('Failed to auto-save: ' . $e->getMessage());
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

    /**
     * Get today's counts for all rooms
     */
    public function get_today_counts() {
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
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');

        if (!$location_id) {
            wp_send_json_error('Invalid location');
            return;
        }

        // Get all rooms from daily list
        $rooms = $this->get_all_rooms($location_id);

        // Get linen items for this location
        $settings = HHLC_Settings::instance();
        $linen_items = $settings->get_linen_items($location_id);

        if (empty($linen_items)) {
            wp_send_json_error('No linen items configured');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        // Get all counts for today
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT room_id, linen_item_id, count, is_locked
            FROM {$table_name}
            WHERE location_id = %d AND service_date = %s",
            $location_id, $date
        ));

        // Organize data by room
        $room_data = array();
        foreach ($rooms as $room) {
            $room_id = $room['room_id'];
            $room_data[$room_id] = array(
                'room_id' => $room_id,
                'counts' => array(),
                'status' => 'none', // none, unsubmitted, submitted
                'has_any_count' => false
            );

            // Check counts for this room
            $has_locked = false;
            $has_unlocked = false;

            foreach ($counts as $count) {
                if ($count->room_id === $room_id) {
                    $room_data[$room_id]['counts'][$count->linen_item_id] = $count->count;
                    $room_data[$room_id]['has_any_count'] = true;

                    if ($count->is_locked) {
                        $has_locked = true;
                    } else {
                        $has_unlocked = true;
                    }
                }
            }

            // Determine status
            if ($has_locked) {
                $room_data[$room_id]['status'] = 'submitted';
            } elseif ($has_unlocked) {
                $room_data[$room_id]['status'] = 'unsubmitted';
            }
        }

        wp_send_json_success(array(
            'rooms' => array_values($room_data),
            'linen_items' => $linen_items,
            'date' => $date
        ));
    }

    /**
     * Get today's totals
     */
    public function get_today_totals() {
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
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');

        if (!$location_id) {
            wp_send_json_error('Invalid location');
            return;
        }

        // Get linen items for this location
        $settings = HHLC_Settings::instance();
        $linen_items = $settings->get_linen_items($location_id);

        if (empty($linen_items)) {
            wp_send_json_error('No linen items configured');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        // Get totals for today
        $totals = $wpdb->get_results($wpdb->prepare(
            "SELECT linen_item_id, SUM(count) as total
            FROM {$table_name}
            WHERE location_id = %d AND service_date = %s
            GROUP BY linen_item_id",
            $location_id, $date
        ), OBJECT_K);

        // Build response with item names
        $result = array();
        foreach ($linen_items as $item) {
            $total = isset($totals[$item['id']]) ? intval($totals[$item['id']]->total) : 0;
            $result[] = array(
                'id' => $item['id'],
                'name' => $item['name'],
                'shortcode' => $item['shortcode'],
                'total' => $total
            );
        }

        wp_send_json_success(array(
            'totals' => $result,
            'date' => $date
        ));
    }

    /**
     * Get date range report
     */
    public function get_date_range_report() {
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
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        if (!$location_id || !$date_from || !$date_to) {
            wp_send_json_error('Invalid parameters');
            return;
        }

        // Get linen items for this location
        $settings = HHLC_Settings::instance();
        $linen_items = $settings->get_linen_items($location_id);

        if (empty($linen_items)) {
            wp_send_json_error('No linen items configured');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        // Get counts grouped by date and item
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT service_date, linen_item_id, SUM(count) as total
            FROM {$table_name}
            WHERE location_id = %d AND service_date BETWEEN %s AND %s
            GROUP BY service_date, linen_item_id
            ORDER BY service_date ASC",
            $location_id, $date_from, $date_to
        ));

        // Generate all dates in range
        $dates = array();
        $current = strtotime($date_from);
        $end = strtotime($date_to);
        while ($current <= $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }

        // Organize data by item and date
        $report_data = array();
        foreach ($linen_items as $item) {
            $row = array(
                'id' => $item['id'],
                'name' => $item['name'],
                'shortcode' => $item['shortcode'],
                'by_date' => array(),
                'grand_total' => 0
            );

            // Initialize all dates with 0
            foreach ($dates as $date) {
                $row['by_date'][$date] = 0;
            }

            // Fill in actual counts
            foreach ($results as $result) {
                if ($result->linen_item_id === $item['id']) {
                    $row['by_date'][$result->service_date] = intval($result->total);
                    $row['grand_total'] += intval($result->total);
                }
            }

            $report_data[] = $row;
        }

        wp_send_json_success(array(
            'report' => $report_data,
            'dates' => $dates,
            'date_from' => $date_from,
            'date_to' => $date_to
        ));
    }

    /**
     * Get room linen data for edit modal
     */
    public function get_room_linen_data() {
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
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');

        if (!$location_id || !$room_id) {
            wp_send_json_error('Invalid parameters');
            return;
        }

        // Get the linen section HTML
        $html = HHLC_Display::get_linen_section_html($location_id, $room_id, $date);

        wp_send_json_success(array(
            'html' => $html,
            'room_id' => $room_id,
            'date' => $date
        ));
    }

    /**
     * Submit all unsubmitted counts for today
     */
    public function submit_all_unsubmitted() {
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
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');

        if (!$location_id) {
            wp_send_json_error('Invalid location');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';
        $current_user_id = get_current_user_id();
        $current_time = current_time('mysql');

        // Update all unlocked counts to locked
        $updated = $wpdb->update(
            $table_name,
            array(
                'is_locked' => true,
                'last_updated_by' => $current_user_id,
                'last_updated_at' => $current_time
            ),
            array(
                'location_id' => $location_id,
                'service_date' => $date,
                'is_locked' => false
            ),
            array('%d', '%d', '%s'),
            array('%d', '%s', '%d')
        );

        if ($updated !== false && $updated > 0) {
            wp_send_json_success(array(
                'message' => sprintf('%d room count(s) submitted successfully', $updated),
                'count' => $updated
            ));
        } elseif ($updated === 0) {
            wp_send_json_error('No unsubmitted counts found');
        } else {
            wp_send_json_error('Failed to submit counts');
        }
    }

    /**
     * Get all rooms for a location
     * This helper method fetches rooms from the daily list or returns a default list
     */
    private function get_all_rooms($location_id) {
        // Try to get rooms from the daily list module
        if (class_exists('HHDL_Display')) {
            // Use daily list to get today's rooms
            // For now, we'll return a basic structure
            // In production, this would integrate with the NewBook API or daily list cache
        }

        // Fallback: Get unique room IDs from linen counts table
        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        $room_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT room_id FROM {$table_name}
            WHERE location_id = %d
            ORDER BY room_id ASC",
            $location_id
        ));

        $rooms = array();
        foreach ($room_ids as $room_id) {
            $rooms[] = array('room_id' => $room_id);
        }

        return $rooms;
    }
}
