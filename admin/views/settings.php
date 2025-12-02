<?php
/**
 * Settings Page Template
 *
 * @package HotelHub_Housekeeping_LinenCount
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get locations from Hotel Hub
$locations = array();
if (function_exists('hha') && method_exists(hha()->hotels, 'get_all_locations')) {
    $locations = hha()->hotels->get_all_locations();
}

// Get current settings
$all_settings = get_option(HHLC_Settings::OPTION_NAME, array());
?>

<div class="wrap hhlc-settings-wrap">
    <h1><?php _e('Linen Count Settings', 'hhlc'); ?></h1>

    <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'hhlc'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="hhlc-settings-form">
        <input type="hidden" name="action" value="hhlc_save_settings">
        <?php wp_nonce_field('hhlc_save_settings', 'hhlc_settings_nonce'); ?>

        <div class="hhlc-locations-list">
            <?php if (empty($locations)): ?>
                <div class="notice notice-warning">
                    <p><?php _e('No locations found. Please configure locations in Hotel Hub first.', 'hhlc'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($locations as $location): ?>
                    <?php
                    $location_id = $location['id'];
                    $location_settings = isset($all_settings[$location_id]) ? $all_settings[$location_id] : array();
                    $enabled = isset($location_settings['enabled']) ? $location_settings['enabled'] : true;
                    ?>

                    <div class="hhlc-location-card">
                        <div class="hhlc-location-header">
                            <h2><?php echo esc_html($location['name']); ?></h2>
                            <label class="hhlc-switch">
                                <input type="checkbox"
                                       name="hhlc_location_settings[<?php echo $location_id; ?>][enabled]"
                                       value="1"
                                       <?php checked($enabled, true); ?>>
                                <span class="hhlc-slider"></span>
                            </label>
                        </div>

                        <div class="hhlc-location-settings" style="<?php echo $enabled ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
                            <?php
                            // Render the settings section for this location
                            HHLC_Settings::render_settings_section_public($location_id);
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php submit_button(__('Save Settings', 'hhlc')); ?>
    </form>
</div>

<style>
.hhlc-settings-wrap {
    max-width: 1400px;
}

.hhlc-locations-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-top: 20px;
}

.hhlc-location-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.hhlc-location-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #c3c4c7;
    background: #f6f7f7;
}

.hhlc-location-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.hhlc-location-settings {
    padding: 24px;
}

/* Toggle Switch */
.hhlc-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.hhlc-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.hhlc-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}

.hhlc-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .hhlc-slider {
    background-color: #10b981;
}

input:checked + .hhlc-slider:before {
    transform: translateX(26px);
}

/* Linen Items Settings Section */
.hhlc-settings-section {
    max-width: 1200px;
}

.hhlc-settings-section h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

.hhlc-settings-section .description {
    margin: 0 0 16px 0;
    font-size: 12px;
    color: #646970;
}

.hhlc-linen-items-list {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.hhlc-linen-item-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: move;
}

.hhlc-drag-handle {
    cursor: grab;
    color: #666;
    font-size: 18px;
    user-select: none;
}

.hhlc-linen-item-row input[type="text"],
.hhlc-linen-item-row input[type="number"] {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
    background: white;
    font-size: 13px;
}

.hhlc-linen-item-row .linen-item-name {
    flex: 2;
    min-width: 150px;
}

.hhlc-linen-item-row .linen-item-shortcode {
    width: 80px;
}

.hhlc-linen-item-row .linen-item-size {
    width: 100px;
}

.hhlc-linen-item-row .linen-item-pack-qty,
.hhlc-linen-item-row .linen-item-target-stock {
    width: 80px;
}

.hhlc-remove-linen-item {
    padding: 6px 10px;
    min-width: 36px;
    font-size: 16px;
    line-height: 1;
    color: #dc2626;
    border-color: #dc2626;
    background: white;
}

.hhlc-remove-linen-item:hover {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}

.hhlc-add-linen-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.hhlc-add-linen-item .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.hhlc-sortable-placeholder {
    background: #e0f2fe;
    border: 2px dashed #3b82f6;
    border-radius: 4px;
    height: 50px;
    margin-bottom: 8px;
}
</style>
