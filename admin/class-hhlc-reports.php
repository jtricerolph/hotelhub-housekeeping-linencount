<?php
/**
 * Linen Count Reports
 *
 * Provides calendar view and date range reporting for linen counts
 *
 * @package HotelHub_Housekeeping_DailyList
 * @subpackage Reports
 * @since 2.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HHLC_Reports
 *
 * Handles linen count reporting functionality
 */
class HHLC_Reports {

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
        // Register with Hotel Hub reports system
        add_filter('hha_register_reports', array($this, 'register_report'));

        // AJAX handlers for calendar data
        add_action('wp_ajax_hhdl_get_linen_calendar_data', array($this, 'ajax_get_calendar_data'));
        add_action('wp_ajax_hhdl_get_linen_day_details', array($this, 'ajax_get_day_details'));
        add_action('wp_ajax_hhdl_export_linen_report', array($this, 'ajax_export_report'));

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_report_assets'));
    }

    /**
     * Register the linen report
     * Note: Filter passes HHA_Reports object but expects array return
     */
    public function register_report($reports) {
        return array(
            'linen-counts' => array(
                'title' => __('Linen Count Report', 'hhlc'),
                'description' => __('View and analyze soiled linen counts', 'hhlc'),
                'capability' => 'view_reports',
                'callback' => array($this, 'render_report_page'),
                'icon' => 'dry_cleaning',
                'department' => 'housekeeping'
            )
        );
    }

    /**
     * Render the report page
     */
    public function render_report_page() {
        $current_location = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
        $current_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
        ?>
        <div class="wrap hhdl-linen-report-wrap">
            <h1>Linen Count Report</h1>

            <div class="hhdl-report-filters">
                <form method="get" action="" id="linen-report-filters">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />

                    <!-- Location Selector -->
                    <?php if (function_exists('hha')) :
                        $locations = hha()->hotels->get_active();
                        if (count($locations) > 1) : ?>
                    <select name="location_id" id="location-selector">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location) : ?>
                            <option value="<?php echo esc_attr($location->id); ?>"
                                    <?php selected($current_location, $location->id); ?>>
                                <?php echo esc_html($location->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; endif; ?>

                    <!-- Month Selector -->
                    <input type="month" name="month" id="month-selector"
                           value="<?php echo esc_attr($current_month); ?>" />

                    <button type="submit" class="button button-primary">Update View</button>
                </form>
            </div>

            <!-- Report Tabs -->
            <div class="nav-tab-wrapper">
                <a href="#calendar-view" class="nav-tab nav-tab-active" data-tab="calendar">Calendar View</a>
                <a href="#summary-report" class="nav-tab" data-tab="summary">Summary Report</a>
                <a href="#export" class="nav-tab" data-tab="export">Export Data</a>
            </div>

            <!-- Calendar View Tab -->
            <div id="calendar-view" class="tab-content active">
                <div class="hhdl-calendar-container">
                    <div class="hhdl-calendar-header">
                        <button type="button" class="button prev-month">&lt; Previous</button>
                        <h2 class="calendar-month-year"><?php echo date('F Y', strtotime($current_month)); ?></h2>
                        <button type="button" class="button next-month">Next &gt;</button>
                    </div>

                    <div id="linen-calendar" class="hhdl-calendar-grid"
                         data-location="<?php echo esc_attr($current_location); ?>"
                         data-month="<?php echo esc_attr($current_month); ?>">
                        <!-- Calendar will be populated via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Summary Report Tab -->
            <div id="summary-report" class="tab-content">
                <div class="hhdl-summary-controls">
                    <label>Date Range:</label>
                    <input type="date" id="summary-start-date" value="<?php echo date('Y-m-01'); ?>" />
                    <span>to</span>
                    <input type="date" id="summary-end-date" value="<?php echo date('Y-m-t'); ?>" />
                    <button type="button" class="button button-primary" id="generate-summary">Generate Report</button>
                </div>

                <div id="summary-results">
                    <!-- Summary will be populated here -->
                </div>
            </div>

            <!-- Export Tab -->
            <div id="export" class="tab-content">
                <h3>Export Linen Count Data</h3>
                <div class="export-options">
                    <label>
                        <input type="radio" name="export-format" value="csv" checked> CSV Format
                    </label>
                    <label>
                        <input type="radio" name="export-format" value="excel"> Excel Format
                    </label>
                </div>

                <div class="export-date-range">
                    <label>Date Range:</label>
                    <input type="date" id="export-start-date" value="<?php echo date('Y-m-01'); ?>" />
                    <span>to</span>
                    <input type="date" id="export-end-date" value="<?php echo date('Y-m-t'); ?>" />
                </div>

                <button type="button" class="button button-primary" id="export-data">
                    Download Report
                </button>
            </div>
        </div>

        <!-- Day Details Modal -->
        <div id="linen-day-modal" class="hhdl-modal" style="display: none;">
            <div class="hhdl-modal-content">
                <span class="close">&times;</span>
                <h2 class="modal-title"></h2>
                <div class="modal-body">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get calendar data
     */
    public function ajax_get_calendar_data() {
        // Verify nonce
        if (!check_ajax_referer('hhdl_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : date('Y-m');

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        // Get the start and end dates for the month
        $start_date = date('Y-m-01', strtotime($month));
        $end_date = date('Y-m-t', strtotime($month));

        // Build query
        $query = "SELECT
                    service_date,
                    COUNT(DISTINCT room_id) as room_count,
                    COUNT(DISTINCT linen_item_id) as item_types,
                    SUM(count) as total_items,
                    COUNT(DISTINCT submitted_by) as staff_count
                  FROM {$table_name}
                  WHERE service_date BETWEEN %s AND %s";

        $query_params = array($start_date, $end_date);

        if ($location_id > 0) {
            $query .= " AND location_id = %d";
            $query_params[] = $location_id;
        }

        $query .= " GROUP BY service_date";

        $results = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

        // Format results by date
        $calendar_data = array();
        foreach ($results as $row) {
            $calendar_data[$row['service_date']] = $row;
        }

        // Generate calendar HTML
        $calendar_html = $this->generate_calendar_html($month, $calendar_data);

        wp_send_json_success(array(
            'html' => $calendar_html,
            'data' => $calendar_data
        ));
    }

    /**
     * Generate calendar HTML
     */
    private function generate_calendar_html($month, $data) {
        $first_day = date('Y-m-01', strtotime($month));
        $last_day = date('Y-m-t', strtotime($month));
        $start_weekday = date('w', strtotime($first_day));
        $days_in_month = date('t', strtotime($first_day));

        ob_start();
        ?>
        <table class="hhdl-calendar-table">
            <thead>
                <tr>
                    <th>Sun</th>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                <?php
                // Empty cells for days before month starts
                for ($i = 0; $i < $start_weekday; $i++) {
                    echo '<td class="empty-day"></td>';
                }

                // Days of the month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = date('Y-m-d', strtotime($month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
                    $has_data = isset($data[$date]);
                    $day_data = $has_data ? $data[$date] : null;

                    $current_weekday = date('w', strtotime($date));
                    if ($current_weekday == 0 && $day > 1) {
                        echo '</tr><tr>';
                    }

                    $class = $has_data ? 'has-data' : 'no-data';
                    $today_class = ($date == date('Y-m-d')) ? ' today' : '';
                    ?>
                    <td class="calendar-day <?php echo $class . $today_class; ?>"
                        data-date="<?php echo esc_attr($date); ?>">
                        <div class="day-number"><?php echo $day; ?></div>
                        <?php if ($has_data) : ?>
                        <div class="day-summary">
                            <div class="room-count" title="Rooms">
                                <span class="material-symbols-outlined">bed</span>
                                <?php echo esc_html($day_data['room_count']); ?>
                            </div>
                            <div class="item-count" title="Total Items">
                                <span class="material-symbols-outlined">checkroom</span>
                                <?php echo esc_html($day_data['total_items']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php
                }

                // Empty cells for remaining days
                $remaining = 7 - (($days_in_month + $start_weekday) % 7);
                if ($remaining < 7) {
                    for ($i = 0; $i < $remaining; $i++) {
                        echo '<td class="empty-day"></td>';
                    }
                }
                ?>
                </tr>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler to get day details
     */
    public function ajax_get_day_details() {
        // Verify nonce
        if (!check_ajax_referer('hhdl_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (empty($date)) {
            wp_send_json_error('Date required');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        // Build query
        $query = "SELECT
                    lc.*,
                    u1.display_name as submitted_by_name,
                    u2.display_name as updated_by_name
                  FROM {$table_name} lc
                  LEFT JOIN {$wpdb->users} u1 ON lc.submitted_by = u1.ID
                  LEFT JOIN {$wpdb->users} u2 ON lc.last_updated_by = u2.ID
                  WHERE lc.service_date = %s";

        $query_params = array($date);

        if ($location_id > 0) {
            $query .= " AND lc.location_id = %d";
            $query_params[] = $location_id;
        }

        $query .= " ORDER BY lc.room_id, lc.linen_item_id";

        $results = $wpdb->get_results($wpdb->prepare($query, $query_params));

        // Get linen items configuration
        $linen_items = array();
        if ($location_id > 0) {
            $settings = get_option('hhdl_location_settings', array());
            $location_settings = isset($settings[$location_id]) ? $settings[$location_id] : array();
            $linen_items = isset($location_settings['linen_items']) ? $location_settings['linen_items'] : array();
        }

        // Group results by room
        $rooms = array();
        foreach ($results as $row) {
            if (!isset($rooms[$row->room_id])) {
                $rooms[$row->room_id] = array(
                    'room_id' => $row->room_id,
                    'submitted_by' => $row->submitted_by_name,
                    'submitted_at' => $row->submitted_at,
                    'items' => array(),
                    'total_count' => 0
                );
            }

            // Find item details
            $item_details = $this->find_linen_item($linen_items, $row->linen_item_id);

            $rooms[$row->room_id]['items'][] = array(
                'item_id' => $row->linen_item_id,
                'name' => $item_details ? $item_details['name'] : $row->linen_item_id,
                'shortcode' => $item_details ? $item_details['shortcode'] : '',
                'count' => $row->count
            );
            $rooms[$row->room_id]['total_count'] += $row->count;
        }

        // Generate HTML for modal
        ob_start();
        ?>
        <div class="hhdl-day-details">
            <?php if (empty($rooms)) : ?>
                <p>No linen counts recorded for this date.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Items</th>
                            <th>Total Count</th>
                            <th>Submitted By</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($room['room_id']); ?></strong></td>
                            <td>
                                <?php foreach ($room['items'] as $item) : ?>
                                    <span class="linen-item-badge">
                                        <?php echo esc_html($item['shortcode'] ?: $item['name']); ?>:
                                        <?php echo esc_html($item['count']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo esc_html($room['total_count']); ?></td>
                            <td><?php echo esc_html($room['submitted_by']); ?></td>
                            <td><?php echo esc_html(date('H:i', strtotime($room['submitted_at']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="day-totals">
                    <h3>Daily Totals</h3>
                    <?php
                    // Calculate totals by item type
                    $item_totals = array();
                    foreach ($rooms as $room) {
                        foreach ($room['items'] as $item) {
                            $key = $item['shortcode'] ?: $item['name'];
                            if (!isset($item_totals[$key])) {
                                $item_totals[$key] = 0;
                            }
                            $item_totals[$key] += $item['count'];
                        }
                    }
                    ?>
                    <div class="item-totals-grid">
                        <?php foreach ($item_totals as $item_name => $total) : ?>
                        <div class="total-item">
                            <span class="item-name"><?php echo esc_html($item_name); ?></span>
                            <span class="item-total"><?php echo esc_html($total); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="grand-total">
                        <strong>Total Rooms:</strong> <?php echo count($rooms); ?><br>
                        <strong>Total Items:</strong> <?php echo array_sum($item_totals); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'title' => 'Linen Counts for ' . date('F j, Y', strtotime($date))
        ));
    }

    /**
     * Find linen item details by ID
     */
    private function find_linen_item($items, $item_id) {
        foreach ($items as $item) {
            if ($item['id'] == $item_id) {
                return $item;
            }
        }
        return null;
    }

    /**
     * AJAX handler to export report
     */
    public function ajax_export_report() {
        // Verify nonce
        if (!check_ajax_referer('hhdl_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';

        global $wpdb;
        $table_name = $wpdb->prefix . 'hhlc_linen_counts';

        // Build query
        $query = "SELECT
                    lc.*,
                    u1.display_name as submitted_by_name,
                    u2.display_name as updated_by_name
                  FROM {$table_name} lc
                  LEFT JOIN {$wpdb->users} u1 ON lc.submitted_by = u1.ID
                  LEFT JOIN {$wpdb->users} u2 ON lc.last_updated_by = u2.ID
                  WHERE 1=1";

        $query_params = array();

        if (!empty($start_date) && !empty($end_date)) {
            $query .= " AND lc.service_date BETWEEN %s AND %s";
            $query_params[] = $start_date;
            $query_params[] = $end_date;
        }

        if ($location_id > 0) {
            $query .= " AND lc.location_id = %d";
            $query_params[] = $location_id;
        }

        $query .= " ORDER BY lc.service_date, lc.room_id, lc.linen_item_id";

        if (!empty($query_params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
        } else {
            $results = $wpdb->get_results($query, ARRAY_A);
        }

        // Generate CSV
        if ($format == 'csv') {
            $filename = 'linen-counts-' . date('Y-m-d') . '.csv';
            $csv_data = $this->generate_csv($results);

            wp_send_json_success(array(
                'filename' => $filename,
                'data' => base64_encode($csv_data),
                'mime' => 'text/csv'
            ));
        }
    }

    /**
     * Generate CSV from results
     */
    private function generate_csv($results) {
        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, array(
            'Date',
            'Room',
            'Item ID',
            'Count',
            'Submitted By',
            'Submitted At',
            'Updated By',
            'Updated At',
            'Booking Ref'
        ));

        // Data rows
        foreach ($results as $row) {
            fputcsv($output, array(
                $row['service_date'],
                $row['room_id'],
                $row['linen_item_id'],
                $row['count'],
                $row['submitted_by_name'],
                $row['submitted_at'],
                $row['updated_by_name'],
                $row['last_updated_at'],
                $row['booking_ref']
            ));
        }

        rewind($output);
        $csv_data = stream_get_contents($output);
        fclose($output);

        return $csv_data;
    }

    /**
     * Enqueue report assets
     */
    public function enqueue_report_assets($hook) {
        // Only load on reports page
        if (strpos($hook, 'linen-counts') === false) {
            return;
        }

        // Report styles
        wp_add_inline_style('wp-admin', $this->get_report_inline_styles());

        // Report JavaScript
        wp_add_inline_script('jquery', $this->get_report_inline_script(), 'after');
    }

    /**
     * Get inline styles for reports
     */
    private function get_report_inline_styles() {
        return '
        .hhdl-linen-report-wrap { padding: 20px; }
        .hhdl-report-filters { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; }
        .hhdl-report-filters form { display: flex; gap: 15px; align-items: center; }
        .tab-content { display: none; padding: 20px; background: #fff; border: 1px solid #ccd0d4; }
        .tab-content.active { display: block; }

        /* Calendar styles */
        .hhdl-calendar-container { max-width: 1200px; margin: 0 auto; }
        .hhdl-calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .hhdl-calendar-table { width: 100%; border-collapse: collapse; }
        .hhdl-calendar-table th { background: #f0f0f0; padding: 10px; text-align: center; font-weight: bold; }
        .hhdl-calendar-table td { border: 1px solid #ddd; padding: 5px; height: 100px; vertical-align: top; position: relative; }
        .calendar-day { cursor: pointer; transition: background 0.2s; }
        .calendar-day:hover { background: #f5f5f5; }
        .calendar-day.has-data { background: #e8f5e9; }
        .calendar-day.today { background: #fff3cd; }
        .day-number { font-weight: bold; margin-bottom: 5px; }
        .day-summary { font-size: 11px; }
        .day-summary div { display: inline-block; margin-right: 5px; }
        .empty-day { background: #f9f9f9; cursor: default; }

        /* Modal styles */
        .hhdl-modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .hhdl-modal-content { background: #fff; margin: 5% auto; padding: 20px; width: 80%; max-width: 800px; border-radius: 5px; }
        .hhdl-modal .close { float: right; font-size: 28px; cursor: pointer; }

        /* Day details styles */
        .linen-item-badge { display: inline-block; margin: 2px; padding: 2px 6px; background: #e0e0e0; border-radius: 3px; font-size: 12px; }
        .item-totals-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 15px 0; }
        .total-item { padding: 10px; background: #f5f5f5; border-radius: 3px; display: flex; justify-content: space-between; }
        .grand-total { margin-top: 15px; padding: 15px; background: #e3f2fd; border-radius: 3px; }

        /* Summary styles */
        .hhdl-summary-controls { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
        #summary-results { min-height: 300px; }
        ';
    }

    /**
     * Get inline JavaScript for reports
     */
    private function get_report_inline_script() {
        return "
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                $('#' + tab + '-view, #' + tab).addClass('active');
            });

            // Load calendar on page load
            loadCalendar();

            // Calendar navigation
            $('.prev-month').on('click', function() {
                var currentMonth = $('#linen-calendar').data('month');
                var newMonth = moment(currentMonth).subtract(1, 'month').format('YYYY-MM');
                $('#month-selector').val(newMonth);
                $('#linen-calendar').data('month', newMonth);
                $('.calendar-month-year').text(moment(newMonth).format('MMMM YYYY'));
                loadCalendar();
            });

            $('.next-month').on('click', function() {
                var currentMonth = $('#linen-calendar').data('month');
                var newMonth = moment(currentMonth).add(1, 'month').format('YYYY-MM');
                $('#month-selector').val(newMonth);
                $('#linen-calendar').data('month', newMonth);
                $('.calendar-month-year').text(moment(newMonth).format('MMMM YYYY'));
                loadCalendar();
            });

            // Load calendar data
            function loadCalendar() {
                var locationId = $('#location-selector').val() || 0;
                var month = $('#linen-calendar').data('month');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hhdl_get_linen_calendar_data',
                        nonce: '" . wp_create_nonce('hhdl_ajax_nonce') . "',
                        location_id: locationId,
                        month: month
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#linen-calendar').html(response.data.html);
                        }
                    }
                });
            }

            // Day click handler
            $(document).on('click', '.calendar-day.has-data', function() {
                var date = $(this).data('date');
                var locationId = $('#location-selector').val() || 0;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hhdl_get_linen_day_details',
                        nonce: '" . wp_create_nonce('hhdl_ajax_nonce') . "',
                        location_id: locationId,
                        date: date
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.modal-title').text(response.data.title);
                            $('.modal-body').html(response.data.html);
                            $('#linen-day-modal').show();
                        }
                    }
                });
            });

            // Modal close
            $('.close, .hhdl-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#linen-day-modal').hide();
                }
            });

            // Generate summary report
            $('#generate-summary').on('click', function() {
                var startDate = $('#summary-start-date').val();
                var endDate = $('#summary-end-date').val();
                var locationId = $('#location-selector').val() || 0;

                // Load summary data
                $('#summary-results').html('<p>Loading summary report...</p>');

                // This would call an AJAX handler to generate the summary
                // Implementation depends on specific requirements
            });

            // Export data
            $('#export-data').on('click', function() {
                var format = $('input[name=\"export-format\"]:checked').val();
                var startDate = $('#export-start-date').val();
                var endDate = $('#export-end-date').val();
                var locationId = $('#location-selector').val() || 0;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hhdl_export_linen_report',
                        nonce: '" . wp_create_nonce('hhdl_ajax_nonce') . "',
                        location_id: locationId,
                        start_date: startDate,
                        end_date: endDate,
                        format: format
                    },
                    success: function(response) {
                        if (response.success) {
                            // Create download link
                            var blob = new Blob([atob(response.data.data)], {type: response.data.mime});
                            var url = window.URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = response.data.filename;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                            document.body.removeChild(a);
                        }
                    }
                });
            });
        });
        ";
    }
}

// Initialize the reports
HHLC_Reports::instance();