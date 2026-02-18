# Dynamic UI Implementation Guide

## Overview

All admin and frontend files have been made **dynamic** using Laravel Blade configuration system. This means you can easily customize:

- **App Branding** (name, title, description)
- **UI Theme Colors** (primary, secondary, danger, success, etc.)
- **Features** (enable/disable any module)
- **Navigation Menu** (customize menu items and visibility)
- **Status Configurations** (customize status labels and colors)
- **Pagination Settings** (items per page)
- **User Permissions** (role-based access)

## Configuration File

**Location:** `config/crm.php`

This is the master configuration file that controls everything about the application's appearance and functionality.

### Key Configuration Sections

#### 1. Branding
```php
'branding' => [
    'app_name' => 'DTFTA CRM',
    'app_title' => 'DTFTA CRM - Ops Dashboard',
    'app_description' => 'Shopify Fulfillment Automation Dashboard',
    'logo_url' => '/assets/logo.png',
]
```

**How to customize:**
- Edit `config/crm.php`
- Or use environment variables (create a `config/crm.php` override)

#### 2. Theme Colors
```php
'theme' => [
    'primary_color' => '#667eea',
    'secondary_color' => '#764ba2',
    'danger_color' => '#e53e3e',
    'success_color' => '#38a169',
    // ... more colors
]
```

**How it works:**
- Colors are injected into Blade layouts as CSS variables
- All charts and UI elements use these colors dynamically
- Change in config = instant visual update

#### 3. Features Control
```php
'features' => [
    'dashboard' => [
        'enabled' => true,
        'show_charts' => true,
        'show_activity_log' => true,
        'default_metric_window' => 7,
    ],
    'orders' => [
        'enabled' => true,
        'items_per_page' => 10,
        'enable_bulk_actions' => true,
        'downloadable_report' => true,
    ],
    // ... more features
]
```

**How it works:**
- Each feature can be individually enabled/disabled
- Views check feature flags using `@if()` Blade directives
- Disable a feature = it won't appear in UI
- Change pagination, buttons, etc. without editing views

#### 4. Navigation Menu
```php
'navigation' => [
    [
        'label' => 'Dashboard',
        'route' => 'crm.dashboard',
        'icon' => 'nav-icon-dashboard',
        'visible' => true,
        'roles' => ['admin', 'manager', 'user'],
    ],
    // ... more items
]
```

**How to customize:**
- Add/remove menu items
- Control which roles see which items
- Change labels, routes, icons

#### 5. Status Configurations
```php
'statuses' => [
    'order_statuses' => [
        'new' => ['label' => 'New', 'color' => '#e0e7ff', 'text_color' => '#3730a3'],
        'shipped' => ['label' => 'Shipped', 'color' => '#dcfce7', 'text_color' => '#166534'],
        // ... more statuses
    ],
]
```

**How to customize:**
- Change label text
- Adjust colors
- Add new status types
- Views automatically use these colors

## Files Modified

### Views Updated

1. **layouts/app.blade.php**
   - Dynamic branding in header
   - Dynamic navigation menu
   - Dynamic theme colors via CSS variables
   - Conditional feature rendering

2. **crm/dashboard.blade.php**
   - Dynamic card visibility based on features
   - Dynamic chart colors from theme
   - Dynamic metrics window

3. **crm/orders.blade.php**
   - Dynamic status options from config
   - Dynamic pagination size
   - Dynamic report download feature
   - Dynamic status badge colors

4. **crm/shipping.blade.php**
   - Dynamic carrier list
   - Dynamic pagination
   - Conditional tracking/packing slip features
   - Feature flags for enable/disable

5. **crm/stores.blade.php**
   - Dynamic pagination
   - Conditional Shopify sync button
   - Feature-driven UI

### Controllers Updated

**app/Http/Controllers/CrmController.php**
- Added `prepareViewData()` method
- Each view method now passes dynamic configuration
- Filters navigation by user role
- Passes feature configs to views

### Helper Services

**app/Services/DynamicUIService.php**
- Helper class for common UI operations
- Methods for checking features, permissions, colors
- Can be used in controllers or views via `@php`

## How Views Use Configuration

### Example 1: Conditional Rendering
```blade
@if($features['dashboard']['show_charts'] ?? true)
    <!-- Charts section -->
@endif
```

### Example 2: Using Theme Colors
```blade
<style>
    :root {
        --primary-color: {{ $theme['primary_color'] ?? '#667eea' }};
    }
</style>
```

### Example 3: Dynamic Status Options
```blade
@forelse($orderStatuses ?? [] as $key => $status)
    <option value="{{ $key }}">{{ $status['label'] ?? ucfirst($key) }}</option>
@endforelse
```

### Example 4: Dynamic Pagination
```javascript
let pageSize = {{ $ordersConfig['items_per_page'] ?? 10 }};
```

## Global Configuration Object (JavaScript)

All configuration is accessible in JavaScript via `window.crmConfig`:

```javascript
window.crmConfig = {
    branding: @json($branding),
    theme: @json($theme),
    features: @json($features),
    statuses: @json($statuses),
    apiBaseUrl: '/api/v1',
    apiTimeout: 10000
}
```

**Usage in JavaScript:**
```javascript
// Check if feature is enabled
if (window.crmConfig.features.dashboard.show_charts) {
    initializeCharts();
}

// Get theme color
const primaryColor = window.crmConfig.theme.primary_color;

// Get status configuration
const orderStatuses = window.crmConfig.statuses.order_statuses;
```

## Customization Examples

### Example 1: Change App Branding
File: `config/crm.php`
```php
'branding' => [
    'app_name' => 'My Custom CRM',
    'app_title' => 'My Custom CRM - Dashboard',
    'app_description' => 'Custom Fulfillment System',
],
```

### Example 2: Disable Reports Feature
File: `config/crm.php`
```php
'features' => [
    'reports' => [
        'enabled' => false,  // Reports hidden from UI
        // ...
    ],
],
```

### Example 3: Add New Status Type
File: `config/crm.php`
```php
'statuses' => [
    'order_statuses' => [
        'pending_review' => [
            'label' => 'Pending Review',
            'color' => '#faf089',
            'text_color' => '#744210'
        ],
        // ... existing statuses
    ],
],
```

### Example 4: Change Pagination
File: `config/crm.php`
```php
'features' => [
    'orders' => [
        'items_per_page' => 25,  // Show 25 items instead of 10
    ],
],
```

### Example 5: Customize Navigation
File: `config/crm.php`
```php
'navigation' => [
    // Only show Dashboard to all users
    [
        'label' => 'Dashboard',
        'route' => 'crm.dashboard',
        'icon' => 'nav-icon-dashboard',
        'visible' => true,
        'roles' => ['admin', 'manager', 'user'],
    ],
    // Only show Reports to admins and managers
    [
        'label' => 'Reports',
        'route' => 'crm.reports',
        'icon' => 'nav-icon-reports',
        'visible' => true,
        'roles' => ['admin', 'manager'],  // User won't see this
    ],
],
```

## Usage in Controllers/Services

### Example - Using DynamicUIService

```php
<?php
namespace App\Http\Controllers;

use App\Services\DynamicUIService;

class MyController extends Controller
{
    public function index()
    {
        // Check if feature is enabled
        if (DynamicUIService::isFeatureEnabled('orders.downloadable_report')) {
            // Show report button
        }

        // Get filtered navigation by role
        $nav = DynamicUIService::getFilteredNavigation('manager');

        // Get status colors
        $colors = DynamicUIService::getStatusColors('shipped', 'order');

        // Check permissions
        if (DynamicUIService::hasPermission('view_reports', 'manager')) {
            // Show reports
        }
    }
}
```

## Environment Variables

You can override config values using environment variables:

```env
APP_NAME="My Custom App"
CRM_PRIMARY_COLOR="#FF6B6B"
CRM_REPORTS_ENABLED=false
```

Then in `config/crm.php`:
```php
'branding' => [
    'app_name' => env('APP_NAME', 'DTFTA CRM'),
],
'theme' => [
    'primary_color' => env('CRM_PRIMARY_COLOR', '#667eea'),
],
```

## Admin Settings Interface (Future)

For a future admin panel to modify settings without touching config files:

1. Create Settings model
2. Add admin controller to manage config
3. Load settings from database instead of config file
4. Cache for performance

## Performance Optimization

The configuration is loaded once per request and cached:

```php
// In controller
$config = config('crm');  // Cached after first access
```

For database-backed settings (future), implement caching:

```php
$config = Cache::remember('crm_config', 3600, function() {
    return database_settings();
});
```

## Testing

All dynamic features are testable:

```php
// Test feature flag
$this->assertTrue(config('crm.features.dashboard.enabled'));

// Test status colors
$status = DynamicUIService::getStatusColors('shipped', 'order');
$this->assertNotNull($status['color']);

// Test navigation filtering
$nav = DynamicUIService::getFilteredNavigation('user');
$this->assertCount(3, $nav);  // User sees 3 menu items
```

## Troubleshooting

### Issue: Config changes not showing
- **Solution:** Clear config cache with `php artisan config:clear`

### Issue: View variables undefined
- **Solution:** Check CrmController is passing all data with `prepareViewData()`

### Issue: Theme colors not applying
- **Solution:** Ensure layout has style tag with CSS variables

### Issue: Feature flags not working
- **Solution:** Use correct feature path in check: `features.orders.downloadable_report`

## Summary

✅ **All admin files are now dynamic**  
✅ **Single configuration file controls everything**  
✅ **No hardcoded values in views**  
✅ **Easy to customize without touching code**  
✅ **Theme colors, features, navigation all configurable**  
✅ **Helper service for common operations**  
✅ **JavaScript access via window.crmConfig**  
✅ **Ready for environment-based configuration**

Start by editing `config/crm.php` to customize your application!
