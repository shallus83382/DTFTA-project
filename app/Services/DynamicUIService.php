<?php

namespace App\Services;

/**
 * Dynamic UI Configuration Helper
 * Centralizes all dynamic UI configurations and provides utility methods
 */
class DynamicUIService
{
    /**
     * Get full configuration
     */
    public static function getConfig()
    {
        return config('crm');
    }

    /**
     * Get theme colors by status
     */
    public static function getStatusColors($status, $type = 'order')
    {
        $config = self::getConfig();
        $statuses = $config['statuses'] ?? [];
        
        if ($type === 'order') {
            return $statuses['order_statuses'][$status] ?? $statuses['order_statuses']['pending'];
        } elseif ($type === 'shipment') {
            return $statuses['shipment_statuses'][$status] ?? $statuses['shipment_statuses']['pending'];
        }
        
        return [];
    }

    /**
     * Get navigation filtered by user role
     */
    public static function getFilteredNavigation($userRole = 'user')
    {
        $config = self::getConfig();
        $navigation = $config['navigation'] ?? [];
        
        return array_filter($navigation, function ($item) use ($userRole) {
            $roles = $item['roles'] ?? [];
            return in_array($userRole, $roles);
        });
    }

    /**
     * Check if feature is enabled
     */
    public static function isFeatureEnabled($featurePath)
    {
        $config = self::getConfig();
        $features = $config['features'] ?? [];
        
        // Support dot notation like: dashboard.show_charts
        $parts = explode('.', $featurePath);
        $current = $features;
        
        foreach ($parts as $part) {
            if (isset($current[$part])) {
                $current = $current[$part];
            } else {
                return false;
            }
        }
        
        return $current === true || (is_array($current) && ($current['enabled'] ?? true));
    }

    /**
     * Get all enabled features
     */
    public static function getEnabledFeatures()
    {
        $config = self::getConfig();
        $features = $config['features'] ?? [];
        
        $enabled = [];
        foreach ($features as $key => $feature) {
            if (is_array($feature) && ($feature['enabled'] ?? true)) {
                $enabled[$key] = $feature;
            }
        }
        
        return $enabled;
    }

    /**
     * Format status label
     */
    public static function formatStatusLabel($status, $type = 'order')
    {
        $colors = self::getStatusColors($status, $type);
        return $colors['label'] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Get theme color
     */
    public static function getThemeColor($colorKey)
    {
        $config = self::getConfig();
        $theme = $config['theme'] ?? [];
        
        return $theme[$colorKey] ?? '#667eea';
    }

    /**
     * Get branding info
     */
    public static function getBranding($key = null)
    {
        $config = self::getConfig();
        $branding = $config['branding'] ?? [];
        
        if ($key) {
            return $branding[$key] ?? null;
        }
        
        return $branding;
    }

    /**
     * Check user permission
     */
    public static function hasPermission($permission, $userRole = 'user')
    {
        $config = self::getConfig();
        $users = $config['users'] ?? [];
        
        if ($userRole === 'admin') {
            return true;
        }
        
        if ($userRole === 'manager') {
            $managerActions = $users['manager_actions'] ?? [];
            return in_array($permission, $managerActions);
        }
        
        return false;
    }

    /**
     * Get available statuses
     */
    public static function getAvailableStatuses($type = 'order')
    {
        $config = self::getConfig();
        $statuses = $config['statuses'] ?? [];
        
        if ($type === 'order') {
            return $statuses['order_statuses'] ?? [];
        } elseif ($type === 'shipment') {
            return $statuses['shipment_statuses'] ?? [];
        }
        
        return [];
    }
}
