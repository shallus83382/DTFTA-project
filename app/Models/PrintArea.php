<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PrintArea extends Model
{
    /** Logical editor size used by the Shopify customizer canvas (DESIGN_LOGICAL_CANVAS_SIZE). */
    public const DESIGN_LOGICAL_SIZE = 500;

    /** Smallest region edge (logical units) the admin editor allows. */
    public const DESIGN_MIN_EDGE = 10;

    public const DESIGN_REGION_FIELDS = [
        'design_position_x',
        'design_position_y',
        'design_area_width',
        'design_area_height',
    ];

    protected $fillable = [
        'title',
        'area_width',
        'area_height',
        'unit',
        'position_x',
        'position_y',
        'design_position_x',
        'design_position_y',
        'design_area_width',
        'design_area_height',
        'tshirt_size',
        'display_order',
        'is_active',
        'price',
        'images'
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'design_position_x' => 'decimal:2',
        'design_position_y' => 'decimal:2',
        'design_area_width' => 'decimal:2',
        'design_area_height' => 'decimal:2',
    ];

    /** True when the admin has saved an explicit canvas region for the customizer. */
    public function hasDesignRegion(): bool
    {
        foreach (self::DESIGN_REGION_FIELDS as $field) {
            if ($this->{$field} === null || $this->{$field} === '') {
                return false;
            }
        }

        return (float) $this->design_area_width > 0 && (float) $this->design_area_height > 0;
    }

    /**
     * Clamp a submitted region into the 0-500 logical canvas, or return all-null
     * when the region is incomplete (the customizer then falls back to deriving it).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, float|null>
     */
    public static function normalizeDesignRegion(array $input): array
    {
        $values = [];
        foreach (self::DESIGN_REGION_FIELDS as $field) {
            $raw = $input[$field] ?? null;
            if ($raw === null || $raw === '' || !is_numeric($raw)) {
                return array_fill_keys(self::DESIGN_REGION_FIELDS, null);
            }
            $values[$field] = (float) $raw;
        }

        $size = self::DESIGN_LOGICAL_SIZE;
        $width = min($size, max(self::DESIGN_MIN_EDGE, $values['design_area_width']));
        $height = min($size, max(self::DESIGN_MIN_EDGE, $values['design_area_height']));
        $left = min($size - $width, max(0, $values['design_position_x']));
        $top = min($size - $height, max(0, $values['design_position_y']));

        return [
            'design_position_x' => round($left, 2),
            'design_position_y' => round($top, 2),
            'design_area_width' => round($width, 2),
            'design_area_height' => round($height, 2),
        ];
    }

    // PrintArea → Products (M:N)
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_print_areas',
            'print_area_id',
            'product_id'
        )
        ->using(ProductPrintArea::class)
        ->withTimestamps();
    }
}
