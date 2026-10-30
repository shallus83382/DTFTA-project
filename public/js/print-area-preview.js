/**
 * DTFTA CRM — print-area canvas preview.
 *
 * Reproduces the Shopify app's product customizer canvas (dtfta-remix-app
 * app/components/DesignCanvas.tsx) so admins can see and adjust the dashed
 * print-area boundary exactly as merchants will see it:
 *
 *  - Region values live in a logical 500 × 500 space (DESIGN_LOGICAL_CANVAS_SIZE)
 *    and are stretched non-uniformly onto the canvas (scaleX = w/500, scaleY = h/500).
 *  - Canvas size follows DesignCanvas `fillWidth` sizing (0.72 aspect, 280–860 wide,
 *    280–520 high), so the same region looks different at different widths.
 *  - Product image is contain-fitted and centered; dashed rect is stroke #374151,
 *    width 2, dash [8, 8]; artwork clip is padded by DESIGN_REGION_EDGE_BLEED_PX (2).
 *  - When a print area has no saved region, the region is derived exactly like
 *    app/lib/product-customize/print-area-dimensions.ts → getDesignRegionFromPrintArea.
 *
 * Requires Fabric 7 UMD (window.fabric), same major as the app (fabric ^7.2.0).
 */
(function (global) {
  'use strict';

  // ---------------------------------------------------------------------------
  // Constants copied from the Shopify app
  // ---------------------------------------------------------------------------
  var LOGICAL = 500; // DESIGN_LOGICAL_CANVAS_SIZE / CANVAS_SIZE
  var EDGE_BLEED = 2; // DESIGN_REGION_EDGE_BLEED_PX
  var MIN_CANVAS_SIZE = 280;
  var MIN_CANVAS_HEIGHT = 280;
  var CANVAS_ASPECT_RATIO = 0.72;
  var MAX_CANVAS_WIDTH = 860;
  var MAX_CANVAS_HEIGHT = 520;
  var REGION_STROKE = '#374151';
  var DEFAULT_DPI = 150;
  var INCH_MAX = 48, MM_MAX = 280, CM_MAX = 90;
  var DEFAULT_REF_W_IN = 14, DEFAULT_REF_H_IN = 18;
  var MIN_REF_W_IN = 20, MIN_REF_H_IN = 22;
  var SNAP_LOGICAL = 4;

  /** Wrapper widths that reproduce the canvas the merchant sees in the app. */
  var VIEWPORTS = [
    { key: 'desktop', label: 'Customizer · desktop', width: 676, hint: '676 × 487 canvas (screens wider than 1040px)' },
    { key: 'tablet', label: 'Customizer · tablet', width: 860, hint: '860 × 520 canvas (stacked layout, 1040px and narrower)' },
    { key: 'phone', label: 'Customizer · phone', width: 327, hint: '327 × 280 canvas (phone)' }
  ];

  // ---------------------------------------------------------------------------
  // Ported helpers (print-units.ts / print-area-dimensions.ts / design-assets.ts)
  // ---------------------------------------------------------------------------
  function num(v, fallback) {
    var n = Number(v);
    return Number.isFinite(n) ? n : fallback;
  }
  function clamp(n, min, max) { return Math.max(min, Math.min(max, n)); }
  function round2(n) { return Math.round(n * 100) / 100; }

  function normalizeUnit(unit) {
    var u = String(unit == null ? '' : unit).trim().toLowerCase();
    if (!u) return '';
    if (u === 'inch' || u === 'inches' || u === 'in') return 'in';
    if (/^millimet(er|re)s?$/.test(u) || u === 'mm') return 'mm';
    if (/^centimet(er|re)s?$/.test(u) || u === 'cm') return 'cm';
    if (u === 'pixel' || u === 'pixels' || u === 'px') return 'px';
    if (u === 'point' || u === 'points' || u === 'pt') return 'pt';
    if (/^met(er|re)s?$/.test(u) || u === 'm') return 'm';
    return u;
  }
  function unitKind(unit) {
    var n = normalizeUnit(unit);
    if (!n) return 'unknown';
    return ['px', 'in', 'mm', 'cm', 'm', 'pt'].indexOf(n) >= 0 ? n : 'unknown';
  }
  function spanInchesToUnit(wIn, hIn, unit) {
    switch (unitKind(unit)) {
      case 'mm': return { width: wIn * 25.4, height: hIn * 25.4 };
      case 'cm': return { width: wIn * 2.54, height: hIn * 2.54 };
      case 'm': return { width: wIn * 0.0254, height: hIn * 0.0254 };
      default: return { width: wIn, height: hIn };
    }
  }

  /** Same defaults as normalizeDtftaPrintArea() when Laravel sends null. */
  function normalizeArea(a) {
    a = a || {};
    var s = function (v, d) { return v === null || v === undefined || v === '' ? d : String(v); };
    var out = {
      id: a.id || 0,
      title: s(a.title, ''),
      area_width: s(a.area_width, '250.00'),
      area_height: s(a.area_height, '250.00'),
      unit: s(a.unit, 'px'),
      position_x: s(a.position_x, '0.00'),
      position_y: s(a.position_y, '0.00'),
      asset_key: s(a.asset_key, '')
    };
    ['design_position_x', 'design_position_y', 'design_area_width', 'design_area_height'].forEach(function (k) {
      if (a[k] !== null && a[k] !== undefined && String(a[k]).trim() !== '') out[k] = String(a[k]);
    });
    return out;
  }

  function physicalPrintSize(area) {
    var w = num(area.area_width, 0), h = num(area.area_height, 0);
    var safeW = w > 0 ? w : 250, safeH = h > 0 ? h : 250;
    var kind = unitKind(area.unit);
    var rawEmpty = !String(area.unit || '').trim();
    var dpi = DEFAULT_DPI;
    if (kind === 'px' || rawEmpty) return { width: safeW, height: safeH };
    var maxDim = Math.max(safeW, safeH);
    var fromPx = function () { return { width: safeW / dpi, height: safeH / dpi }; };
    if (kind === 'in') return maxDim <= INCH_MAX ? { width: safeW, height: safeH } : fromPx();
    if (kind === 'mm') { if (maxDim <= MM_MAX) return { width: safeW, height: safeH }; var i = fromPx(); return { width: i.width * 25.4, height: i.height * 25.4 }; }
    if (kind === 'cm') { if (maxDim <= CM_MAX) return { width: safeW, height: safeH }; var c = fromPx(); return { width: c.width * 2.54, height: c.height * 2.54 }; }
    if (kind === 'm') { if (maxDim <= 3) return { width: safeW, height: safeH }; var m = fromPx(); return { width: m.width * 0.0254, height: m.height * 0.0254 }; }
    return { width: safeW, height: safeH };
  }

  function referenceSpan(area) {
    var kind = unitKind(area.unit);
    var rawEmpty = !String(area.unit || '').trim();
    if (kind === 'px' || rawEmpty) return { width: LOGICAL, height: LOGICAL };
    var px = num(area.position_x, 0), py = num(area.position_y, 0);
    var aw = num(area.area_width, 0), ah = num(area.area_height, 0);
    if (kind === 'in') {
      return {
        width: Math.max(MIN_REF_W_IN, DEFAULT_REF_W_IN, px + aw),
        height: Math.max(MIN_REF_H_IN, DEFAULT_REF_H_IN, py + ah)
      };
    }
    var minU = spanInchesToUnit(MIN_REF_W_IN, MIN_REF_H_IN, area.unit);
    var defU = spanInchesToUnit(DEFAULT_REF_W_IN, DEFAULT_REF_H_IN, area.unit);
    return {
      width: Math.max(minU.width, defU.width, px + aw),
      height: Math.max(minU.height, defU.height, py + ah)
    };
  }

  function logicalPosition(area, rw, rh) {
    var kind = unitKind(area.unit);
    var rawEmpty = !String(area.unit || '').trim();
    var px = num(area.position_x, 0), py = num(area.position_y, 0);
    if (kind === 'px' || rawEmpty || kind === 'unknown') return { left: px, top: py };
    var ref = referenceSpan(area);
    var left = (px / ref.width) * LOGICAL;
    var top = (py / ref.height) * LOGICAL;
    return {
      left: clamp(left, 0, Math.max(0, LOGICAL - rw)),
      top: clamp(top, 0, Math.max(0, LOGICAL - rh))
    };
  }

  function physicalInAreaFields(area) {
    var w = num(area.area_width, 0), h = num(area.area_height, 0);
    if (!(w > 0) || !(h > 0)) return false;
    var kind = unitKind(area.unit);
    var rawEmpty = !String(area.unit || '').trim();
    var maxDim = Math.max(w, h);
    if (kind === 'px' || rawEmpty) return false;
    if (kind === 'in') return maxDim <= INCH_MAX;
    if (kind === 'mm') return maxDim <= MM_MAX;
    if (kind === 'cm') return maxDim <= CM_MAX;
    if (kind === 'm') return maxDim <= 3;
    return false;
  }

  /** Region the app computes when no design_* fields are sent (getDesignRegionFromPrintArea). */
  function derivedRegion(area) {
    var rawW = num(area.area_width || 250, 250), rawH = num(area.area_height || 250, 250);
    var safeW = rawW > 0 ? rawW : 250, safeH = rawH > 0 ? rawH : 250;
    if (physicalInAreaFields(area)) {
      var aspect = safeW / safeH;
      if (!Number.isFinite(aspect) || aspect <= 0) return { left: 125, top: 125, width: 250, height: 250 };
      var rw, rh;
      if (aspect >= 1) { rw = 280; rh = rw / aspect; } else { rh = 280; rw = rh * aspect; }
      rw = round2(rw); rh = round2(rh);
      var px = num(area.position_x, 0), py = num(area.position_y, 0);
      if (Math.abs(px) < 1e-6 && Math.abs(py) < 1e-6) {
        return { left: Math.max(0, (LOGICAL - rw) / 2), top: Math.max(0, (LOGICAL - rh) / 2), width: rw, height: rh };
      }
      var p = logicalPosition(area, rw, rh);
      return { left: p.left, top: p.top, width: rw, height: rh };
    }
    var pos = logicalPosition(area, safeW, safeH);
    return { left: pos.left, top: pos.top, width: safeW, height: safeH };
  }

  /** Full app logic, including saved design_* fields. */
  function appRegion(area) {
    var dw = num(area.design_area_width, NaN), dh = num(area.design_area_height, NaN);
    if (dw > 0 && dh > 0) {
      if (area.design_position_x !== undefined && area.design_position_y !== undefined) {
        return { left: num(area.design_position_x, 0), top: num(area.design_position_y, 0), width: dw, height: dh };
      }
      var p = logicalPosition(area, dw, dh);
      return { left: p.left, top: p.top, width: dw, height: dh };
    }
    return derivedRegion(area);
  }

  /** resolveDesignPlacementFromPrintArea (title first, then asset key). */
  function resolvePlacement(title, assetKey) {
    var t = String(title || '').trim().toLowerCase();
    if (t) {
      if (/right[\s_-]*sleeve/.test(t)) return 'right_sleeve';
      if (/left[\s_-]*sleeve/.test(t)) return 'left_sleeve';
      if (t === 'front' || /\bfront\b/.test(t)) return 'front';
      if (t === 'back' || /\bback\b/.test(t)) return 'back';
    }
    var k = String(assetKey || '').trim().toLowerCase();
    if (!k) return null;
    if (/_front$/.test(k) || k === 'front') return 'front';
    if (/_back$/.test(k) || k === 'back') return 'back';
    if (k.indexOf('left_sleeve') >= 0 || k.indexOf('left-sleeve') >= 0) return 'left_sleeve';
    if (k.indexOf('right_sleeve') >= 0 || k.indexOf('right-sleeve') >= 0) return 'right_sleeve';
    return null;
  }

  var PLACEMENT_LABELS = { front: 'Front', back: 'Back', left_sleeve: 'Left sleeve', right_sleeve: 'Right sleeve' };

  /** DesignCanvas fillWidth sizing for a wrapper of `w` px. */
  function canvasSizeFor(w) {
    var cw = Math.min(MAX_CANVAS_WIDTH, Math.max(MIN_CANVAS_SIZE, w));
    var ch = Math.max(MIN_CANVAS_HEIGHT, Math.min(MAX_CANVAS_HEIGHT, Math.min(Math.round(cw * CANVAS_ASPECT_RATIO), Math.round(cw * 0.9))));
    return { w: cw, h: ch };
  }

  function fmt(n, digits) {
    if (!Number.isFinite(n)) return '–';
    var d = digits == null ? 2 : digits;
    return String(Number(n.toFixed(d)));
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function sameRegion(a, b) {
    if (!a || !b) return !a && !b;
    return Math.abs(a.left - b.left) < 0.005 && Math.abs(a.top - b.top) < 0.005 &&
      Math.abs(a.width - b.width) < 0.005 && Math.abs(a.height - b.height) < 0.005;
  }

  function clampRegion(r, minEdge) {
    var me = minEdge || 10;
    var width = clamp(num(r.width, 250), me, LOGICAL);
    var height = clamp(num(r.height, 250), me, LOGICAL);
    return {
      left: round2(clamp(num(r.left, 0), 0, LOGICAL - width)),
      top: round2(clamp(num(r.top, 0), 0, LOGICAL - height)),
      width: round2(width),
      height: round2(height)
    };
  }

  // ---------------------------------------------------------------------------
  // Styles (scoped to .pap)
  // ---------------------------------------------------------------------------
  var STYLE_ID = 'pap-styles';
  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;
    var css = [
      '.pap{display:flex;flex-direction:column;gap:16px;min-width:0}',
      '.pap *{box-sizing:border-box}',
      '.pap-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px}',
      '.pap-head h3{margin:0 0 4px;font-size:18px;font-weight:600;color:var(--text-primary,#fff)}',
      '.pap-sub{margin:0;font-size:13px;color:var(--text-muted,#94a3b8);max-width:62ch}',
      '.pap-controls{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}',
      '.pap-field{display:flex;flex-direction:column;gap:6px;min-width:0}',
      '.pap-field>label,.pap-label{font-size:12px;font-weight:500;color:var(--text-secondary,#cbd5e1);text-transform:uppercase;letter-spacing:.5px}',
      '.pap-field select,.pap-field input{width:100%}',
      '.pap-tabs{display:flex;flex-wrap:wrap;gap:8px}',
      '.pap-tab{position:relative;border:1px solid var(--border-color,#334155);background:var(--bg-secondary,#1e293b);color:var(--text-primary,#fff);border-radius:8px;padding:8px 14px;font:600 13px Inter,sans-serif;cursor:pointer}',
      '.pap-tab:hover{border-color:var(--accent-blue,#3b82f6)}',
      '.pap-tab[aria-selected="true"]{background:linear-gradient(135deg,var(--accent-orange,#f97316),#ea580c);border-color:transparent;color:#fff}',
      '.pap-tab .pap-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#facc15;margin-left:6px;vertical-align:middle}',
      '.pap-tab .pap-muted{font-weight:500;opacity:.75;margin-left:6px}',
      '.pap-body{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start}',
      '@media (max-width:1180px){.pap-body{grid-template-columns:minmax(0,1fr)}}',
      '.pap-stage-wrap{min-width:0;display:flex;flex-direction:column;gap:8px}',
      '.pap-stage{position:relative;background:#ffffff;border-radius:12px;overflow:hidden;margin:0 auto;max-width:100%;box-shadow:0 8px 18px rgba(2,6,23,.35)}',
      '.pap-stage canvas{max-width:none!important;max-height:none!important}',
      '.pap-stage .canvas-container{max-width:none}',
      '.pap-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;color:#475569;font-size:14px;pointer-events:none}',
      '.pap-stagebar{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;font-size:12px;color:var(--text-muted,#94a3b8)}',
      '.pap-side{display:flex;flex-direction:column;gap:14px;min-width:0}',
      '.pap-box{background:var(--bg-primary,#0f172a);border:1px solid var(--border-color,#334155);border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:12px}',
      '.pap-box h4{margin:0;font-size:13px;font-weight:600;color:var(--text-primary,#fff)}',
      '.pap-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}',
      '.pap-num{position:relative}',
      '.pap-num input{padding-right:34px!important;font-variant-numeric:tabular-nums}',
      '.pap-num span{position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;color:var(--text-muted,#94a3b8);pointer-events:none}',
      '.pap-btnrow{display:flex;flex-wrap:wrap;gap:8px}',
      '.pap-btn{border:1px solid var(--border-color,#334155);background:var(--bg-tertiary,#334155);color:var(--text-primary,#fff);border-radius:8px;padding:7px 10px;font:500 12px Inter,sans-serif;cursor:pointer}',
      '.pap-btn:hover{border-color:var(--accent-blue,#3b82f6)}',
      '.pap-btn:disabled{opacity:.45;cursor:not-allowed}',
      '.pap-btn:focus-visible,.pap-tab:focus-visible{outline:2px solid var(--accent-blue,#3b82f6);outline-offset:2px}',
      '.pap-status{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary,#cbd5e1)}',
      '.pap-pill{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px;border:1px solid}',
      '.pap-pill.saved{color:#34d399;border-color:rgba(16,185,129,.45);background:rgba(16,185,129,.1)}',
      '.pap-pill.derived{color:#93c5fd;border-color:rgba(59,130,246,.45);background:rgba(59,130,246,.1)}',
      '.pap-pill.dirty{color:#fde047;border-color:rgba(250,204,21,.5);background:rgba(250,204,21,.1)}',
      '.pap-note{font-size:12px;line-height:1.5;color:var(--text-muted,#94a3b8);margin:0}',
      '.pap-note strong{color:var(--text-secondary,#cbd5e1)}',
      '.pap-warn{font-size:12px;line-height:1.5;color:#fdba74;background:rgba(249,115,22,.08);border:1px solid rgba(249,115,22,.35);border-radius:8px;padding:8px 10px;margin:0}',
      '.pap-check{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary,#cbd5e1);cursor:pointer}',
      '.pap-swatches{display:flex;flex-wrap:wrap;gap:6px}',
      '.pap-sw{width:24px;height:24px;border-radius:50%;border:2px solid var(--bg-card,#1e293b);box-shadow:0 0 0 1px var(--border-color,#334155);cursor:pointer;padding:0}',
      '.pap-sw[aria-pressed="true"]{box-shadow:0 0 0 2px var(--accent-orange,#f97316)}',
      '.pap-sw.none{background:repeating-linear-gradient(45deg,#cbd5e1 0 4px,#f8fafc 4px 8px)}',
      '.pap-savebar{display:flex;flex-wrap:wrap;align-items:center;gap:10px}',
      '.pap-msg{font-size:13px}',
      '.pap-msg.ok{color:#34d399}.pap-msg.err{color:#f87171}'
    ].join('\n');
    var el = document.createElement('style');
    el.id = STYLE_ID;
    el.textContent = css;
    document.head.appendChild(el);
  }

  // ---------------------------------------------------------------------------
  // Preview widget
  // ---------------------------------------------------------------------------
  /**
   * @param {HTMLElement} root
   * @param {object} opts
   *   data            payload from PrintAreaPreviewData::payload()
   *   mode            'form' (create/edit page) | 'designer'
   *   readArea()      form mode: returns the print area fields currently in the form
   *   regionInputs    form mode: {x,y,w,h} inputs that get submitted with the form
   *   initialProductId, initialPrintAreaId, initialColor, printAreaId (form edit)
   */
  function PrintAreaPreview(root, opts) {
    if (!global.fabric) {
      root.innerHTML = '<p class="pap-warn">The preview could not load its drawing library. Check your connection and reload the page.</p>';
      return;
    }
    injectStyles();
    this.root = root;
    this.opts = opts || {};
    this.data = this.opts.data || { products: [], printAreas: [], assets: {} };
    this.minEdge = this.data.minEdge || 10;
    this.mode = this.opts.mode === 'designer' ? 'designer' : 'form';
    this.viewport = VIEWPORTS[0];
    this.showArtwork = true;
    this.drafts = {}; // designer: areaId -> region (unsaved)
    this.areaById = {};
    var self = this;
    (this.data.printAreas || []).forEach(function (a) { self.areaById[a.id] = a; });
    this.productId = null;
    this.colorKey = '';
    this.areaId = null;
    this.custom = null; // form mode: current explicit region or null (= derived)
    this.bgToken = 0;
    this.build();
    this.initState();
  }

  PrintAreaPreview.VIEWPORTS = VIEWPORTS;

  var P = PrintAreaPreview.prototype;

  P.build = function () {
    var r = this.root;
    var designer = this.mode === 'designer';
    r.classList.add('pap');
    r.innerHTML =
      '<div class="pap-head"><div>' +
        '<h3>' + (designer ? 'Product print areas' : 'Customizer canvas preview') + '</h3>' +
        '<p class="pap-sub">' + (designer
          ? 'Pick a product and placement, then drag or resize the dashed box over the product image. This is exactly what merchants see in the Shopify customizer.'
          : 'Drag or resize the dashed box over the product image, or type exact values. Saved with this print area and shown to merchants in the Shopify customizer.') +
        '</p></div></div>' +
      '<div class="pap-controls">' +
        '<div class="pap-field"><label for="pap-product">Product</label><select id="pap-product" class="filter-select" data-ref="product"></select></div>' +
        '<div class="pap-field"><span class="pap-label">Colour</span><div class="pap-swatches" data-ref="swatches" role="group" aria-label="Product colour"></div></div>' +
        '<div class="pap-field"><label for="pap-viewport">Preview as</label><select id="pap-viewport" class="filter-select" data-ref="viewport"></select></div>' +
      '</div>' +
      (designer ? '<div class="pap-field"><span class="pap-label">Placement</span><div class="pap-tabs" data-ref="tabs" role="tablist" aria-label="Placement"></div></div>' : '') +
      '<div class="pap-body">' +
        '<div class="pap-stage-wrap">' +
          '<div class="pap-stage" data-ref="stage"><canvas data-ref="canvas"></canvas><div class="pap-empty" data-ref="empty" hidden></div></div>' +
          '<div class="pap-stagebar"><span data-ref="stageinfo"></span><span>Drag to move · drag corners to resize · arrow keys nudge (Shift = 10)</span></div>' +
        '</div>' +
        '<div class="pap-side">' +
          '<div class="pap-box">' +
            '<div class="pap-status"><span data-ref="pill" class="pap-pill derived"></span><span data-ref="placementlabel"></span></div>' +
            '<div><span class="pap-label">Position &amp; size</span>' +
              '<p class="pap-note" style="margin-top:4px">Canvas units on the customizer’s 500 × 500 grid.</p></div>' +
            '<div class="pap-grid2">' +
              numField('x', 'X (left)') + numField('y', 'Y (top)') + numField('w', 'Width') + numField('h', 'Height') +
            '</div>' +
            '<div class="pap-btnrow">' +
              '<button type="button" class="pap-btn" data-act="center-x">Center horizontally</button>' +
              '<button type="button" class="pap-btn" data-act="center-y">Center vertically</button>' +
              '<button type="button" class="pap-btn" data-act="aspect">Match print size shape</button>' +
              '<button type="button" class="pap-btn" data-act="reset">Use calculated</button>' +
            '</div>' +
            '<label class="pap-check"><input type="checkbox" data-ref="artwork" checked> Show sample artwork</label>' +
          '</div>' +
          '<div class="pap-box">' +
            '<h4>Print file</h4>' +
            '<p class="pap-note" data-ref="printinfo"></p>' +
            '<p class="pap-warn" data-ref="aspectwarn" hidden></p>' +
          '</div>' +
          (designer
            ? '<div class="pap-box"><p class="pap-warn" data-ref="shared" hidden></p>' +
              '<div class="pap-savebar"><button type="button" class="btn-primary" data-act="save">Save print area</button>' +
              '<a class="pap-btn" data-ref="editlink" href="#" style="text-decoration:none">Edit size, unit &amp; price</a></div>' +
              '<span class="pap-msg" data-ref="msg" role="status" aria-live="polite"></span></div>'
            : '<div class="pap-box"><p class="pap-note" data-ref="formnote"></p><p class="pap-warn" data-ref="shared" hidden></p></div>') +
        '</div>' +
      '</div>';

    function numField(key, label) {
      return '<div class="pap-field"><label for="pap-' + key + '">' + label + '</label>' +
        '<div class="pap-num"><input id="pap-' + key + '" class="filter-select" type="number" step="any" min="0" max="500" inputmode="decimal" data-num="' + key + '"><span>/500</span></div></div>';
    }

    var refs = {};
    r.querySelectorAll('[data-ref]').forEach(function (el) { refs[el.getAttribute('data-ref')] = el; });
    this.refs = refs;
    this.nums = {};
    var self = this;
    r.querySelectorAll('[data-num]').forEach(function (el) { self.nums[el.getAttribute('data-num')] = el; });

    // viewport select
    refs.viewport.innerHTML = VIEWPORTS.map(function (v) {
      return '<option value="' + v.key + '">' + escapeHtml(v.label) + '</option>';
    }).join('');

    // product select
    var products = this.data.products || [];
    refs.product.innerHTML = '<option value="">No product (print area image only)</option>' + products.map(function (p) {
      return '<option value="' + p.id + '">' + escapeHtml(p.label) + (p.status && p.status !== 'active' ? ' (' + escapeHtml(p.status) + ')' : '') + '</option>';
    }).join('');

    // fabric canvas
    var fabric = global.fabric;
    this.canvas = new fabric.Canvas(refs.canvas, {
      selection: false,
      preserveObjectStacking: true,
      backgroundColor: '#ffffff',
      enableRetinaScaling: true
    });
    this.makeRegionRect();

    // events
    refs.viewport.addEventListener('change', function () {
      self.viewport = VIEWPORTS.filter(function (v) { return v.key === refs.viewport.value; })[0] || VIEWPORTS[0];
      self.layout();
    });
    refs.product.addEventListener('change', function () {
      self.setProduct(refs.product.value ? Number(refs.product.value) : null);
    });
    refs.artwork.addEventListener('change', function () {
      self.showArtwork = refs.artwork.checked;
      self.syncArtwork();
      self.canvas.requestRenderAll();
    });
    Object.keys(this.nums).forEach(function (k) {
      var el = self.nums[k];
      el.addEventListener('input', function () { self.onNumberInput(); });
      el.addEventListener('change', function () { self.onNumberInput(true); });
    });
    r.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]');
      if (!b || !r.contains(b)) return;
      self.action(b.getAttribute('data-act'));
    });
    this.resizeObserver = new ResizeObserver(function () { self.fitDisplay(); });
    this.resizeObserver.observe(refs.stage.parentElement);
    refs.stage.tabIndex = 0;
    refs.stage.setAttribute('aria-label', 'Print area preview. Use arrow keys to move the print area.');
    refs.stage.addEventListener('keydown', function (e) { self.onKey(e); });
  };

  P.makeRegionRect = function () {
    var fabric = global.fabric;
    var self = this;
    var rect = new fabric.Rect({
      left: 0, top: 0, width: 100, height: 100,
      originX: 'left', originY: 'top',
      fill: 'rgba(55,65,81,0.04)',
      stroke: REGION_STROKE,
      strokeWidth: 2,
      strokeDashArray: [8, 8],
      strokeUniform: true,
      objectCaching: false,
      lockRotation: true,
      hasRotatingPoint: false,
      cornerColor: '#ffffff',
      cornerStrokeColor: '#f97316',
      borderColor: '#f97316',
      borderDashArray: [4, 4],
      transparentCorners: false,
      cornerSize: 10,
      cornerStyle: 'circle',
      padding: 0,
      hoverCursor: 'move'
    });
    if (rect.setControlsVisibility) rect.setControlsVisibility({ mtr: false });
    this.rect = rect;
    this.canvas.add(rect);

    this.guideV = new fabric.Line([0, 0, 0, 0], { stroke: '#ec4899', strokeWidth: 1, selectable: false, evented: false, visible: false, excludeFromExport: true });
    this.guideH = new fabric.Line([0, 0, 0, 0], { stroke: '#ec4899', strokeWidth: 1, selectable: false, evented: false, visible: false, excludeFromExport: true });
    this.canvas.add(this.guideV, this.guideH);

    this.canvas.on('object:moving', function (e) { if (e.target === rect) self.onRectMoving(); });
    this.canvas.on('object:scaling', function (e) { if (e.target === rect) self.onRectScaling(); });
    this.canvas.on('object:modified', function (e) { if (e.target === rect) self.onRectModified(); });
    this.canvas.on('mouse:up', function () { self.hideGuides(); });
  };

  // ---------- geometry helpers ----------
  P.scale = function () {
    return { x: this.size.w / LOGICAL, y: this.size.h / LOGICAL };
  };

  P.rectToLogical = function () {
    var s = this.scale();
    var r = this.rect;
    return {
      left: r.left / s.x,
      top: r.top / s.y,
      width: (r.width * r.scaleX) / s.x,
      height: (r.height * r.scaleY) / s.y
    };
  };

  P.placeRect = function (region) {
    var s = this.scale();
    this.rect.set({
      left: region.left * s.x,
      top: region.top * s.y,
      width: region.width * s.x,
      height: region.height * s.y,
      scaleX: 1, scaleY: 1
    });
    this.rect.setCoords();
  };

  // ---------- state ----------
  P.currentArea = function () {
    if (this.mode === 'form') {
      var a = normalizeArea(this.opts.readArea ? this.opts.readArea() : {});
      if (this.custom) {
        a.design_position_x = String(this.custom.left);
        a.design_position_y = String(this.custom.top);
        a.design_area_width = String(this.custom.width);
        a.design_area_height = String(this.custom.height);
      } else {
        delete a.design_position_x; delete a.design_position_y; delete a.design_area_width; delete a.design_area_height;
      }
      return a;
    }
    var raw = this.areaById[this.areaId];
    if (!raw) return null;
    var area = normalizeArea(raw);
    var draft = this.drafts[this.areaId];
    if (draft !== undefined) {
      delete area.design_position_x; delete area.design_position_y; delete area.design_area_width; delete area.design_area_height;
      if (draft) {
        area.design_position_x = String(draft.left);
        area.design_position_y = String(draft.top);
        area.design_area_width = String(draft.width);
        area.design_area_height = String(draft.height);
      }
    }
    return area;
  };

  P.savedRegionFor = function (raw) {
    var a = normalizeArea(raw);
    return a.design_area_width ? appRegion(a) : null;
  };

  P.initState = function () {
    var o = this.opts;
    var products = this.data.products || [];

    if (this.mode === 'form') {
      // Saved region from the inputs rendered by the server
      var vals = ['x', 'y', 'w', 'h'].map(function (k) { return o.regionInputs && o.regionInputs[k] ? o.regionInputs[k].value : ''; });
      if (vals.every(function (v) { return String(v).trim() !== '' && Number.isFinite(Number(v)); }) && Number(vals[2]) > 0 && Number(vals[3]) > 0) {
        this.custom = clampRegion({ left: vals[0], top: vals[1], width: vals[2], height: vals[3] }, this.minEdge);
      }
      this.savedCustom = this.custom ? Object.assign({}, this.custom) : null;
      // Default product: one linked to this print area, else one with mockups, else first.
      var pid = o.initialProductId || null;
      if (!pid && o.printAreaId) {
        var linkedAll = products.filter(function (p) { return (p.print_area_ids || []).indexOf(Number(o.printAreaId)) >= 0; });
        var a0 = normalizeArea(o.readArea ? o.readArea() : {});
        var pl0 = resolvePlacement(a0.title, a0.asset_key);
        var linkedWithMock = linkedAll.filter(function (p) {
          return (p.colors || []).some(function (c) { return pl0 && c.mockups && c.mockups[pl0]; });
        })[0];
        var linked = linkedWithMock || linkedAll[0];
        if (linked) pid = linked.id;
      }
      if (!pid) {
        var withMock = products.filter(function (p) { return (p.colors || []).length; })[0];
        pid = withMock ? withMock.id : (products[0] ? products[0].id : null);
      }
      this.refs.product.value = pid ? String(pid) : '';
      this.productId = pid;
      this.pickDefaultColor(o.initialColor);
      this.renderSwatches();
      this.layout();
      return;
    }

    // designer
    var pid2 = o.initialProductId || null;
    if (pid2 && !products.some(function (p) { return p.id === pid2; })) pid2 = null;
    if (!pid2 && o.initialPrintAreaId) {
      var p2 = products.filter(function (p) { return (p.print_area_ids || []).indexOf(Number(o.initialPrintAreaId)) >= 0; })[0];
      if (p2) pid2 = p2.id;
    }
    if (!pid2) {
      var firstWithAreas = products.filter(function (p) { return (p.print_area_ids || []).length; })[0];
      pid2 = firstWithAreas ? firstWithAreas.id : (products[0] ? products[0].id : null);
    }
    this.refs.product.value = pid2 ? String(pid2) : '';
    this.productId = pid2;
    var areas = this.productAreas();
    var wanted = o.initialPrintAreaId ? Number(o.initialPrintAreaId) : null;
    this.areaId = areas.some(function (a) { return a.id === wanted; }) ? wanted : (areas[0] ? areas[0].id : null);
    this.pickDefaultColor(o.initialColor);
    this.renderSwatches();
    this.renderTabs();
    this.layout();
  };

  P.product = function () {
    var id = this.productId;
    return (this.data.products || []).filter(function (p) { return p.id === id; })[0] || null;
  };

  /** Active print areas of the product, sorted like the app (display_order). Inactive ones listed last. */
  P.productAreas = function () {
    var p = this.product();
    if (!p) return [];
    var self = this;
    var list = (p.print_area_ids || []).map(function (id) { return self.areaById[id]; }).filter(Boolean);
    return list.sort(function (a, b) {
      if (a.is_active !== b.is_active) return a.is_active ? -1 : 1;
      return (a.display_order - b.display_order) || String(a.title).localeCompare(String(b.title));
    });
  };

  P.placement = function () {
    var a = this.currentArea();
    return a ? resolvePlacement(a.title, a.asset_key) : null;
  };

  P.pickDefaultColor = function (preferred) {
    var p = this.product();
    var placement = this.placement();
    var colors = p ? p.colors || [] : [];
    if (preferred && colors.some(function (c) { return c.key === preferred; })) { this.colorKey = preferred; return; }
    if (this.colorKey && colors.some(function (c) { return c.key === this.colorKey; }, this)) return;
    var withPlacement = colors.filter(function (c) { return placement && c.mockups && c.mockups[placement]; })[0];
    this.colorKey = withPlacement ? withPlacement.key : (colors[0] ? colors[0].key : '');
  };

  P.setProduct = function (id) {
    this.productId = id;
    this.colorKey = '';
    if (this.mode === 'designer') {
      var areas = this.productAreas();
      var keep = areas.some(function (a) { return a.id === this.areaId; }, this);
      if (!keep) this.areaId = areas[0] ? areas[0].id : null;
      this.renderTabs();
    }
    this.pickDefaultColor();
    this.renderSwatches();
    this.refresh(true);
  };

  P.setArea = function (id) {
    this.areaId = id;
    this.renderTabs();
    this.pickDefaultColor(this.colorKey);
    this.renderSwatches();
    this.refresh(true);
    this.setMsg('');
  };

  P.renderSwatches = function () {
    var box = this.refs.swatches;
    var p = this.product();
    var placement = this.placement();
    var self = this;
    var colors = p ? p.colors || [] : [];
    var html = '<button type="button" class="pap-sw none" data-color="" title="No colour mockup (print area image)" aria-label="No colour mockup" aria-pressed="' + (!this.colorKey) + '"></button>';
    html += colors.map(function (c) {
      var has = placement && c.mockups && c.mockups[placement];
      var bg = c.hex ? escapeHtml(c.hex) : '#94a3b8';
      return '<button type="button" class="pap-sw" style="background:' + bg + (has ? '' : ';opacity:.45') + '" data-color="' + escapeHtml(c.key) + '" title="' +
        escapeHtml(c.name + (has ? '' : ' (no ' + (PLACEMENT_LABELS[placement] || 'placement').toLowerCase() + ' mockup, uses print area image)')) +
        '" aria-label="' + escapeHtml(c.name) + '" aria-pressed="' + (c.key === self.colorKey) + '"></button>';
    }).join('');
    box.innerHTML = html;
    box.querySelectorAll('[data-color]').forEach(function (b) {
      b.addEventListener('click', function () {
        self.colorKey = b.getAttribute('data-color');
        self.renderSwatches();
        self.loadBackground();
      });
    });
  };

  P.renderTabs = function () {
    var box = this.refs.tabs;
    if (!box) return;
    var areas = this.productAreas();
    var self = this;
    if (!areas.length) {
      box.innerHTML = '<p class="pap-note">' + (this.product()
        ? 'This product has no print areas yet. Link print areas on the product’s edit page.'
        : 'Select a product to see its placements.') + '</p>';
      return;
    }
    box.innerHTML = areas.map(function (a) {
      var dirty = self.drafts[a.id] !== undefined;
      return '<button type="button" class="pap-tab" role="tab" data-area="' + a.id + '" aria-selected="' + (a.id === self.areaId) + '">' +
        escapeHtml(a.title || ('Print area #' + a.id)) +
        (a.is_active ? '' : '<span class="pap-muted">inactive</span>') +
        (dirty ? '<span class="pap-dot" title="Unsaved changes"></span>' : '') + '</button>';
    }).join('');
    box.querySelectorAll('[data-area]').forEach(function (b) {
      b.addEventListener('click', function () { self.setArea(Number(b.getAttribute('data-area'))); });
    });
  };

  // ---------- canvas layout ----------
  P.layout = function () {
    this.size = canvasSizeFor(this.viewport.width);
    this.canvas.setDimensions({ width: this.size.w, height: this.size.h });
    this.fitDisplay();
    this.refresh(true);
  };

  /** Scale the canvas down with CSS when the admin's screen is narrower (math stays identical). */
  P.fitDisplay = function () {
    if (!this.size) return;
    var avail = this.refs.stage.parentElement.clientWidth || this.size.w;
    var k = Math.min(1, avail / this.size.w);
    var w = Math.floor(this.size.w * k), h = Math.floor(this.size.h * k);
    this.canvas.setDimensions({ width: w + 'px', height: h + 'px' }, { cssOnly: true });
    this.refs.stage.style.width = w + 'px';
    this.refs.stage.style.height = h + 'px';
    this.canvas.calcOffset();
    this.refs.stageinfo.textContent = this.viewport.hint + (k < 1 ? ' · shown at ' + Math.round(k * 100) + '%' : '');
  };

  /** Recompute region from state and redraw everything. */
  P.refresh = function (reloadBackground) {
    var area = this.currentArea();
    var noArea = !area;
    this.rect.set({ visible: !noArea, selectable: !noArea, evented: !noArea });
    if (!noArea) this.placeRect(appRegion(area));
    if (reloadBackground) this.loadBackground();
    this.syncArtwork();
    this.updatePanel();
    this.canvas.requestRenderAll();
  };

  P.backgroundSources = function () {
    var area = this.currentArea();
    if (!area) return [];
    var placement = resolvePlacement(area.title, area.asset_key);
    var p = this.product();
    var urls = [];
    if (p && placement && this.colorKey) {
      var c = (p.colors || []).filter(function (x) { return x.key === this.colorKey; }, this)[0];
      if (c && c.mockups && c.mockups[placement]) urls.push({ url: c.mockups[placement], source: c.name + ' ' + PLACEMENT_LABELS[placement].toLowerCase() + ' mockup' });
    }
    var asset = area.asset_key && this.data.assets ? this.data.assets[area.asset_key] : null;
    if (asset) {
      urls.push({ url: asset.primary, source: 'Print area image · ' + asset.name });
      if (asset.fallback && asset.fallback !== asset.primary) urls.push({ url: asset.fallback, source: 'Print area image · ' + asset.name });
    }
    return urls;
  };

  P.loadBackground = function () {
    var fabric = global.fabric;
    var self = this;
    var token = ++this.bgToken;
    var sources = this.backgroundSources();
    if (this.bg) { this.canvas.remove(this.bg); this.bg = null; }
    this.bgSource = '';
    this.showEmpty(sources.length ? 'Loading product image…' : (this.currentArea()
      ? 'No product image for this placement. Choose a print area image or add a colour mockup to the product.'
      : (this.mode === 'designer' ? 'Select a product with print areas.' : '')));
    this.canvas.requestRenderAll();
    if (!sources.length) { this.updatePanel(); return; }

    var tryAt = function (i) {
      if (token !== self.bgToken) return;
      if (i >= sources.length) {
        self.showEmpty('The product image could not be loaded. Check that the mockup file exists.');
        self.updatePanel();
        return;
      }
      var img = new Image();
      img.onload = function () {
        if (token !== self.bgToken) return;
        var fimg = new fabric.FabricImage(img, {
          originX: 'center', originY: 'center',
          selectable: false, evented: false, hasControls: false, hasBorders: false,
          objectCaching: false
        });
        var scaleBg = Math.min(self.size.w / img.naturalWidth, self.size.h / img.naturalHeight);
        fimg.set({ scaleX: scaleBg, scaleY: scaleBg, left: self.size.w / 2, top: self.size.h / 2 });
        self.bg = fimg;
        self.bgSource = sources[i].source;
        self.canvas.add(fimg);
        self.canvas.sendObjectToBack(fimg);
        self.showEmpty('');
        self.updatePanel();
        self.canvas.requestRenderAll();
      };
      img.onerror = function () { tryAt(i + 1); };
      img.src = sources[i].url;
    };
    tryAt(0);
  };

  P.showEmpty = function (text) {
    this.refs.empty.hidden = !text;
    this.refs.empty.textContent = text || '';
  };

  /** Sample artwork clipped exactly like the app (padded absolute clip rect). */
  P.syncArtwork = function () {
    var fabric = global.fabric;
    if (this.art) { this.canvas.remove(this.art); this.art = null; }
    if (!this.showArtwork || !this.rect.visible) return;
    var r = this.rectToLogical();
    var s = this.scale();
    var px = { left: r.left * s.x, top: r.top * s.y, width: r.width * s.x, height: r.height * s.y };
    var cw = this.size.w, ch = this.size.h;
    var cl = Math.max(0, px.left - EDGE_BLEED), ct = Math.max(0, px.top - EDGE_BLEED);
    var clip = new fabric.Rect({
      left: cl, top: ct,
      width: Math.max(1, Math.min(cw - cl, px.width + 2 * EDGE_BLEED)),
      height: Math.max(1, Math.min(ch - ct, px.height + 2 * EDGE_BLEED)),
      originX: 'left', originY: 'top', absolutePositioned: true
    });
    // Art is fitted like an uploaded image: inset 10px, aspect kept.
    var maxW = Math.max(10, px.width - 20), maxH = Math.max(10, px.height - 20);
    var artW = Math.min(maxW, maxH * 1.25), artH = artW / 1.25;
    var shapes = [
      new fabric.Rect({ left: 0, top: 0, width: artW, height: artH, rx: artW * 0.06, ry: artW * 0.06, fill: 'rgba(249,115,22,0.18)', stroke: '#f97316', strokeWidth: 1.5, originX: 'center', originY: 'center' }),
      new fabric.Circle({ left: 0, top: -artH * 0.12, radius: Math.min(artW, artH) * 0.22, fill: '#f97316', originX: 'center', originY: 'center' }),
      new fabric.FabricText('YOUR ARTWORK', { left: 0, top: artH * 0.28, originX: 'center', originY: 'center', fontFamily: 'Inter, Arial, sans-serif', fontWeight: '700', fontSize: Math.max(8, artW * 0.085), fill: '#9a3412', charSpacing: 60 })
    ];
    var g = new fabric.Group(shapes, {
      left: px.left + px.width / 2, top: px.top + px.height / 2,
      originX: 'center', originY: 'center',
      selectable: false, evented: false, objectCaching: false
    });
    g.clipPath = clip;
    this.art = g;
    this.canvas.add(g);
    if (this.bg) this.canvas.sendObjectToBack(this.bg);
    this.canvas.bringObjectToFront(this.rect);
    this.canvas.bringObjectToFront(this.guideV);
    this.canvas.bringObjectToFront(this.guideH);
  };

  // ---------- interactions ----------
  P.onRectMoving = function () {
    var r = this.rect, s = this.scale();
    var w = r.width * r.scaleX, h = r.height * r.scaleY;
    var left = clamp(r.left, 0, this.size.w - w);
    var top = clamp(r.top, 0, this.size.h - h);
    // snap centre to the canvas centre lines (logical 250)
    var cxL = (left + w / 2) / s.x, cyL = (top + h / 2) / s.y;
    var snapX = Math.abs(cxL - LOGICAL / 2) < SNAP_LOGICAL, snapY = Math.abs(cyL - LOGICAL / 2) < SNAP_LOGICAL;
    if (snapX) left = (LOGICAL / 2) * s.x - w / 2;
    if (snapY) top = (LOGICAL / 2) * s.y - h / 2;
    r.set({ left: left, top: top });
    r.setCoords();
    this.showGuides(snapX, snapY);
    this.liveFromRect();
  };

  P.onRectScaling = function () {
    var r = this.rect, s = this.scale();
    var minW = this.minEdge * s.x, minH = this.minEdge * s.y;
    var w = Math.max(minW, r.width * r.scaleX), h = Math.max(minH, r.height * r.scaleY);
    var left = r.left, top = r.top;
    if (left < 0) { w += left; left = 0; }
    if (top < 0) { h += top; top = 0; }
    w = Math.max(minW, Math.min(w, this.size.w - left));
    h = Math.max(minH, Math.min(h, this.size.h - top));
    r.set({ left: left, top: top, width: w, height: h, scaleX: 1, scaleY: 1 });
    r.setCoords();
    this.liveFromRect();
  };

  P.onRectModified = function () {
    var region = clampRegion(this.rectToLogical(), this.minEdge);
    this.commit(region);
  };

  P.liveFromRect = function () {
    var region = this.rectToLogical();
    this.writeNumbers(region);
    this.syncArtwork();
    this.updateAspectWarning(region);
  };

  P.showGuides = function (vx, hy) {
    var cx = this.size.w / 2, cy = this.size.h / 2;
    this.guideV.set({ x1: cx, y1: 0, x2: cx, y2: this.size.h, visible: !!vx });
    this.guideH.set({ x1: 0, y1: cy, x2: this.size.w, y2: cy, visible: !!hy });
  };
  P.hideGuides = function () {
    if (this.guideV.visible || this.guideH.visible) {
      this.guideV.set({ visible: false }); this.guideH.set({ visible: false });
      this.canvas.requestRenderAll();
    }
  };

  P.onKey = function (e) {
    if (!this.rect.visible) return;
    var k = e.key;
    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(k) < 0) return;
    e.preventDefault();
    var step = e.shiftKey ? 10 : 1;
    var r = this.effectiveRegion();
    if (k === 'ArrowLeft') r.left -= step;
    if (k === 'ArrowRight') r.left += step;
    if (k === 'ArrowUp') r.top -= step;
    if (k === 'ArrowDown') r.top += step;
    this.commit(clampRegion(r, this.minEdge));
  };

  P.onNumberInput = function (final) {
    var v = {
      left: this.nums.x.value, top: this.nums.y.value,
      width: this.nums.w.value, height: this.nums.h.value
    };
    var filled = ['left', 'top', 'width', 'height'].every(function (k) { return String(v[k]).trim() !== '' && Number.isFinite(Number(v[k])); });
    if (!filled) return;
    var region = clampRegion(v, this.minEdge);
    if (final) {
      this.commit(region);
    } else {
      // live preview while typing without rewriting the field the admin is editing
      this.placeRect(region);
      this.syncArtwork();
      this.updateAspectWarning(region);
      this.canvas.requestRenderAll();
    }
  };

  P.effectiveRegion = function () {
    var a = this.currentArea();
    return a ? appRegion(a) : { left: 125, top: 125, width: 250, height: 250 };
  };

  /** Store an explicit region (form: hidden inputs; designer: draft). */
  P.commit = function (region) {
    if (this.mode === 'form') {
      this.custom = region;
    } else {
      var raw = this.areaById[this.areaId];
      if (!raw) return;
      var saved = this.savedRegionFor(raw);
      if (saved && sameRegion(saved, region)) delete this.drafts[this.areaId];
      else this.drafts[this.areaId] = region;
      this.renderTabs();
    }
    this.syncInputs();
    this.refresh(false);
    this.setMsg('');
  };

  P.clearCustom = function () {
    if (this.mode === 'form') {
      this.custom = null;
    } else {
      var raw = this.areaById[this.areaId];
      if (!raw) return;
      if (this.savedRegionFor(raw)) this.drafts[this.areaId] = null; else delete this.drafts[this.areaId];
      this.renderTabs();
    }
    this.syncInputs();
    this.refresh(false);
  };

  P.action = function (act) {
    var area = this.currentArea();
    if (!area && act !== 'save') return;
    var r = area ? appRegion(area) : null;
    if (act === 'center-x') { r.left = (LOGICAL - r.width) / 2; this.commit(clampRegion(r, this.minEdge)); }
    else if (act === 'center-y') { r.top = (LOGICAL - r.height) / 2; this.commit(clampRegion(r, this.minEdge)); }
    else if (act === 'aspect') {
      // Make the box on screen the same shape as the physical print (W:H).
      var phys = physicalPrintSize(area);
      var s = this.scale();
      var target = phys.width / phys.height; // visible w/h
      var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
      var h = (r.width * s.x) / (target * s.y);
      var w = r.width;
      if (h > LOGICAL) { h = LOGICAL; w = (h * target * s.y) / s.x; }
      this.commit(clampRegion({ left: cx - w / 2, top: cy - h / 2, width: w, height: h }, this.minEdge));
    }
    else if (act === 'reset') this.clearCustom();
    else if (act === 'save') this.save();
  };

  P.writeNumbers = function (region) {
    var self = this;
    var map = { x: region.left, y: region.top, w: region.width, h: region.height };
    Object.keys(map).forEach(function (k) {
      if (document.activeElement !== self.nums[k]) self.nums[k].value = fmt(map[k]);
    });
  };

  /** Form mode: write the submitted inputs. */
  P.syncInputs = function () {
    if (this.mode !== 'form' || !this.opts.regionInputs) return;
    var ri = this.opts.regionInputs;
    var c = this.custom;
    ri.x.value = c ? fmt(c.left) : '';
    ri.y.value = c ? fmt(c.top) : '';
    ri.w.value = c ? fmt(c.width) : '';
    ri.h.value = c ? fmt(c.height) : '';
  };

  P.isDirty = function () {
    if (this.mode === 'form') return !sameRegion(this.custom, this.savedCustom);
    return Object.keys(this.drafts).length > 0;
  };

  P.updatePanel = function () {
    var refs = this.refs;
    var area = this.currentArea();
    var disabled = !area;
    Object.keys(this.nums).forEach(function (k) { this.nums[k].disabled = disabled; }, this);
    this.root.querySelectorAll('[data-act]').forEach(function (b) {
      if (b.getAttribute('data-act') !== 'save') b.disabled = disabled;
    });

    if (!area) {
      refs.pill.className = 'pap-pill derived';
      refs.pill.textContent = 'No placement';
      refs.placementlabel.textContent = '';
      refs.printinfo.textContent = 'Select a placement to see its print size.';
      refs.aspectwarn.hidden = true;
      if (refs.shared) refs.shared.hidden = true;
      Object.keys(this.nums).forEach(function (k) { this.nums[k].value = ''; }, this);
      if (this.mode === 'designer') this.updateSaveState();
      return;
    }

    var region = appRegion(area);
    this.writeNumbers(region);
    var hasCustom = !!area.design_area_width;

    // status pill
    var dirty;
    if (this.mode === 'form') {
      dirty = this.isDirty();
    } else {
      dirty = this.drafts[this.areaId] !== undefined;
    }
    if (dirty) { refs.pill.className = 'pap-pill dirty'; refs.pill.textContent = hasCustom ? 'Unsaved changes' : 'Unsaved · will use calculated'; }
    else if (hasCustom) { refs.pill.className = 'pap-pill saved'; refs.pill.textContent = 'Custom region saved'; }
    else { refs.pill.className = 'pap-pill derived'; refs.pill.textContent = 'Calculated from size & position'; }

    var placement = resolvePlacement(area.title, area.asset_key);
    refs.placementlabel.textContent = placement ? PLACEMENT_LABELS[placement] : 'Placement not recognised';
    refs.placementlabel.title = placement ? '' : 'Name the print area Front, Back, Left sleeve or Right sleeve (or pick a matching image) so colour mockups can be matched.';

    // print info
    var phys = physicalPrintSize(area);
    var unit = normalizeUnit(area.unit) || area.unit || 'px';
    var s = this.scale();
    var visW = region.width * s.x, visH = region.height * s.y;
    var exportW = Math.round(visW * 3), exportH = Math.round(visH * 3);
    refs.printinfo.innerHTML =
      '<strong>' + fmt(phys.width) + ' × ' + fmt(phys.height) + ' ' + escapeHtml(unit) + '</strong> physical print size. ' +
      'In this preview the box is ' + Math.round(visW) + ' × ' + Math.round(visH) + ' px on screen, exported at about ' + exportW + ' × ' + exportH + ' px (3×).' +
      (this.bgSource ? '<br>Image: ' + escapeHtml(this.bgSource) + '.' : '');
    this.updateAspectWarning(region);

    // shared warning
    if (refs.shared) {
      var raw = this.mode === 'designer' ? this.areaById[this.areaId] : (this.opts.printAreaId ? this.areaById[this.opts.printAreaId] : null);
      var n = raw ? raw.products_count : 0;
      refs.shared.hidden = !(n > 1);
      refs.shared.textContent = n > 1 ? 'This print area is shared by ' + n + ' products. Changing it updates the customizer for all of them.' : '';
    }

    if (this.mode === 'form' && refs.formnote) {
      refs.formnote.innerHTML = hasCustom
        ? 'The dashed box above is saved with this print area when you click <strong>' + escapeHtml(this.opts.submitLabel || 'Save') + '</strong>.'
        : 'No custom region yet. The customizer calculates this box from <strong>Area Width/Height</strong> and <strong>Position X/Y</strong>. Drag the box or type values to set it exactly.';
    }

    if (this.mode === 'designer') {
      if (refs.editlink) refs.editlink.href = (this.areaById[this.areaId] || {}).edit_url || '#';
      this.updateSaveState();
    }
  };

  P.updateAspectWarning = function (region) {
    var area = this.currentArea();
    var warn = this.refs.aspectwarn;
    if (!area || !warn) return;
    var phys = physicalPrintSize(area);
    var s = this.scale();
    var vis = (region.width * s.x) / (region.height * s.y);
    var target = phys.width / phys.height;
    var diff = Math.abs(vis - target) / target;
    var show = Number.isFinite(diff) && diff > 0.08;
    warn.hidden = !show;
    if (show) {
      warn.textContent = 'The box is ' + (vis > target ? 'wider' : 'taller') + ' than the ' + fmt(phys.width) + ' × ' + fmt(phys.height) +
        ' print shape, so artwork placed edge to edge will not fill the print exactly. Use “Match print size shape” to align them.';
    }
  };

  // ---------- designer save ----------
  P.updateSaveState = function () {
    var btn = this.root.querySelector('[data-act="save"]');
    if (!btn) return;
    var dirty = this.areaId != null && this.drafts[this.areaId] !== undefined;
    btn.disabled = !dirty || this.saving;
    btn.textContent = this.saving ? 'Saving…' : 'Save print area';
  };

  P.setMsg = function (text, kind) {
    if (!this.refs.msg) return;
    this.refs.msg.textContent = text || '';
    this.refs.msg.className = 'pap-msg' + (kind ? ' ' + kind : '');
  };

  P.save = function () {
    var self = this;
    var id = this.areaId;
    var raw = this.areaById[id];
    if (!raw || this.drafts[id] === undefined || this.saving) return;
    var region = this.drafts[id];
    var body = region
      ? { design_position_x: region.left, design_position_y: region.top, design_area_width: region.width, design_area_height: region.height }
      : { design_position_x: null, design_position_y: null, design_area_width: null, design_area_height: null };
    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    this.saving = true;
    this.updateSaveState();
    this.setMsg('');
    fetch(raw.region_url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) { return { ok: res.ok, status: res.status, json: json }; });
    }).then(function (r) {
      self.saving = false;
      if (!r.ok || !r.json || r.json.success === false) {
        var err = (r.json && (r.json.message || (r.json.errors && Object.values(r.json.errors)[0]))) || ('Save failed (HTTP ' + r.status + ').');
        if (Array.isArray(err)) err = err[0];
        if (r.status === 419) err = 'Your session expired. Reload the page and try again.';
        self.setMsg(String(err), 'err');
        self.updateSaveState();
        return;
      }
      var updated = r.json.print_area;
      if (updated) {
        self.areaById[updated.id] = updated;
        var list = self.data.printAreas;
        for (var i = 0; i < list.length; i++) if (list[i].id === updated.id) list[i] = updated;
      }
      delete self.drafts[id];
      self.renderTabs();
      self.refresh(false);
      self.setMsg(r.json.message || 'Saved.', 'ok');
    }).catch(function () {
      self.saving = false;
      self.setMsg('Could not reach the server. Check your connection and try again.', 'err');
      self.updateSaveState();
    });
  };

  /** Called by form pages when area fields (title, size, unit, position, image) change. */
  P.areaFieldsChanged = function () {
    this.pickDefaultColor(this.colorKey);
    this.renderSwatches();
    this.refresh(true);
  };

  // public API
  global.PrintAreaPreview = PrintAreaPreview;
  global.PrintAreaPreview.math = {
    LOGICAL: LOGICAL,
    canvasSizeFor: canvasSizeFor,
    derivedRegion: derivedRegion,
    appRegion: appRegion,
    physicalPrintSize: physicalPrintSize,
    resolvePlacement: resolvePlacement,
    normalizeArea: normalizeArea,
    clampRegion: clampRegion
  };
})(window);
