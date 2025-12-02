# Hotel Hub Module - Housekeeping - Spoilt Linen Count

A comprehensive soiled linen tracking module for the Hotel Hub Housekeeping system. Features touch-friendly counting interface, real-time multi-user synchronization, and detailed reporting capabilities.

## Description

The Spoilt Linen Count module extends the Hotel Hub Housekeeping Daily List by adding specialized linen tracking functionality directly into the room details modal. Housekeeping staff can quickly count and submit soiled linen items using an intuitive, touch-optimized interface designed for tablets and mobile devices.

## Features

- **Touch-Friendly Interface**: Large, easy-to-tap buttons optimized for tablets and mobile devices
- **Real-Time Synchronization**: Multiple users can work simultaneously with automatic updates via WordPress Heartbeat API
- **Smart Lock/Edit System**: Submitted counts are locked to prevent accidental changes, with supervisor-level edit permissions
- **Configurable Linen Items**: Customize linen items per location with shortcodes, sizes, pack quantities, and target stock levels
- **Calendar Reporting**: Visual calendar interface showing daily linen counts at a glance
- **Detailed Reports**: View room-by-room breakdowns with item totals and user attribution
- **CSV Export**: Export data for external analysis and recordkeeping
- **Permission-Based Access**: Integrates with Hotel Hub's Workforce Authentication system

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Required Plugins**:
  - [Hotel Hub App](https://github.com/jtricerolph/hotelhub-app)
  - [Hotel Hub Housekeeping Daily List](https://github.com/jtricerolph/hotelhubmodule-housekeeping-dailylist)

## Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Via FTP/SFTP

1. Extract the plugin ZIP file
2. Upload the `hotelhub-housekeeping-linencount` folder to `/wp-content/plugins/`
3. Navigate to **Plugins** in WordPress admin
4. Activate **Hotel Hub Module - Housekeeping - Spoilt Linen Count**

### Via Git

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/jtricerolph/hotelhub-housekeeping-linencount.git
```

Then activate the plugin through the WordPress admin panel.

## Configuration

### 1. Enable the Module

1. Navigate to **Hotel Hub → Settings**
2. Select the location you want to configure
3. Find the **Linen Count Configuration** section
4. Toggle **Enable Linen Count Module** to ON

### 2. Configure Linen Items

For each location, add linen items with these details:

- **Name**: Full descriptive name (e.g., "Pillow Case", "Bed Sheet")
- **Shortcode**: 2-4 character display code (e.g., "PC", "BS", "DBD")
- **Size**: Item size or specification (e.g., "King", "Queen", "Standard")
- **Pack Qty**: Number of items per pack (for inventory management)
- **Target Stock Qty**: Desired inventory level

**Example Configuration:**
| Name | Shortcode | Size | Pack Qty | Target Stock |
|------|-----------|------|----------|--------------|
| Pillow Case | PC | Standard | 10 | 500 |
| Bed Sheet | BS | Queen | 5 | 300 |
| Duvet Cover | DBD | King | 3 | 150 |

### 3. Set Permissions

The module uses three permission levels:

- **Access Linen Count Module**: View and submit counts (housekeeping staff)
- **Edit Submitted Linen Counts**: Modify locked counts (supervisors)
- **View Linen Count Reports**: Access reports section (managers)

Configure these in **Hotel Hub → Workforce Authentication → Permissions**.

## Usage

### For Housekeeping Staff

#### Submitting Linen Counts

1. Open the **Daily List** module
2. Click on a room card to open the room details modal
3. Scroll to the **Spoilt Linen Count** section
4. Use the up/down arrows to adjust counts:
   - **▲** button increases count by 1
   - **▼** button decreases count by 1 (minimum 0)
5. Items with changes are highlighted in yellow
6. Click **Submit Count** to save
7. The interface locks automatically after submission

#### Editing Submitted Counts

If you have edit permissions:

1. Open a room with submitted counts
2. Click the **Edit** button
3. Make necessary adjustments
4. Click **Submit Count** to save changes
5. Your edit will be recorded with timestamp and user attribution

### Multi-User Scenarios

The module handles multiple users gracefully:

**Scenario 1: Sequential Use**
- User A submits counts for Room 101
- User B opens Room 101 and sees locked counts with User A's attribution
- User B can view but not modify without edit permissions

**Scenario 2: Concurrent Viewing**
- User A and User B both have Room 101 modal open
- User A submits counts
- User B receives automatic notification and sees updated counts
- Interface updates without page reload

**Scenario 3: Supervisor Edit**
- Housekeeper submits incorrect count
- Supervisor opens same room
- Supervisor clicks Edit (only available to supervisors)
- Makes correction and resubmits
- Original submission time preserved, edit time added

### Reports

#### Calendar View

1. Navigate to **Hotel Hub → Reports → Linen Count Report**
2. Select location and month
3. Calendar displays:
   - **Green dates**: Have linen count data
   - **Yellow date**: Today
   - Room count and total items shown for each date
4. Click any date to view detailed breakdown

#### Detailed Day View

When you click a calendar date, you'll see:

- **Room-by-room breakdown**: Each room's submitted counts
- **Item details**: Individual linen item counts per room
- **User attribution**: Who submitted counts and when
- **Daily totals by item type**: Aggregate counts for each linen item
- **Grand totals**: Total rooms and total items for the day

#### Exporting Data

1. Go to **Reports → Linen Count Report → Export Data** tab
2. Select date range
3. Choose format (CSV recommended)
4. Click **Download Report**

The export includes:
- Service date
- Room ID
- Linen item details
- Counts
- Submitted by (user name)
- Submission timestamp
- Last updated by (if edited)
- Update timestamp
- Booking reference

## Technical Details

### Database Schema

The plugin creates one table: `wp_hhlc_linen_counts`

```sql
CREATE TABLE wp_hhlc_linen_counts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id BIGINT(20) UNSIGNED NOT NULL,
    room_id VARCHAR(50) NOT NULL,
    linen_item_id VARCHAR(50) NOT NULL,
    count INT(11) UNSIGNED NOT NULL DEFAULT 0,
    submitted_by BIGINT(20) UNSIGNED NOT NULL,
    submitted_at DATETIME NOT NULL,
    service_date DATE NOT NULL,
    booking_ref VARCHAR(100),
    is_locked BOOLEAN DEFAULT TRUE,
    last_updated_by BIGINT(20) UNSIGNED,
    last_updated_at DATETIME,
    UNIQUE KEY unique_room_item_date (location_id, room_id, linen_item_id, service_date)
);
```

### Hooks and Filters

#### Actions

- `hhdl_modal_sections` - Adds linen count section to room modal
- `heartbeat_received` - Processes real-time updates
- `hha_register_reports` - Registers linen report

#### Filters

- `hha_register_modules` - Registers module with Hotel Hub
- `wfa_register_permissions` - Registers permissions
- `heartbeat_settings` - Configures 30-second interval

### AJAX Endpoints

- `hhlc_submit_linen_count` - Submit or update counts
- `hhlc_get_linen_counts` - Retrieve room counts
- `hhlc_unlock_linen_count` - Unlock for editing
- `hhlc_get_linen_calendar_data` - Calendar view data
- `hhlc_get_linen_day_details` - Detailed day information
- `hhlc_export_linen_report` - Export report data

## Troubleshooting

### Linen Section Not Appearing

**Possible Causes:**
- Module not enabled for the location
- No linen items configured
- User lacks required permissions
- Plugin dependencies not met

**Solutions:**
1. Check module is enabled in settings
2. Add at least one linen item
3. Verify user has `hhlc_access_module` permission
4. Ensure Hotel Hub App and Daily List module are active

### Counts Not Saving

**Possible Causes:**
- JavaScript errors
- AJAX nonce expired
- Database error

**Solutions:**
1. Check browser console for JavaScript errors
2. Refresh the page to get new nonce
3. Enable WordPress debug mode and check `wp-content/debug.log`

### Real-Time Sync Not Working

**Possible Causes:**
- WordPress Heartbeat disabled
- Server-side caching interfering
- Transient storage not working

**Solutions:**
1. Verify Heartbeat is enabled: `add_filter('heartbeat_settings', function($s) { return $s; });`
2. Disable aggressive caching for admin/frontend
3. Check transient storage is working properly

### Enable Debug Mode

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `wp-content/debug.log`

## Development

### File Structure

```
hotelhub-housekeeping-linencount/
├── hotelhub-housekeeping-linencount.php  # Main plugin file
├── admin/
│   └── class-hhlc-reports.php            # Reports functionality
├── includes/
│   ├── class-hhlc-core.php               # Core module class
│   ├── class-hhlc-settings.php           # Settings management
│   ├── class-hhlc-ajax.php               # AJAX handlers
│   ├── class-hhlc-display.php            # Modal display
│   └── class-hhlc-heartbeat.php          # Real-time sync
├── assets/
│   ├── css/
│   │   ├── linen-count.css               # Frontend styles
│   │   └── admin.css                     # Admin styles
│   └── js/
│       └── linen-count.js                # Frontend functionality
└── README.md
```

### Extending the Module

**Add Custom Item Types:**

```php
add_filter('hhlc_linen_item_types', function($types) {
    $types['custom_type'] = 'Custom Type';
    return $types;
});
```

**Custom Validation:**

```php
add_filter('hhlc_validate_linen_count', function($is_valid, $counts) {
    // Your validation logic
    return $is_valid;
}, 10, 2);
```

**Modify Report Data:**

```php
add_filter('hhlc_report_data', function($data, $location_id, $date_range) {
    // Modify report data
    return $data;
}, 10, 3);
```

## Changelog

### 1.0.0 - 2024-12-02
- Initial release
- Touch-friendly counting interface
- Real-time multi-user synchronization
- Calendar-based reporting
- CSV export functionality
- Permission-based access control

## Support

For issues, feature requests, or contributions:

- **Issues**: [GitHub Issues](https://github.com/jtricerolph/hotelhub-housekeeping-linencount/issues)
- **Documentation**: [Wiki](https://github.com/jtricerolph/hotelhub-housekeeping-linencount/wiki)

## License

GPL v2 or later

## Credits

Developed by JTR for the Hotel Hub ecosystem.

## Related Plugins

- [Hotel Hub App](https://github.com/jtricerolph/hotelhub-app) - Core Hotel Hub system
- [Hotel Hub Housekeeping Daily List](https://github.com/jtricerolph/hotelhubmodule-housekeeping-dailylist) - Room task management
