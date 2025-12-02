# Developer Notes - Common Mistakes When Creating Hotel Hub Modules

## ⚠️ CRITICAL #1: Module Registration

**WRONG WAY** (treats as array):
```php
public function register_module($modules) {
    $modules['module_id'] = array(
        'id' => 'module_id',
        'name' => 'Module Name',
        // ...
    );
    return $modules;
}
```

**RIGHT WAY** (uses object method + getter methods):
```php
public function register_module($modules_manager) {
    $modules_manager->register_module($this);
}

// Add these getter methods:
public function get_id() { return 'module_id'; }
public function get_name() { return __('Module Name', 'textdomain'); }
public function get_description() { return __('Description', 'textdomain'); }
public function get_department() { return 'department_name'; }
public function get_icon() { return 'material_icon'; }
public function get_color() { return '#hexcolor'; }
public function get_version() { return PLUGIN_VERSION; }
public function get_requires() { return array('required_module'); }
public function get_callback() { return array($this, 'render_page'); }
public function get_settings_callback() { return array(Settings::instance(), 'render'); }
public function get_supports() { return array('multi_location', 'reports'); }
```

**Error You'll Get:**
```
Fatal error: Cannot use object of type HHA_Modules as array
```

## ⚠️ CRITICAL #2: WFA Permissions Registration

**WRONG WAY** (treats as array):
```php
public function register_permissions($permissions) {
    $permissions['permission_slug'] = array(
        'label' => 'Permission Label',
        'description' => 'Description',
        'department' => 'department_name'
    );
    return $permissions;
}
```

**RIGHT WAY** (uses object method):
```php
public function register_permissions($permissions_manager) {
    $permissions_manager->register_permission(
        'permission_slug',
        __('Permission Label', 'textdomain'),
        __('Permission Description', 'textdomain'),
        'Module Name - Category'
    );
    // No return needed
}
```

**Error You'll Get:**
```
Fatal error: Cannot use object of type WFA_Permissions as array
```

## Plugin Dependency Naming

**Check these match your actual plugin directories:**

```php
// Plugin header (line ~13)
* Requires Plugins: hotel-hub-app, hotelhubmodule-housekeeping-dailylist

// Dependency array (check_required_plugins method)
$required_plugins = array(
    'hotel-hub-app/hotel-hub-app.php' => 'Hotel Hub App',
    'hotelhubmodule-housekeeping-dailylist/hotelhubmodule-housekeeping-dailylist.php' => 'Daily List'
);

// Activation checks
if (!is_plugin_active('hotel-hub-app/hotel-hub-app.php')) { ... }
```

**Common mistakes:**
- Using `hotelhub-app` instead of `hotel-hub-app` (hyphen placement)
- Mismatching directory names between local dev and production

## Module Registration Pattern

**Correct pattern for Hotel Hub modules:**

```php
public function register_module($modules_manager) {
    $modules_manager->register_module($this);
    // OR for simple array-based registration:
    $modules['module_id'] = array(
        'id' => 'module_id',
        'name' => 'Module Name',
        // ...
    );
    return $modules;
}
```

## File Loading Order

**DON'T check for dependencies in load_dependencies():**

```php
// WRONG - prevents files from loading
private function load_dependencies() {
    if (!function_exists('hha') || !class_exists('HHDL_Core')) {
        return; // Files never get loaded!
    }
    require_once PLUGIN_DIR . 'includes/class-core.php';
}

// RIGHT - just load the files
private function load_dependencies() {
    require_once PLUGIN_DIR . 'includes/class-core.php';
    require_once PLUGIN_DIR . 'includes/class-ajax.php';
    // etc...
}
```

Dependency checking should happen in:
1. `check_required_plugins()` method (runtime check)
2. `activate()` method (activation check)
3. `init()` method (before initializing components)

## Database Table Naming

**Convention:**
- Use prefix: `wp_{plugin_prefix}_table_name`
- Example: `wp_hhlc_linen_counts`

**In create_tables():**
```php
$table_name = $wpdb->prefix . 'hhlc_linen_counts'; // Good
$table_name = 'wp_linen_counts'; // Bad - hardcoded prefix
```

## Constants Definition

**Standard constants to define:**
```php
define('PLUGIN_VERSION', '1.0.0');
define('PLUGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PLUGIN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PLUGIN_PLUGIN_BASENAME', plugin_basename(__FILE__));
```

## Hook Priorities

**WordPress plugin loading order:**
```php
// Hotel Hub loads at priority 10 (default)
add_action('plugins_loaded', array($this, 'init'), 15); // Load AFTER Hotel Hub

// If you need to load before Hotel Hub:
add_action('plugins_loaded', array($this, 'init'), 5);
```

## AJAX Nonce Naming

**Be consistent with nonce names:**
```php
// In localize_script
'nonce' => wp_create_nonce('plugin_ajax_nonce')

// In AJAX handler
check_ajax_referer('plugin_ajax_nonce', 'nonce', false)
```

## Text Domain

**Use consistently throughout:**
```php
// Plugin header
* Text Domain: hhlc

// In code
__('Translatable String', 'hhlc')  // Good
__('Translatable String', 'different-domain')  // Bad
```

## Checklist for New Modules

Before pushing to production:



## ⚠️ CRITICAL #3: Reports Registration

**Reports use add_report() OBJECT METHOD:**

```php
public function register_report($reports) {
    $reports->add_report(
        'report-id',
        array(
            'title' => __('Report Title', 'textdomain'),
            'description' => __('Report Description', 'textdomain'),
            'capability' => 'view_reports',
            'callback' => array($this, 'render_report_page'),
            'icon' => 'icon_name',
            'department' => 'department_name'
        )
    );
}
```

**Error You'll Get if using array syntax:**
```
Fatal error: Cannot use object of type HHA_Reports as array
```

## ⚠️ CRITICAL #4: Required Getter Method - get_config()

**ALL modules MUST implement get_config() with ALL fields:**

```php
public function get_config() {
    return array(
        'id' => $this->get_id(),
        'name' => $this->get_name(),
        'description' => $this->get_description(),
        'department' => $this->get_department(),
        'icon' => $this->get_icon(),
        'color' => $this->get_color(),
        'version' => $this->get_version()
    );
}
```

**Errors You'll Get:**
```
HHA_Modules: Module must implement get_config() method
HHA_Modules: Module config missing required field: id
HHA_Modules: Module config missing required field: name
```

Must include: id, name, description, department, icon, color, version

