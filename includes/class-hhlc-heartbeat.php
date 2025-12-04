<?php
/**
 * Heartbeat Integration Class
 *
 * Handles real-time synchronization via WordPress Heartbeat API
 *
 * @package HotelHub_Housekeeping_LinenCount
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HHLC_Heartbeat
 */
class HHLC_Heartbeat {

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
        // Heartbeat hooks
        add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 3);
        add_filter('heartbeat_send', array($this, 'heartbeat_send'), 10, 2);
    }

    /**
     * Modify heartbeat settings
     */
    public function heartbeat_settings($settings) {
        // Set heartbeat to 30 seconds for more frequent updates
        $settings['interval'] = 30;
        return $settings;
    }

    /**
     * Process heartbeat request
     */
    public function heartbeat_received($response, $data, $screen_id) {
        if (!isset($data['hhlc_linen_monitor'])) {
            return $response;
        }

        $monitor_data = $data['hhlc_linen_monitor'];
        $location_id = isset($monitor_data['location_id']) ? intval($monitor_data['location_id']) : 0;
        $last_check = isset($monitor_data['last_check']) ? sanitize_text_field($monitor_data['last_check']) : '';
        $viewing_date = isset($monitor_data['viewing_date']) ? sanitize_text_field($monitor_data['viewing_date']) : '';
        $current_room = isset($monitor_data['current_room']) ? sanitize_text_field($monitor_data['current_room']) : '';
        $modal_open = isset($monitor_data['modal_open']) ? (bool)$monitor_data['modal_open'] : false;

        if (!$location_id || !$last_check || !$viewing_date) {
            return $response;
        }

        // Record user activity
        $this->record_activity(get_current_user_id(), $location_id, $viewing_date, $current_room, $modal_open);

        // Get recent linen count updates
        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        // Build query - if modal is open and viewing specific room, only get updates for that room
        $query = "SELECT lc.*,
                    u1.display_name as submitted_by_name,
                    u2.display_name as last_updated_by_name
            FROM {$table_name} lc
            LEFT JOIN {$wpdb->users} u1 ON lc.submitted_by = u1.ID
            LEFT JOIN {$wpdb->users} u2 ON lc.last_updated_by = u2.ID
            WHERE lc.location_id = %d
            AND lc.service_date = %s
            AND (lc.submitted_at >= %s OR (lc.last_updated_at IS NOT NULL AND lc.last_updated_at >= %s))";

        $query_params = array($location_id, $viewing_date, $last_check, $last_check);

        // If modal is open and viewing a specific room, filter to that room only
        if ($modal_open && !empty($current_room)) {
            $query .= " AND lc.room_id = %s";
            $query_params[] = $current_room;
        }

        $query .= " ORDER BY GREATEST(lc.submitted_at, IFNULL(lc.last_updated_at, '0000-00-00')) DESC";

        $updates = $wpdb->get_results($wpdb->prepare($query, $query_params));

        if (!empty($updates)) {
            // Get the most recent timestamp from the updates
            // Since we ORDER BY DESC, the first record has the latest timestamp
            $latest_timestamp = $updates[0]->submitted_at;
            if (!empty($updates[0]->last_updated_at)) {
                // Compare both timestamps and use the more recent one
                $latest_timestamp = max($updates[0]->submitted_at, $updates[0]->last_updated_at);
            }

            $response['hhlc_linen_updates'] = array(
                'updates' => $updates,
                'timestamp' => $latest_timestamp,  // Use timestamp of most recent update
                'for_room' => $current_room
            );
        }

        // Get active users
        $active_users = $this->get_active_users_list($location_id, $viewing_date);
        if (!empty($active_users)) {
            $response['hhlc_active_users'] = $active_users;
        }

        return $response;
    }

    /**
     * Add data to send with heartbeat
     */
    public function heartbeat_send($response, $screen_id) {
        // Can add additional data to send if needed
        return $response;
    }

    /**
     * Record user activity
     */
    private function record_activity($user_id, $location_id, $viewing_date, $current_room = '', $modal_open = false) {
        $transient_key = 'hhlc_active_' . $location_id . '_' . str_replace('-', '', $viewing_date);
        $users = get_transient($transient_key);

        if (!is_array($users)) {
            $users = array();
        }

        $users[$user_id] = array(
            'user_id' => $user_id,
            'display_name' => wp_get_current_user()->display_name,
            'last_active' => current_time('mysql'),
            'current_room' => $current_room,
            'modal_open' => $modal_open
        );

        // Remove inactive users (more than 2 minutes old)
        $cutoff_time = strtotime('-2 minutes', current_time('timestamp'));
        foreach ($users as $uid => $user_data) {
            if (strtotime($user_data['last_active']) < $cutoff_time) {
                unset($users[$uid]);
            }
        }

        set_transient($transient_key, $users, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Get list of active users
     */
    private function get_active_users_list($location_id, $viewing_date) {
        $transient_key = 'hhlc_active_' . $location_id . '_' . str_replace('-', '', $viewing_date);
        $users = get_transient($transient_key);

        if (!is_array($users)) {
            return array();
        }

        // Remove current user from the list (they know they're active)
        $current_user_id = get_current_user_id();
        if (isset($users[$current_user_id])) {
            unset($users[$current_user_id]);
        }

        return array_values($users);
    }
}
