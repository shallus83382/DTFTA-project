# Print-area canvas preview (CRM admin)

Admins can pick a product and placement, see the dashed print-area boundary over the
product image exactly as the Shopify customizer draws it (DesignCanvas.tsx), and adjust
its size and position (drag, resize, or type values) before saving.

## Deploy

1. Copy these files into the Laravel project at the same paths (5 new, 10 replaced).
2. Run `php artisan migrate`. This adds 4 nullable columns to `print_areas`.
3. Run `php artisan view:clear`.

The Shopify app needs no changes. It already reads `design_position_x/y` and
`design_area_width/height` in `getDesignRegionFromPrintArea()`; Laravel just never sent them.

## How it works

- New columns `design_position_x`, `design_position_y`, `design_area_width` and
  `design_area_height` hold the region in the customizer's logical 500 x 500 space.
- `DtftaProductResource` sends them (as strings, which the app requires) only when all
  four are set. Print areas without a saved region behave exactly as before.
- The preview copies the app: canvas sizing (676 x 487 desktop, 860 x 520 stacked tablet,
  327 x 280 phone), contain-fit background, `#374151` 2px `[8, 8]` dashed rect, a 2px clip
  bleed, and the background lookup (colour mockup for the placement first, then the print
  area image).
- With no saved region, the preview shows the box the app calculates from Area
  Width/Height and Position X/Y. It is a line-for-line port of `print-area-dimensions.ts`,
  checked against the app's TypeScript on 20,000 random print areas with identical results.

## Where it appears

- **Print Areas > Edit / Add.** A "Customizer canvas preview" card. The region is saved
  with the form.
- **Print Areas > Preview on Products** (`/crm/print-areas/designer`). Pick a product,
  switch placements with tabs, and save each one (AJAX). Linked from the print-area list
  (per row), the edit page, the detail page, and the product detail page.
- **Print Area detail page.** Shows the saved region, or "Calculated from size & position".

## Notes

- Print areas are shared between products. When an area is used by more than one product,
  the preview says so, because saving changes it for all of them.
- The preview tries the `.png` asset the app loads first, then the `.jpg` listed in
  `config/assets.php`.
- "Use calculated" clears the custom region, and the app goes back to calculating it.
- Fabric 7.2.0 (the app's version) loads from cdn.jsdelivr.net on the preview pages only.
- Small fixes along the way:
  - `PrintArea::products()` was missing its `BelongsToMany` import.
  - The edit page's script threw on every load (it referenced a removed `#imageInput`).
  - The Add page now keeps the selected image after a validation error.
  - The resource no longer errors when a print area has no image set.
