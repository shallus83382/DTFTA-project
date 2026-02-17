<?php

/**
 * DTFTA CRM Configuration
 * Dynamic settings for frontend and admin interfaces
 */

return [
    // Application branding
    'branding' => [
        'app_name' => env('APP_NAME', 'DTFTA CRM'),
        'app_title' => 'DTFTA CRM - Ops Dashboard',
        'app_description' => 'Shopify Fulfillment Automation Dashboard',
        'app_version' => '1.0.0',
        'logo_url' => '/assets/logo.png',
    ],

    // UI Theming
    'theme' => [
        'primary_color' => '#667eea',
        'secondary_color' => '#764ba2',
        'danger_color' => '#e53e3e',
        'success_color' => '#38a169',
        'warning_color' => '#ed8936',
        'info_color' => '#3182ce',
    ],

    // Features and modules
    'features' => [
        'dashboard' => [
            'enabled' => true,
            'show_charts' => true,
            'show_activity_log' => true,
            'default_metric_window' => 7, // days
        ],
        'orders' => [
            'enabled' => true,
            'items_per_page' => 10,
            'enable_bulk_actions' => true,
            'downloadable_report' => true,
        ],
        'shipping' => [
            'enabled' => true,
            'items_per_page' => 10,
            'carriers' => ['usps', 'ups', 'fedex', 'other'],
            'enable_tracking' => true,
            'enable_packing_slip' => true,
        ],
        'stores' => [
            'enabled' => true,
            'items_per_page' => 10,
            'enable_shopify_sync' => true,
        ],
        'reports' => [
            'enabled' => true,
            'enable_export' => true,
            'available_reports' => ['sales', 'fulfillment', 'inventory', 'performance'],
        ],
        'settings' => [
            'enabled' => true,
            'allow_profile_edit' => true,
            'allow_password_change' => true,
            'allow_notification_settings' => true,
        ],
        'notifications' => [
            'enabled' => true,
            'enable_email' => true,
            'enable_in_app' => true,
        ],
    ],

    // Navigation menu items
    'navigation' => [
        [
            'label' => 'Dashboard',
            'route' => 'crm.dashboard',
            'icon' => 'nav-icon-dashboard',
            'visible' => true,
            'roles' => ['admin', 'manager', 'user'],
        ],
        [
            'label' => 'Orders / Jobs',
            'route' => 'crm.orders',
            'icon' => 'nav-icon-orders',
            'visible' => true,
            'roles' => ['admin', 'manager', 'user'],
        ],
        [
            'label' => 'Shipping',
            'route' => 'crm.shipping',
            'icon' => 'nav-icon-shipping',
            'visible' => true,
            'roles' => ['admin', 'manager', 'user'],
        ],
        [
            'label' => 'Stores',
            'route' => 'crm.stores',
            'icon' => 'nav-icon-stores',
            'visible' => true,
            'roles' => ['admin', 'manager'],
        ],
        [
            'label' => 'Reports',
            'route' => 'crm.reports',
            'icon' => 'nav-icon-reports',
            'visible' => true,
            'roles' => ['admin', 'manager'],
        ],
    ],

    // Status configurations
    'statuses' => [
        'order_statuses' => [
            'new' => ['label' => 'New', 'color' => '#e0e7ff', 'text_color' => '#3730a3'],
            'pending' => ['label' => 'Pending', 'color' => '#e0e7ff', 'text_color' => '#3730a3'],
            'artwork_needed' => ['label' => 'Artwork Needed', 'color' => '#fed7aa', 'text_color' => '#92400e'],
            'in_production' => ['label' => 'In Production', 'color' => '#e9d5ff', 'text_color' => '#6b21a8'],
            'shipped' => ['label' => 'Shipped', 'color' => '#dcfce7', 'text_color' => '#166534'],
            'exception' => ['label' => 'Exception', 'color' => '#fee2e2', 'text_color' => '#991b1b'],
            'cancelled' => ['label' => 'Cancelled', 'color' => '#f3f4f6', 'text_color' => '#4b5563'],
        ],
        'shipment_statuses' => [
            'pending' => ['label' => 'Pending', 'color' => '#fed7aa'],
            'in_transit' => ['label' => 'In Transit', 'color' => '#e9d5ff'],
            'delivered' => ['label' => 'Delivered', 'color' => '#dcfce7'],
            'failed' => ['label' => 'Failed', 'color' => '#fee2e2'],
        ],
    ],

    // Default pagination
    'pagination' => [
        'default_per_page' => 10,
        'max_per_page' => 100,
    ],

    // API Settings
    'api' => [
        'base_url' => env('API_BASE_URL', '/api/v1'),
        'timeout' => 10000, // milliseconds
    ],

    // Default user role
    'default_role' => env('DEFAULT_USER_ROLE', 'user'),

    // User management
    'users' => [
        'admin_only_actions' => ['user_management', 'activity_logs', 'system_settings'],
        'manager_actions' => ['view_reports', 'manage_stores', 'view_activity_logs'],
    ],
];
