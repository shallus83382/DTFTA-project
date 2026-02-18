@extends('layouts.app')

@section('title', 'DTFTA CRM - Stores & Partners')
@section('page-title', 'Stores & Partners')

@section('content')
    @if(session('success'))
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #166534;">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <p class="stores-intro">All connected Shopify stores - view and manage partners from your CRM.</p>

    <form id="storesFiltersForm" method="GET" action="{{ route('crm.stores') }}" class="filters-section">
        <div class="filter-group">
            <label for="search">Search</label>
            <input id="search" name="search" type="text" placeholder="Domain, brand, support email" class="filter-select"
                value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="filter-select">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                @foreach($storeStatusOptions as $statusOption)
                    <option value="{{ $statusOption }}" {{ ($filters['status'] ?? '') === $statusOption ? 'selected' : '' }}>
                        {{ ucfirst($statusOption) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label for="sort_by">Sort</label>
            <select id="sort_by" name="sort_by" class="filter-select">
                <option value="created_at" {{ ($filters['sort_by'] ?? '') === 'created_at' ? 'selected' : '' }}>Newest</option>
                <option value="shop_domain" {{ ($filters['sort_by'] ?? '') === 'shop_domain' ? 'selected' : '' }}>Store Domain</option>
                <option value="status" {{ ($filters['sort_by'] ?? '') === 'status' ? 'selected' : '' }}>Status</option>
                <option value="installed_at" {{ ($filters['sort_by'] ?? '') === 'installed_at' ? 'selected' : '' }}>Connected Date</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="sort_dir">Direction</label>
            <select id="sort_dir" name="sort_dir" class="filter-select">
                <option value="desc" {{ ($filters['sort_dir'] ?? '') === 'desc' ? 'selected' : '' }}>DESC</option>
                <option value="asc" {{ ($filters['sort_dir'] ?? '') === 'asc' ? 'selected' : '' }}>ASC</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="per_page">Per Page</label>
            <select id="per_page" name="per_page" class="filter-select">
                @foreach([10, 25, 50, 100] as $perPageOption)
                    <option value="{{ $perPageOption }}" {{ (int) ($filters['per_page'] ?? 10) === $perPageOption ? 'selected' : '' }}>
                        {{ $perPageOption }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="btn-secondary" type="submit">Apply</button>
        <a href="{{ route('crm.stores') }}" class="btn-secondary">Clear Filters</a>
    </form>

    <div class="table-container store-list-table">
        <table class="jobs-table">
            <thead>
                <tr>
                    <th>Store Name (domain)</th>
                    <th>Brand</th>
                    <th>Status</th>
                    <th>Total Orders</th>
                    <th>Pending Jobs</th>
                    <th>Connected Since</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stores as $store)
                    @php
                        $status = strtolower($store->status ?? 'active');
                        $badgeClass = match($status) {
                            'active' => 'status-shipped',
                            'suspended' => 'status-production',
                            'uninstalled' => 'status-cancelled',
                            default => 'status-new',
                        };
                    @endphp
                    <tr>
                        <td>{{ $store->shop_domain }}</td>
                        <td>{{ $store->partnerProfile->brand_name ?? '-' }}</td>
                        <td><span class="status-badge {{ $badgeClass }}">{{ ucfirst($status) }}</span></td>
                        <td>{{ number_format((int) ($store->total_orders_count ?? 0)) }}</td>
                        <td>{{ number_format((int) ($store->pending_jobs_count ?? 0)) }}</td>
                        <td>{{ optional($store->installed_at)->format('Y-m-d') ?? optional($store->created_at)->format('Y-m-d') }}</td>
                        <td><a href="{{ route('crm.store-detail', $store->id) }}" class="btn-link">Store detail</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">No stores found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        @if($stores->onFirstPage())
            <button class="btn-pagination" disabled>Previous</button>
        @else
            <a href="{{ $stores->previousPageUrl() }}" class="btn-pagination">Previous</a>
        @endif
        <span class="page-info">Page {{ $stores->currentPage() }} of {{ $stores->lastPage() }}</span>
        @if($stores->hasMorePages())
            <a href="{{ $stores->nextPageUrl() }}" class="btn-pagination">Next</a>
        @else
            <button class="btn-pagination" disabled>Next</button>
        @endif
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('storesFiltersForm');
            if (!form) return;

            const autoSubmitFields = ['status', 'sort_by', 'sort_dir', 'per_page'];
            autoSubmitFields.forEach(function (fieldId) {
                const field = document.getElementById(fieldId);
                if (!field) return;
                field.addEventListener('change', function () {
                    form.requestSubmit();
                });
            });

            const search = document.getElementById('search');
            if (!search) return;

            let debounceTimer = null;
            search.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    form.requestSubmit();
                }, 350);
            });
        });
    </script>
    @endpush
@endsection
