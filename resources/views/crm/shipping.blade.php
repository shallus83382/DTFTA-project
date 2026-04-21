@extends('layouts.app')

@section('title', 'DTFTA CRM - Shipping & Fulfillment')
@section('page-title', 'Shipping & Fulfillment')

@section('content')
    <style>
        .ship-wrap {
            display: grid;
            gap: 16px;
            position: relative;
        }
        .ship-wrap::before {
            content: "";
            position: absolute;
            inset: -60px -30px auto auto;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.18), rgba(59, 130, 246, 0));
            pointer-events: none;
            z-index: 0;
        }
        .ship-wrap > * {
            position: relative;
            z-index: 1;
        }
        .ship-top {
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 16px;
        }
        .ship-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.96), rgba(15, 23, 42, 0.94));
            border: 1px solid rgba(148, 163, 184, 0.26);
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.22);
        }
        .ship-card::before {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 1px;
            background: linear-gradient(90deg, rgba(56, 189, 248, 0.28), rgba(45, 212, 191, 0.18), rgba(56, 189, 248, 0.28));
            pointer-events: none;
        }
        .ship-card h3 {
            margin: 0 0 12px;
            color: #f8fafc;
            font-size: 26px;
            line-height: 1.15;
            letter-spacing: -0.01em;
            font-weight: 700;
        }
        .ship-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .ship-grid .full { grid-column: 1 / -1; }
        .ship-card label {
            display: block;
            font-size: 12px;
            color: #b9c9e4;
            margin-bottom: 6px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }
        .ship-card input, .ship-card select, .ship-card textarea {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 10px;
            padding: 12px 13px;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.86), rgba(15, 23, 42, 0.76));
            color: #f8fafc;
            font-size: 14px;
            font-weight: 500;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .ship-card select,
        .ship-toolbar select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        .ship-card select option,
        .ship-toolbar select option {
            color: #e2e8f0;
            background: rgba(15, 23, 42, 0.98);
        }
        .ship-card input::placeholder,
        .ship-card textarea::placeholder {
            color: #7f93b2;
            font-size: 13px;
            font-weight: 500;
        }
        .ship-card textarea { min-height: 90px; resize: vertical; }
        .ship-card input:focus, .ship-card select:focus, .ship-card textarea:focus {
            outline: none;
            border-color: rgba(56, 189, 248, 0.75);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.16);
        }
        .ship-note {
            margin: 10px 0 0;
            padding: 10px 11px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.25), rgba(30, 64, 175, 0.12));
            border: 1px solid rgba(96, 165, 250, 0.36);
            color: #dbeafe;
            font-size: 12px;
        }
        .ship-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
            padding: 10px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: linear-gradient(140deg, rgba(15, 23, 42, 0.5), rgba(15, 23, 42, 0.3));
        }
        .ship-btn {
            border: 1px solid transparent;
            border-radius: 10px;
            min-height: 40px;
            padding: 10px 16px;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.01em;
            cursor: pointer;
            transition: transform .2s ease, filter .2s ease, box-shadow .2s ease, border-color .2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
        }
        .ship-btn::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            border: 1px solid rgba(255, 255, 255, 0.12);
            pointer-events: none;
        }
        .ship-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.08);
            box-shadow: 0 10px 20px rgba(2, 6, 23, 0.28);
        }
        .ship-btn:disabled,
        .ship-btn[disabled] {
            opacity: 1;
            color: #cfddf4;
            border-color: rgba(148, 163, 184, 0.2);
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
            filter: saturate(0.8) brightness(0.9);
        }
        .ship-btn.primary {
            background: linear-gradient(135deg, #0ea5e9, #2563eb 55%, #1d4ed8);
            border-color: rgba(125, 211, 252, 0.45);
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.32);
        }
        .ship-btn.secondary {
            background: linear-gradient(135deg, #10b981, #059669 55%, #047857);
            border-color: rgba(110, 231, 183, 0.45);
            box-shadow: 0 10px 22px rgba(5, 150, 105, 0.28);
        }
        .ship-btn.warn {
            background: linear-gradient(135deg, #f59e0b, #d97706 55%, #b45309);
            border-color: rgba(253, 186, 116, 0.45);
            box-shadow: 0 10px 22px rgba(217, 119, 6, 0.28);
        }
        .ship-btn.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626 55%, #b91c1c);
            border-color: rgba(252, 165, 165, 0.45);
            box-shadow: 0 10px 22px rgba(220, 38, 38, 0.28);
        }

        .ship-btn.primary:disabled { background: linear-gradient(135deg, rgba(14, 165, 233, 0.55), rgba(37, 99, 235, 0.55), rgba(29, 78, 216, 0.55)); }
        .ship-btn.secondary:disabled { background: linear-gradient(135deg, rgba(16, 185, 129, 0.55), rgba(5, 150, 105, 0.55), rgba(4, 120, 87, 0.55)); }
        .ship-btn.warn:disabled { background: linear-gradient(135deg, rgba(245, 158, 11, 0.55), rgba(217, 119, 6, 0.55), rgba(180, 83, 9, 0.55)); }
        .ship-btn.danger:disabled { background: linear-gradient(135deg, rgba(239, 68, 68, 0.55), rgba(220, 38, 38, 0.55), rgba(185, 28, 28, 0.55)); }

        .ship-btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(191, 219, 254, 0.25), 0 10px 22px rgba(2, 6, 23, 0.32);
        }
        @media (max-width: 860px) {
            .ship-actions {
                gap: 8px;
                padding: 8px;
            }
            .ship-btn {
                flex: 1 1 calc(50% - 8px);
                min-width: 180px;
            }
        }
        .ship-toolbar {
            display: grid;
            grid-template-columns: 1fr 180px 180px auto;
            gap: 10px;
            align-items: end;
        }
        .ship-table-wrap {
            background: linear-gradient(170deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 12px;
            overflow: hidden;
        }
        .ship-toolbar select,
        .ship-toolbar input {
            color: #f8fafc;
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
        .ship-table th {
            background: rgba(15, 23, 42, 0.35);
            font-size: 11px;
            color: #9fb1cf;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .ship-table tbody tr {
            transition: background 0.2s ease;
        }
        .ship-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.1);
        }
        .ship-row.selected { background: rgba(56, 189, 248, 0.08); }
        .ship-status {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: inline-block;
            border: 1px solid transparent;
        }
        .ship-status.processing { background: rgba(245, 158, 11, 0.18); color: #fcd34d; border-color: rgba(245, 158, 11, 0.45); }
        .ship-status.created { background: rgba(59, 130, 246, 0.18); color: #93c5fd; border-color: rgba(59, 130, 246, 0.45); }
        .ship-status.in_transit { background: rgba(139, 92, 246, 0.18); color: #c4b5fd; border-color: rgba(139, 92, 246, 0.45); }
        .ship-status.delivered { background: rgba(16, 185, 129, 0.18); color: #6ee7b7; border-color: rgba(16, 185, 129, 0.45); }
        .ship-status.cancelled { background: rgba(239, 68, 68, 0.18); color: #fca5a5; border-color: rgba(239, 68, 68, 0.45); }
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
            border: 1px solid rgba(56, 189, 248, 0.28);
            border-radius: 8px;
            padding: 5px 8px;
            background: rgba(15, 23, 42, 0.5);
        }
        .ship-link:hover {
            color: #e0f2fe;
            border-color: rgba(125, 211, 252, 0.5);
            background: rgba(14, 165, 233, 0.12);
            text-decoration: none;
        }
        @media (max-width: 1080px) {
            .ship-top { grid-template-columns: 1fr; }
            .ship-grid { grid-template-columns: 1fr; }
            .ship-toolbar { grid-template-columns: 1fr; }
        }
        @media (max-width: 991px) {
            .ship-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                max-width: 100%;
            }
            .ship-table {
                min-width: 720px;
            }
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
