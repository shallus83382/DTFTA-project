@extends('layouts.app')

@section('title', 'DTFTA CRM - Shipping & Fulfillment')
@section('page-title', 'Shipping & Fulfillment')

@section('content')
    <style>
        .ship-wrap { display: grid; gap: 16px; }
        .ship-top {
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 16px;
        }
        .ship-card {
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.93));
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 8px 20px rgba(2, 6, 23, 0.14);
        }
        .ship-card h3 {
            margin: 0 0 12px;
            color: #f8fafc;
            font-size: 17px;
        }
        .ship-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .ship-grid .full { grid-column: 1 / -1; }
        .ship-card label {
            display: block;
            font-size: 12px;
            color: #bfdbfe;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .ship-card input, .ship-card select, .ship-card textarea {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.32);
            border-radius: 9px;
            padding: 10px 12px;
            background: rgba(15, 23, 42, 0.65);
            color: #e2e8f0;
            font-size: 13px;
        }
        .ship-card textarea { min-height: 90px; resize: vertical; }
        .ship-card input:focus, .ship-card select:focus, .ship-card textarea:focus {
            outline: none;
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }
        .ship-note {
            margin: 10px 0 0;
            padding: 9px 10px;
            border-radius: 9px;
            background: rgba(30, 64, 175, 0.22);
            border: 1px solid rgba(59, 130, 246, 0.35);
            color: #dbeafe;
            font-size: 12px;
        }
        .ship-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .ship-btn {
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: transform .2s ease, filter .2s ease;
        }
        .ship-btn:hover { transform: translateY(-2px); filter: brightness(1.08); }
        .ship-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .ship-btn.primary { background: linear-gradient(135deg, #0284c7, #2563eb); }
        .ship-btn.secondary { background: linear-gradient(135deg, #0f766e, #059669); }
        .ship-btn.warn { background: linear-gradient(135deg, #b45309, #d97706); }
        .ship-btn.danger { background: linear-gradient(135deg, #b91c1c, #dc2626); }
        .ship-toolbar {
            display: grid;
            grid-template-columns: 1fr 180px 180px auto;
            gap: 10px;
            align-items: end;
        }
        .ship-table-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }
        .ship-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .ship-table th, .ship-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            color: var(--text-primary);
            text-align: left;
        }
        .ship-table th { background: rgba(15, 23, 42, 0.06); font-size: 12px; color: var(--text-secondary); }
        .ship-row.selected { background: rgba(56, 189, 248, 0.08); }
        .ship-status {
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .ship-status.processing { background: #ffedd5; color: #9a3412; }
        .ship-status.created { background: #dbeafe; color: #1e3a8a; }
        .ship-status.in_transit { background: #ede9fe; color: #5b21b6; }
        .ship-status.delivered { background: #dcfce7; color: #166534; }
        .ship-status.cancelled { background: #fee2e2; color: #991b1b; }
        .ship-empty { padding: 26px; text-align: center; color: var(--text-muted); }
        .ship-flash {
            display: none;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .ship-flash.success { display:block; background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .ship-flash.error { display:block; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .ship-link {
            display: inline-block;
            color: #38bdf8;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }
        .ship-link:hover { color: #7dd3fc; text-decoration: underline; }
        @media (max-width: 1080px) {
            .ship-top { grid-template-columns: 1fr; }
            .ship-grid { grid-template-columns: 1fr; }
            .ship-toolbar { grid-template-columns: 1fr; }
        }
    </style>

    <div class="ship-wrap" id="shippingModule">
        <div id="shipFlash" class="ship-flash"></div>

        <section class="ship-top">
            <article class="ship-card">
                <h3>Shipment Details</h3>
                <div class="ship-grid">
                    <div class="full">
                        <label for="shipOrderId">Order</label>
                        <select id="shipOrderId">
                            <option value="">Select order</option>
                            @foreach($ordersForShipping as $order)
                                <option value="{{ $order->id }}" data-shop-id="{{ $order->shop_id }}">
                                    #{{ $order->id }} | {{ $order->order_number ?: 'No Order Number' }} | {{ $order->shop->shop_domain ?? 'Unknown Shop' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="shipShopId">Shop</label>
                        <select id="shipShopId">
                            <option value="">Select shop</option>
                            @foreach($shopsForShipping as $shop)
                                <option value="{{ $shop->id }}">{{ $shop->shop_domain }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="shipServiceId">Fulfillment Service</label>
                        <select id="shipServiceId">
                            <option value="">Manual / None</option>
                            @foreach($servicesForShipping as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="shipCarrier">Carrier</label>
                        <select id="shipCarrier">
                            <option value="">Select carrier</option>
                            @foreach(($shippingConfig['carriers'] ?? ['usps','ups','fedex']) as $carrier)
                                <option value="{{ $carrier }}">{{ strtoupper($carrier) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="shipTrackingNo">Tracking Number</label>
                        <input type="text" id="shipTrackingNo" placeholder="Enter tracking number">
                    </div>
                    <div class="full">
                        <label for="shipTrackingUrl">Tracking URL (optional)</label>
                        <input type="url" id="shipTrackingUrl" placeholder="https://tracking-url.example">
                    </div>
                </div>
                <div class="ship-actions">
                    <button type="button" class="ship-btn primary" id="btnCreateShipment">Generate Shipping Label</button>
                    <button type="button" class="ship-btn secondary" id="btnUpdateTracking" disabled>Add Tracking Number</button>
                    <button type="button" class="ship-btn warn" id="btnPushTracking" disabled>Push Tracking to Shopify</button>
                    <button type="button" class="ship-btn danger" id="btnCancelShipment" disabled>Cancel Shipment</button>
                </div>
            </article>

            <article class="ship-card">
                <h3>Packing Slip Profile</h3>
                <div class="ship-grid">
                    <div>
                        <label for="packBrandName">Brand Name</label>
                        <input type="text" id="packBrandName" placeholder="Brand name">
                    </div>
                    <div>
                        <label for="packSupportContact">Support (email / phone)</label>
                        <input type="text" id="packSupportContact" placeholder="support@domain.com / +1...">
                    </div>
                    <div class="full">
                        <label for="packReturnAddress">Return Address</label>
                        <textarea id="packReturnAddress" placeholder="Street, City, State, ZIP, Country"></textarea>
                    </div>
                </div>
                <div class="ship-actions">
                    <button type="button" class="ship-btn secondary" id="btnSaveProfile">Save Profile</button>
                </div>
                <p class="ship-note">Packing slip always uses merchant profile details. DTFTA branding is not included.</p>
            </article>
        </section>

        <section class="ship-card">
            <h3>Shipment Queue</h3>
            <div class="ship-toolbar">
                <input type="text" id="shipSearch" placeholder="Search by shipment/order/tracking">
                <select id="shipFilterStatus">
                    <option value="">All Status</option>
                    @foreach($shipmentStatuses as $statusKey => $status)
                        <option value="{{ $statusKey }}">{{ $status['label'] ?? strtoupper($statusKey) }}</option>
                    @endforeach
                    <option value="processing">Processing</option>
                    <option value="created">Created</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select id="shipFilterCarrier">
                    <option value="">All Carriers</option>
                    @foreach(($shippingConfig['carriers'] ?? ['usps','ups','fedex']) as $carrier)
                        <option value="{{ $carrier }}">{{ strtoupper($carrier) }}</option>
                    @endforeach
                </select>
                <button type="button" class="ship-btn secondary" id="btnRefreshShipments">Refresh</button>
            </div>
            <div class="ship-table-wrap" style="margin-top:12px;">
                <table class="ship-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Order</th>
                            <th>Shop</th>
                            <th>Carrier</th>
                            <th>Tracking</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="shipmentsTbody"></tbody>
                </table>
                <div id="shipmentsEmpty" class="ship-empty" style="display:none;">No shipments found.</div>
            </div>
        </section>
    </div>

    <script>
        const shippingState = {
            selectedShipmentId: null,
            shipments: @json($recentShipments),
            profilesByShop: @json($profilesForShipping),
            orders: @json($ordersForShipping),
            shops: @json($shopsForShipping),
            autoRefreshTimer: null,
        };

        function getAuthToken() {
            return localStorage.getItem('auth_token');
        }

        async function apiRequest(path, options = {}) {
            const token = getAuthToken();
            const headers = Object.assign({
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }, options.headers || {});
            if (token) headers['Authorization'] = 'Bearer ' + token;

            const response = await fetch(path, Object.assign({}, options, { headers }));
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Request failed');
            }
            return data;
        }

        function showFlash(message, type = 'success') {
            const box = document.getElementById('shipFlash');
            box.className = `ship-flash ${type}`;
            box.textContent = message;
            setTimeout(() => {
                box.className = 'ship-flash';
                box.textContent = '';
            }, 3200);
        }

        function normalizeShipmentsResponse(payload) {
            if (!payload) return [];
            if (Array.isArray(payload.data)) return payload.data;
            if (payload.data && Array.isArray(payload.data.data)) return payload.data.data;
            if (Array.isArray(payload)) return payload;
            return [];
        }

        function getStatusClass(status) {
            const key = (status || 'processing').toString().toLowerCase().replace(/\s+/g, '_');
            return key;
        }

        function renderShipmentsTable() {
            const tbody = document.getElementById('shipmentsTbody');
            const empty = document.getElementById('shipmentsEmpty');
            const search = (document.getElementById('shipSearch').value || '').trim().toLowerCase();
            const filterStatus = (document.getElementById('shipFilterStatus').value || '').trim().toLowerCase();
            const filterCarrier = (document.getElementById('shipFilterCarrier').value || '').trim().toLowerCase();

            const rows = shippingState.shipments.filter((item) => {
                const status = (item.status || '').toString().toLowerCase();
                const carrier = (item.carrier || item.tracking_company || '').toString().toLowerCase();
                const tokens = [
                    item.id,
                    item.order_id,
                    item.tracking_number,
                    item.shipment_id,
                    carrier
                ].map(v => String(v || '').toLowerCase()).join(' ');

                const matchesSearch = !search || tokens.includes(search);
                const matchesStatus = !filterStatus || status === filterStatus;
                const matchesCarrier = !filterCarrier || carrier === filterCarrier;
                return matchesSearch && matchesStatus && matchesCarrier;
            });

            tbody.innerHTML = '';
            rows.forEach((item) => {
                const tr = document.createElement('tr');
                tr.className = 'ship-row' + (shippingState.selectedShipmentId === item.id ? ' selected' : '');
                tr.dataset.shipmentId = item.id;
                tr.innerHTML = `
                    <td>#${item.id}</td>
                    <td>#${item.order_id ?? '-'}</td>
                    <td>${item.shop?.shop_domain ?? ('Shop #' + (item.shop_id ?? '-'))}</td>
                    <td>${(item.carrier || item.tracking_company || '-').toString().toUpperCase()}</td>
                    <td>${item.tracking_number || '-'}</td>
                    <td><span class="ship-status ${getStatusClass(item.status)}">${(item.status || 'processing').replace(/_/g, ' ')}</span></td>
                    <td>${item.created_at ? new Date(item.created_at).toLocaleString() : '-'}</td>
                    <td><a class="ship-link" href="/crm/shipping/detail/${item.id}">View Detail</a></td>
                `;
                tr.addEventListener('click', () => selectShipment(item.id));
                tbody.appendChild(tr);
            });

            empty.style.display = rows.length ? 'none' : 'block';
        }

        function selectShipment(id) {
            shippingState.selectedShipmentId = id;
            const shipment = shippingState.shipments.find(s => s.id === id);
            document.getElementById('btnUpdateTracking').disabled = !shipment;
            document.getElementById('btnPushTracking').disabled = !shipment;
            document.getElementById('btnCancelShipment').disabled = !shipment;

            if (shipment) {
                document.getElementById('shipOrderId').value = shipment.order_id || '';
                document.getElementById('shipShopId').value = shipment.shop_id || '';
                document.getElementById('shipServiceId').value = shipment.fulfillment_service_id || '';
                document.getElementById('shipCarrier').value = shipment.carrier || shipment.tracking_company || '';
                document.getElementById('shipTrackingNo').value = shipment.tracking_number || '';
                document.getElementById('shipTrackingUrl').value = shipment.tracking_url || '';
                hydrateProfileFromShop(shipment.shop_id || '');
            }
            renderShipmentsTable();
        }

        function hydrateProfileFromShop(shopId) {
            if (!shopId) return;
            const profile = shippingState.profilesByShop?.[shopId] || null;
            document.getElementById('packBrandName').value = profile?.brand_name || '';
            document.getElementById('packSupportContact').value = [profile?.support_email, profile?.support_phone].filter(Boolean).join(' / ');
            const parts = [
                profile?.return_address_street,
                profile?.return_address_city,
                profile?.return_address_state,
                profile?.return_address_zip,
                profile?.return_address_country
            ].filter(Boolean);
            document.getElementById('packReturnAddress').value = parts.join(', ');
        }

        async function loadShipments() {
            const res = await apiRequest('/api/v1/shipments');
            shippingState.shipments = normalizeShipmentsResponse(res);
            renderShipmentsTable();
        }

        async function createShipment() {
            const orderId = document.getElementById('shipOrderId').value;
            const shopId = document.getElementById('shipShopId').value;
            const serviceId = document.getElementById('shipServiceId').value;
            const carrier = document.getElementById('shipCarrier').value;
            const trackingNumber = document.getElementById('shipTrackingNo').value.trim();
            const trackingUrl = document.getElementById('shipTrackingUrl').value.trim();

            if (!orderId || !shopId || !trackingNumber) {
                showFlash('Order, shop and tracking number are required.', 'error');
                return;
            }

            let lineItems = [];
            try {
                const summary = await apiRequest(`/api/v1/orders/${orderId}/summary`);
                lineItems = (summary?.data?.items || []).map(i => ({
                    id: i.id,
                    quantity: i.quantity || 1,
                }));
            } catch (_) {}

            const payload = {
                order_id: Number(orderId),
                shop_id: Number(shopId),
                fulfillment_service_id: serviceId ? Number(serviceId) : null,
                line_items: lineItems,
                carrier: carrier || null,
                tracking_company: carrier || null,
                tracking_number: trackingNumber,
                tracking_url: trackingUrl || null,
            };

            const result = await apiRequest('/api/v1/shipments', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            if (result?.data) {
                shippingState.shipments.unshift(result.data);
                shippingState.selectedShipmentId = result.data.id;
                renderShipmentsTable();
            }
            showFlash('Shipment created successfully.');
            await loadShipments();
        }

        async function updateTracking() {
            if (!shippingState.selectedShipmentId) return;
            const carrier = document.getElementById('shipCarrier').value;
            const trackingNumber = document.getElementById('shipTrackingNo').value.trim();
            const trackingUrl = document.getElementById('shipTrackingUrl').value.trim();
            if (!trackingNumber) {
                showFlash('Tracking number is required for update.', 'error');
                return;
            }

            const result = await apiRequest(`/api/v1/shipments/${shippingState.selectedShipmentId}`, {
                method: 'PUT',
                body: JSON.stringify({
                    carrier: carrier || null,
                    tracking_company: carrier || null,
                    tracking_number: trackingNumber,
                    tracking_url: trackingUrl || null,
                })
            });
            if (result?.data) {
                const idx = shippingState.shipments.findIndex(s => s.id === result.data.id);
                if (idx >= 0) shippingState.shipments[idx] = result.data;
                renderShipmentsTable();
            }
            showFlash('Tracking updated successfully.');
            await loadShipments();
        }

        async function pushTracking() {
            if (!shippingState.selectedShipmentId) return;
            const result = await apiRequest(`/api/v1/shipments/${shippingState.selectedShipmentId}`, {
                method: 'PUT',
                body: JSON.stringify({ status: 'in_transit' })
            });
            if (result?.data) {
                const idx = shippingState.shipments.findIndex(s => s.id === result.data.id);
                if (idx >= 0) shippingState.shipments[idx] = result.data;
                renderShipmentsTable();
            }
            showFlash('Tracking pushed to Shopify queue.');
            await loadShipments();
        }

        async function cancelShipment() {
            if (!shippingState.selectedShipmentId) return;
            const result = await apiRequest(`/api/v1/shipments/${shippingState.selectedShipmentId}/cancel`, {
                method: 'POST',
                body: JSON.stringify({})
            });
            if (result?.data) {
                const idx = shippingState.shipments.findIndex(s => s.id === result.data.id);
                if (idx >= 0) shippingState.shipments[idx] = result.data;
                renderShipmentsTable();
            }
            showFlash('Shipment cancelled.');
            await loadShipments();
        }

        async function saveProfile() {
            const shopId = document.getElementById('shipShopId').value;
            if (!shopId) {
                showFlash('Select a shop before saving profile.', 'error');
                return;
            }

            const support = (document.getElementById('packSupportContact').value || '').trim();
            const [supportEmail = '', ...phoneRest] = support.split('/');
            const supportPhone = phoneRest.join('/').trim();
            const address = (document.getElementById('packReturnAddress').value || '').trim();
            const parts = address.split(',').map(p => p.trim());

            const payload = {
                shop_id: Number(shopId),
                brand_name: document.getElementById('packBrandName').value.trim(),
                return_address_street: parts[0] || null,
                return_address_city: parts[1] || null,
                return_address_state: parts[2] || null,
                return_address_zip: parts[3] || null,
                return_address_country: parts[4] || 'US',
                support_email: supportEmail.trim() || null,
                support_phone: supportPhone || null,
            };

            const res = await apiRequest('/api/v1/partner-profiles', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            shippingState.profilesByShop[String(shopId)] = res.data;
            showFlash('Packing slip profile saved.');
        }

        function bindShippingEvents() {
            document.getElementById('btnCreateShipment').addEventListener('click', async () => {
                try { await createShipment(); } catch (e) { showFlash(e.message, 'error'); }
            });
            document.getElementById('btnUpdateTracking').addEventListener('click', async () => {
                try { await updateTracking(); } catch (e) { showFlash(e.message, 'error'); }
            });
            document.getElementById('btnPushTracking').addEventListener('click', async () => {
                try { await pushTracking(); } catch (e) { showFlash(e.message, 'error'); }
            });
            document.getElementById('btnCancelShipment').addEventListener('click', async () => {
                try { await cancelShipment(); } catch (e) { showFlash(e.message, 'error'); }
            });
            document.getElementById('btnSaveProfile').addEventListener('click', async () => {
                try { await saveProfile(); } catch (e) { showFlash(e.message, 'error'); }
            });
            document.getElementById('btnRefreshShipments').addEventListener('click', async () => {
                try { await loadShipments(); showFlash('Shipment list refreshed.'); } catch (e) { showFlash(e.message, 'error'); }
            });

            document.getElementById('shipOrderId').addEventListener('change', (e) => {
                const selected = e.target.options[e.target.selectedIndex];
                if (selected && selected.dataset.shopId) {
                    document.getElementById('shipShopId').value = selected.dataset.shopId;
                    hydrateProfileFromShop(selected.dataset.shopId);
                }
            });
            document.getElementById('shipShopId').addEventListener('change', (e) => {
                hydrateProfileFromShop(e.target.value);
            });

            ['shipSearch', 'shipFilterStatus', 'shipFilterCarrier'].forEach((id) => {
                document.getElementById(id).addEventListener('input', renderShipmentsTable);
                document.getElementById(id).addEventListener('change', renderShipmentsTable);
            });
        }

        function startAutoRefresh() {
            if (shippingState.autoRefreshTimer) {
                clearInterval(shippingState.autoRefreshTimer);
            }
            shippingState.autoRefreshTimer = setInterval(async () => {
                if (document.hidden) return;
                try {
                    await loadShipments();
                } catch (_) {}
            }, 15000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            bindShippingEvents();
            renderShipmentsTable();
            loadShipments().catch((e) => showFlash(e.message, 'error'));
            startAutoRefresh();
            window.addEventListener('beforeunload', () => {
                if (shippingState.autoRefreshTimer) clearInterval(shippingState.autoRefreshTimer);
            });
        });
    </script>
@endsection
