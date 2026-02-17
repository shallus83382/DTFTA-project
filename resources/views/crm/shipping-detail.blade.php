@extends('layouts.app')

@section('title', 'DTFTA CRM - Shipment Detail')
@section('page-title', 'Shipment Detail')

@section('content')
    @php
        $ship = $shipment;
        $shop = $ship->shop;
        $order = $ship->order;
    @endphp
    <style>
        .sd-wrap { display: grid; gap: 16px; }
        .sd-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .sd-back {
            text-decoration: none;
            color: #7dd3fc;
            font-size: 13px;
            font-weight: 600;
        }
        .sd-grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: 16px; }
        .sd-card {
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.93));
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 10px 24px rgba(2, 6, 23, 0.2);
        }
        .sd-card h3 { margin: 0 0 12px; color: #f8fafc; font-size: 17px; }
        .sd-list { display: grid; gap: 8px; margin-top: 8px; }
        .sd-list div { display: flex; justify-content: space-between; gap: 8px; color: #cbd5e1; font-size: 13px; }
        .sd-list b { color: #fff; font-weight: 600; text-align: right; }
        .sd-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .sd-full { grid-column: 1 / -1; }
        .sd-card label { display:block; margin-bottom:6px; color:#bfdbfe; font-size:12px; font-weight:600; }
        .sd-card input, .sd-card select, .sd-card textarea {
            width:100%; border:1px solid rgba(148,163,184,.35); border-radius:10px; background:rgba(15,23,42,.7);
            color:#e2e8f0; padding:10px 12px; font-size:13px;
        }
        .sd-card textarea { min-height: 86px; resize: vertical; }
        .sd-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
        .sd-btn {
            border:none; border-radius:10px; padding:10px 14px; color:#fff; font-size:13px; font-weight:600; cursor:pointer;
            transition: transform .2s ease, filter .2s ease;
        }
        .sd-btn:hover { transform: translateY(-2px); filter: brightness(1.08); }
        .sd-btn.primary { background: linear-gradient(135deg, #0284c7, #2563eb); }
        .sd-btn.secondary { background: linear-gradient(135deg, #0f766e, #059669); }
        .sd-btn.warn { background: linear-gradient(135deg, #b45309, #d97706); }
        .sd-btn.danger { background: linear-gradient(135deg, #b91c1c, #dc2626); }
        .sd-status {
            display:inline-block; border-radius:999px; padding:6px 10px; font-size:11px; font-weight:700;
            text-transform:uppercase; background:#dbeafe; color:#1e3a8a;
        }
        .sd-timeline { display:grid; gap:10px; margin-top:10px; }
        .sd-time-item { border:1px solid rgba(148,163,184,.2); border-radius:10px; padding:10px; color:#cbd5e1; font-size:12px; }
        .sd-flash { display:none; padding:10px 12px; border-radius:10px; font-size:12px; font-weight:600; }
        .sd-flash.success { display:block; background:#dcfce7; color:#166534; border:1px solid #86efac; }
        .sd-flash.error { display:block; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        @media (max-width: 1100px) { .sd-grid, .sd-grid2 { grid-template-columns: 1fr; } .sd-full { grid-column: auto; } }
    </style>

    <div class="sd-wrap">
        <div class="sd-head">
            <a href="{{ route('crm.shipping') }}" class="sd-back">← Back to Shipping</a>
            <span id="sdStatusBadge" class="sd-status">{{ strtoupper($ship->status ?? 'processing') }}</span>
        </div>
        <div id="sdFlash" class="sd-flash"></div>

        <section class="sd-grid">
            <article class="sd-card">
                <h3>Shipment Overview</h3>
                <div class="sd-list">
                    <div><span>Shipment ID</span><b>#{{ $ship->id }}</b></div>
                    <div><span>External Shipment ID</span><b>{{ $ship->shipment_id ?: 'N/A' }}</b></div>
                    <div><span>Order</span><b>#{{ $ship->order_id ?: 'N/A' }}</b></div>
                    <div><span>Job</span><b>#{{ $ship->job_id ?: 'N/A' }}</b></div>
                    <div><span>Shop</span><b>{{ $shop->shop_domain ?? ('Shop #'.$ship->shop_id) }}</b></div>
                    <div><span>Fulfillment Service</span><b>{{ $ship->fulfillmentService->name ?? 'Manual' }}</b></div>
                    <div><span>Created</span><b>{{ optional($ship->created_at)->format('Y-m-d H:i') }}</b></div>
                    <div><span>Updated</span><b>{{ optional($ship->updated_at)->format('Y-m-d H:i') }}</b></div>
                </div>
                <div class="sd-actions">
                    @if($order)
                        <a href="{{ route('crm.order-detail', $order->id) }}" class="sd-btn secondary" style="text-decoration:none;">Open Order Detail</a>
                    @endif
                </div>
            </article>

            <article class="sd-card">
                <h3>Tracking & Status</h3>
                <div class="sd-grid2">
                    <div>
                        <label for="sdCarrier">Carrier</label>
                        <input id="sdCarrier" type="text" value="{{ $ship->carrier ?? $ship->tracking_company }}">
                    </div>
                    <div>
                        <label for="sdStatus">Status</label>
                        <select id="sdStatus">
                            <option value="processing" @selected(($ship->status ?? '') === 'processing')>Processing</option>
                            <option value="created" @selected(($ship->status ?? '') === 'created')>Created</option>
                            <option value="in_transit" @selected(($ship->status ?? '') === 'in_transit')>In Transit</option>
                            <option value="delivered" @selected(($ship->status ?? '') === 'delivered')>Delivered</option>
                            <option value="cancelled" @selected(($ship->status ?? '') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label for="sdTrackingNo">Tracking Number</label>
                        <input id="sdTrackingNo" type="text" value="{{ $ship->tracking_number }}">
                    </div>
                    <div>
                        <label for="sdTrackingUrl">Tracking URL</label>
                        <input id="sdTrackingUrl" type="url" value="{{ $ship->tracking_url }}">
                    </div>
                </div>
                <div class="sd-actions">
                    <button type="button" class="sd-btn primary" id="sdUpdateBtn">Save Updates</button>
                    <button type="button" class="sd-btn warn" id="sdPushBtn">Push To Shopify</button>
                    <button type="button" class="sd-btn danger" id="sdCancelBtn">Cancel Shipment</button>
                    <button type="button" class="sd-btn danger" id="sdDeleteBtn">Delete Shipment</button>
                </div>
            </article>
        </section>

        <section class="sd-grid">
            <article class="sd-card">
                <h3>Packing Slip Profile</h3>
                <div class="sd-grid2">
                    <div>
                        <label for="sdBrandName">Brand Name</label>
                        <input id="sdBrandName" type="text" value="{{ $shopProfile->brand_name ?? '' }}">
                    </div>
                    <div>
                        <label for="sdSupportEmail">Support Email</label>
                        <input id="sdSupportEmail" type="email" value="{{ $shopProfile->support_email ?? '' }}">
                    </div>
                    <div>
                        <label for="sdSupportPhone">Support Phone</label>
                        <input id="sdSupportPhone" type="text" value="{{ $shopProfile->support_phone ?? '' }}">
                    </div>
                    <div class="sd-full">
                        <label for="sdReturnAddress">Return Address</label>
                        <textarea id="sdReturnAddress">{{ implode(', ', array_filter([$shopProfile->return_address_street ?? null, $shopProfile->return_address_city ?? null, $shopProfile->return_address_state ?? null, $shopProfile->return_address_zip ?? null, $shopProfile->return_address_country ?? null])) }}</textarea>
                    </div>
                </div>
                <div class="sd-actions">
                    <button type="button" class="sd-btn secondary" id="sdSaveProfileBtn">Save Profile</button>
                </div>
            </article>

            <article class="sd-card">
                <h3>Shipment Timeline</h3>
                <div class="sd-timeline" id="sdTimeline">
                    <div class="sd-time-item"><b>Created:</b> {{ optional($ship->created_at)->diffForHumans() }}</div>
                    <div class="sd-time-item"><b>Status:</b> {{ strtoupper($ship->status ?? 'processing') }}</div>
                    @if($ship->shipment_id)
                        <div class="sd-time-item"><b>Synced:</b> External shipment id `{{ $ship->shipment_id }}` available.</div>
                    @endif
                    @if($ship->updated_at)
                        <div class="sd-time-item"><b>Updated:</b> {{ optional($ship->updated_at)->diffForHumans() }}</div>
                    @endif
                </div>
            </article>
        </section>
    </div>

    <script>
        const shipmentId = {{ (int)$ship->id }};
        const shopId = {{ (int)($ship->shop_id ?? 0) }};

        function sdToken() { return localStorage.getItem('auth_token'); }

        async function sdRequest(path, options = {}) {
            const headers = Object.assign({
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }, options.headers || {});
            const token = sdToken();
            if (token) headers['Authorization'] = 'Bearer ' + token;
            const res = await fetch(path, Object.assign({}, options, { headers }));
            const body = await res.json().catch(() => ({}));
            if (!res.ok || body.success === false) {
                throw new Error(body.message || 'Request failed');
            }
            return body;
        }

        function sdFlash(msg, type = 'success') {
            const el = document.getElementById('sdFlash');
            el.className = `sd-flash ${type}`;
            el.textContent = msg;
            setTimeout(() => {
                el.className = 'sd-flash';
                el.textContent = '';
            }, 3200);
        }

        let sdTimer = null;

        function refreshStatusBadge(status) {
            const badge = document.getElementById('sdStatusBadge');
            badge.textContent = (status || 'processing').replace(/_/g, ' ').toUpperCase();
        }

        function applyShipmentToForm(data) {
            if (!data) return;
            document.getElementById('sdCarrier').value = data.carrier || data.tracking_company || '';
            document.getElementById('sdTrackingNo').value = data.tracking_number || '';
            document.getElementById('sdTrackingUrl').value = data.tracking_url || '';
            if (data.status) {
                document.getElementById('sdStatus').value = data.status;
                refreshStatusBadge(data.status);
            }
        }

        async function updateShipment() {
            const carrier = document.getElementById('sdCarrier').value.trim();
            const status = document.getElementById('sdStatus').value;
            const trackingNumber = document.getElementById('sdTrackingNo').value.trim();
            const trackingUrl = document.getElementById('sdTrackingUrl').value.trim();
            if (!trackingNumber) throw new Error('Tracking number is required.');

            const res = await sdRequest(`/api/v1/shipments/${shipmentId}`, {
                method: 'PUT',
                body: JSON.stringify({
                    carrier: carrier || null,
                    tracking_company: carrier || null,
                    status: status || null,
                    tracking_number: trackingNumber,
                    tracking_url: trackingUrl || null
                })
            });
            refreshStatusBadge(res?.data?.status || status);
            sdFlash('Shipment updated successfully.');
        }

        async function pushToShopify() {
            const res = await sdRequest(`/api/v1/shipments/${shipmentId}`, {
                method: 'PUT',
                body: JSON.stringify({ status: 'in_transit' })
            });
            document.getElementById('sdStatus').value = 'in_transit';
            refreshStatusBadge(res?.data?.status || 'in_transit');
            sdFlash('Shipment pushed to Shopify queue.');
        }

        async function cancelShipment() {
            const res = await sdRequest(`/api/v1/shipments/${shipmentId}/cancel`, {
                method: 'POST',
                body: JSON.stringify({})
            });
            document.getElementById('sdStatus').value = 'cancelled';
            refreshStatusBadge(res?.data?.status || 'cancelled');
            sdFlash('Shipment cancelled.');
        }

        async function deleteShipment() {
            const ok = window.confirm('Delete this shipment permanently?');
            if (!ok) return;
            await sdRequest(`/api/v1/shipments/${shipmentId}`, { method: 'DELETE' });
            sdFlash('Shipment deleted successfully.');
            setTimeout(() => { window.location.href = '{{ route('crm.shipping') }}'; }, 700);
        }

        async function saveProfile() {
            if (!shopId) throw new Error('Shop not found for this shipment.');
            const rawAddress = (document.getElementById('sdReturnAddress').value || '').trim();
            const parts = rawAddress.split(',').map(x => x.trim());
            await sdRequest('/api/v1/partner-profiles', {
                method: 'POST',
                body: JSON.stringify({
                    shop_id: shopId,
                    brand_name: document.getElementById('sdBrandName').value.trim(),
                    support_email: document.getElementById('sdSupportEmail').value.trim() || null,
                    support_phone: document.getElementById('sdSupportPhone').value.trim() || null,
                    return_address_street: parts[0] || null,
                    return_address_city: parts[1] || null,
                    return_address_state: parts[2] || null,
                    return_address_zip: parts[3] || null,
                    return_address_country: parts[4] || 'US'
                })
            });
            sdFlash('Packing slip profile saved.');
        }

        async function syncShipment() {
            const res = await sdRequest(`/api/v1/shipments/${shipmentId}`, { method: 'GET' });
            applyShipmentToForm(res?.data);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('sdUpdateBtn').addEventListener('click', async () => {
                try { await updateShipment(); } catch (e) { sdFlash(e.message, 'error'); }
            });
            document.getElementById('sdPushBtn').addEventListener('click', async () => {
                try { await pushToShopify(); } catch (e) { sdFlash(e.message, 'error'); }
            });
            document.getElementById('sdCancelBtn').addEventListener('click', async () => {
                try { await cancelShipment(); } catch (e) { sdFlash(e.message, 'error'); }
            });
            document.getElementById('sdDeleteBtn').addEventListener('click', async () => {
                try { await deleteShipment(); } catch (e) { sdFlash(e.message, 'error'); }
            });
            document.getElementById('sdSaveProfileBtn').addEventListener('click', async () => {
                try { await saveProfile(); } catch (e) { sdFlash(e.message, 'error'); }
            });

            sdTimer = setInterval(async () => {
                if (document.hidden) return;
                try { await syncShipment(); } catch (_) {}
            }, 15000);
            window.addEventListener('beforeunload', () => {
                if (sdTimer) clearInterval(sdTimer);
            });
        });
    </script>
@endsection
