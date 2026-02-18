@extends('layouts.app')

@section('title', 'DTFTA CRM - Store Details')
@section('page-title', 'Store Details')

@section('content')
    @if(session('success'))
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #166534;">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <!-- Store info -->
    <section class="chart-card store-detail-card">
        <h3><span class="section-icon section-icon-store" aria-hidden="true"></span>Store info</h3>
        <div class="store-detail-grid">
            <div class="detail-row"><span class="detail-label">Store name (domain)</span><span>{{ $store->shop_domain }}</span></div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                @php
                    $statusClass = match(strtolower($store->status ?? 'active')) {
                        'active' => 'status-shipped',
                        'suspended' => 'status-production',
                        'uninstalled' => 'status-cancelled',
                        default => 'status-new',
                    };
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ ucfirst($store->status ?? 'active') }}</span>
            </div>
            <div class="detail-row"><span class="detail-label">Total orders</span><span>{{ number_format((int) ($store->total_orders_count ?? 0)) }}</span></div>
            <div class="detail-row"><span class="detail-label">Pending jobs</span><span>{{ number_format((int) ($store->pending_jobs_count ?? 0)) }}</span></div>
            <div class="detail-row"><span class="detail-label">Total revenue</span><span>${{ number_format((float) ($storeMetrics['total_revenue'] ?? 0), 2) }}</span></div>
            <div class="detail-row"><span class="detail-label">Revenue (Last 30 days)</span><span>${{ number_format((float) ($storeMetrics['revenue_last_30_days'] ?? 0), 2) }}</span></div>
            <div class="detail-row"><span class="detail-label">Avg order value</span><span>${{ number_format((float) ($storeMetrics['avg_order_value'] ?? 0), 2) }}</span></div>
            <div class="detail-row"><span class="detail-label">Fulfilled orders</span><span>{{ number_format((int) ($storeMetrics['fulfilled_orders'] ?? 0)) }}</span></div>
            <div class="detail-row"><span class="detail-label">Connected since</span><span>{{ optional($store->installed_at)->format('Y-m-d') ?? optional($store->created_at)->format('Y-m-d') }}</span></div>
        </div>

        <form method="POST" action="{{ route('crm.store.update-status', $store->id) }}" style="margin-top: 16px;">
            @csrf
            <div class="filters-section" style="margin-bottom: 0;">
                <div class="filter-group">
                    <label for="status">Update Status</label>
                    <select id="status" name="status" class="filter-select">
                        @foreach($storeStatusOptions as $statusOption)
                            <option value="{{ $statusOption }}" {{ $store->status === $statusOption ? 'selected' : '' }}>
                                {{ ucfirst($statusOption) }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <small style="color: #e53e3e;">{{ $message }}</small>
                    @enderror
                </div>
                <button type="submit" class="btn-primary">Save Status</button>
            </div>
        </form>
    </section>

    <!-- Partner profile -->
    <section class="chart-card store-detail-card">
        <h3><span class="section-icon section-icon-user" aria-hidden="true"></span>Partner profile</h3>
        <form method="POST" action="{{ route('crm.store.upsert-profile', $store->id) }}">
            @csrf
            <div class="filters-section">
                <div class="filter-group">
                    <label for="brand_name">Brand Name</label>
                    <input id="brand_name" name="brand_name" type="text" class="filter-select" value="{{ old('brand_name', $partnerProfile->brand_name ?? '') }}">
                </div>
                <div class="filter-group">
                    <label for="support_email">Support Email</label>
                    <input id="support_email" name="support_email" type="email" class="filter-select" value="{{ old('support_email', $partnerProfile->support_email ?? '') }}">
                </div>
                <div class="filter-group">
                    <label for="support_phone">Support Phone</label>
                    <input id="support_phone" name="support_phone" type="text" class="filter-select" value="{{ old('support_phone', $partnerProfile->support_phone ?? '') }}">
                </div>
                <div class="filter-group">
                    <label for="return_address_street">Return Street</label>
                    <input id="return_address_street" name="return_address_street" type="text" class="filter-select" value="{{ old('return_address_street', $partnerProfile->return_address_street ?? '') }}">
                </div>
                <div class="filter-group">
                    <label for="return_address_city">City</label>
                    <input id="return_address_city" name="return_address_city" type="text" class="filter-select" value="{{ old('return_address_city', $partnerProfile->return_address_city ?? '') }}">
                </div>
                <div class="filter-group">
                    <label for="return_address_state">State</label>
                    <input id="return_address_state" name="return_address_state" type="text" class="filter-select" value="{{ old('return_address_state', $partnerProfile->return_address_state ?? '') }}">
                </div>
                <div class="filter-group">
                    <label for="return_address_zip">ZIP</label>
                    <input id="return_address_zip" name="return_address_zip" type="text" class="filter-select" value="{{ old('return_address_zip', $partnerProfile->return_address_zip ?? '') }}">
                </div>
                <div class="filter-group">
                    <label for="return_address_country">Country</label>
                    <input id="return_address_country" name="return_address_country" type="text" class="filter-select" value="{{ old('return_address_country', $partnerProfile->return_address_country ?? 'US') }}">
                </div>
                <button type="submit" class="btn-primary">Save Partner Profile</button>
            </div>
        </form>
    </section>

    <!-- Order history -->
    <section class="chart-card store-detail-card">
        <h3><span class="section-icon section-icon-list" aria-hidden="true"></span>Order history</h3>

        <form id="storeOrderHistoryFilterForm" method="GET" action="{{ route('crm.store-detail', $store->id) }}" class="filters-section">
            <div class="filter-group">
                <label for="order_status">Order Status</label>
                <select id="order_status" name="order_status" class="filter-select">
                    <option value="">All</option>
                    @foreach($orderStatusOptions as $statusOption)
                        <option value="{{ $statusOption }}" {{ $orderStatusFilter === $statusOption ? 'selected' : '' }}>
                            {{ strtoupper(str_replace('_', ' ', $statusOption)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-secondary">Apply</button>
            <a href="{{ route('crm.store-detail', $store->id) }}" class="btn-secondary">Clear</a>
        </form>

        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Jobs</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $orderStatus = strtolower($order->fulfillment_status ?? 'pending');
                            $orderStatusClass = str_replace('_', '-', $orderStatus);
                            $orderStatusLabel = strtoupper(str_replace('_', ' ', $orderStatus));
                        @endphp
                        <tr>
                            <td>#{{ $order->order_number ?? $order->id }}</td>
                            <td>{{ optional($order->created_at)->format('Y-m-d') }}</td>
                            <td><span class="status-badge status-{{ $orderStatusClass }}">{{ $orderStatusLabel }}</span></td>
                            <td>{{ number_format((int) ($order->order_items_count ?? 0)) }}</td>
                            <td>{{ number_format((int) ($order->jobs_count ?? 0)) }}</td>
                            <td><a href="{{ route('crm.order-detail', $order->id) }}" class="btn-link">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: #999;">No orders found for this store.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination" style="margin-top: 16px;">
            @if($orders->onFirstPage())
                <button class="btn-pagination" disabled>Previous</button>
            @else
                <a href="{{ $orders->previousPageUrl() }}" class="btn-pagination">Previous</a>
            @endif
            <span class="page-info">Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</span>
            @if($orders->hasMorePages())
                <a href="{{ $orders->nextPageUrl() }}" class="btn-pagination">Next</a>
            @else
                <button class="btn-pagination" disabled>Next</button>
            @endif
        </div>
    </section>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('storeOrderHistoryFilterForm');
            const orderStatus = document.getElementById('order_status');
            if (!form || !orderStatus) return;

            orderStatus.addEventListener('change', function () {
                form.requestSubmit();
            });
        });
    </script>
    @endpush
@endsection
