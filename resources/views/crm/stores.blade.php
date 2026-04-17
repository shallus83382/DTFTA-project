@extends('layouts.app')

@section('title', 'DTFTA CRM - Stores & Partners')
@section('page-title', 'Stores & Partners')

@push('styles')
<style>
    .stores-pro-wrap {
        display: grid;
        gap: 14px;
    }

    .stores-pro-intro {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: center;
        padding: 14px 16px;
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.9));
        box-shadow: 0 12px 24px rgba(2, 6, 23, 0.24);
    }

    .stores-pro-intro .stores-intro {
        margin: 0;
        color: #c7d5ec;
        font-size: 14px;
    }

    .stores-pro-pill {
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid rgba(96, 165, 250, 0.36);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.14));
        color: #dbeafe;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    #storesFiltersForm {
        padding: 14px;
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(130deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.92));
        box-shadow: 0 10px 22px rgba(2, 6, 23, 0.2);
        display: grid;
        grid-template-columns: minmax(220px, 1.4fr) repeat(4, minmax(120px, 0.7fr)) auto auto;
        align-items: end;
        gap: 10px;
    }

    #storesFiltersForm .filter-group {
        min-width: 0;
        margin-bottom: 0;
    }

    #storesFiltersForm .filter-group label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a9bddb;
        font-weight: 700;
    }

    #storesFiltersForm .filter-select {
        height: 40px;
        border-radius: 10px;
        border-color: rgba(148, 163, 184, 0.28);
        background-color: rgba(15, 23, 42, 0.72);
        color: #f8fafc;
        font-size: 13px;
    }

    #storesFiltersForm input.filter-select::placeholder {
        color: #7f93b2;
    }

    #storesFiltersForm .filter-select:hover {
        border-color: rgba(96, 165, 250, 0.45);
    }

    #storesFiltersForm .filter-select:focus {
        border-color: rgba(96, 165, 250, 0.72);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
    }

    #storesFiltersForm .btn-secondary {
        height: 40px;
        min-height: 40px;
        border-radius: 10px;
        padding: 0 16px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        vertical-align: middle;
    }

    #storesFiltersForm button[type="submit"].btn-secondary {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.24), rgba(37, 99, 235, 0.18));
        border-color: rgba(96, 165, 250, 0.4);
        color: #e0ecff;
    }

    #storesFiltersForm a.btn-secondary {
        background: linear-gradient(135deg, rgba(71, 85, 105, 0.8), rgba(51, 65, 85, 0.75));
        border-color: rgba(148, 163, 184, 0.28);
        color: #dbe4f4;
        text-decoration: none;
    }

    @media (max-width: 1200px) {
        #storesFiltersForm {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 992px) {
        #storesFiltersForm {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        #storesFiltersForm {
            grid-template-columns: 1fr;
        }

        #storesFiltersForm .btn-secondary {
            width: 100%;
        }
    }

    .store-list-table {
        border-radius: 14px;
        border-color: rgba(148, 163, 184, 0.2);
        background: linear-gradient(165deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.92));
        box-shadow: 0 14px 30px rgba(2, 6, 23, 0.26);
    }

    .store-list-table .jobs-table {
        border-collapse: separate;
        border-spacing: 0;
    }

    .store-list-table .jobs-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a7bce0;
        padding: 14px 14px;
        border-bottom-color: rgba(148, 163, 184, 0.2);
        background: rgba(15, 23, 42, 0.58);
        position: sticky;
        top: 0;
        z-index: 2;
        backdrop-filter: blur(4px);
    }

    .store-list-table .jobs-table td {
        padding: 14px 14px;
        border-bottom-color: rgba(148, 163, 184, 0.16);
        color: #e2e8f0;
        font-size: 13px;
        vertical-align: middle;
    }

    .store-list-table .jobs-table tbody tr {
        transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }

    .store-list-table .jobs-table tbody tr:hover {
        background: linear-gradient(120deg, rgba(59, 130, 246, 0.12), rgba(15, 23, 42, 0.18));
        box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.25);
    }

    .store-list-table .jobs-table tbody tr:nth-child(even) {
        background: rgba(15, 23, 42, 0.18);
    }

    .store-domain-cell {
        display: grid;
        gap: 3px;
        max-width: 360px;
    }

    .store-domain-main {
        color: #f8fafc;
        font-weight: 700;
        font-size: 14px;
        line-height: 1.25;
        word-break: break-word;
    }

    .store-domain-sub {
        color: #91a6c7;
        font-size: 11px;
        letter-spacing: 0.02em;
    }

    .store-metric {
        font-weight: 700;
        color: #dbeafe;
        font-variant-numeric: tabular-nums;
        font-size: 14px;
    }

    .store-list-table .status-badge {
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border: 1px solid transparent;
    }

    .store-list-table .status-badge.status-shipped {
        background: rgba(16, 185, 129, 0.16);
        color: #6ee7b7;
        border-color: rgba(16, 185, 129, 0.45);
    }

    .store-list-table .status-badge.status-production {
        background: rgba(139, 92, 246, 0.16);
        color: #c4b5fd;
        border-color: rgba(139, 92, 246, 0.45);
    }

    .store-list-table .status-badge.status-cancelled {
        background: rgba(239, 68, 68, 0.16);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, 0.45);
    }

    .store-list-table .status-badge.status-new {
        background: rgba(59, 130, 246, 0.16);
        color: #93c5fd;
        border-color: rgba(59, 130, 246, 0.45);
    }

    .store-action-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border-radius: 9px;
        border: 1px solid rgba(96, 165, 250, 0.4);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.18), rgba(37, 99, 235, 0.1));
        color: #dbeafe;
        font-weight: 600;
        font-size: 12px;
        padding: 6px 11px;
        text-decoration: none;
        transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
    }

    .store-action-link:hover {
        border-color: rgba(125, 211, 252, 0.55);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.28), rgba(37, 99, 235, 0.16));
        color: #f8fbff;
        text-decoration: none;
    }

    @media (max-width: 900px) {
        .stores-pro-intro {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
    <div class="stores-pro-wrap">
    @if(session('success'))
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #166534;">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <div class="stores-pro-intro">
        <p class="stores-intro">All connected Shopify stores - view and manage partners from your CRM.</p>
        <span class="stores-pro-pill">{{ $stores->total() }} connected stores</span>
    </div>

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
                        <td>
                            <div class="store-domain-cell">
                                <span class="store-domain-main">{{ $store->shop_domain }}</span>
                                <span class="store-domain-sub">Store #{{ $store->id }}</span>
                            </div>
                        </td>
                        <td>{{ $store->partnerProfile->brand_name ?? '-' }}</td>
                        <td><span class="status-badge {{ $badgeClass }}">{{ ucfirst($status) }}</span></td>
                        <td><span class="store-metric">{{ number_format((int) ($store->total_orders_count ?? 0)) }}</span></td>
                        <td><span class="store-metric">{{ number_format((int) ($store->pending_jobs_count ?? 0)) }}</span></td>
                        <td>{{ optional($store->installed_at)->format('Y-m-d') ?? optional($store->created_at)->format('Y-m-d') }}</td>
                        <td><a href="{{ route('crm.store-detail', $store->id) }}" class="store-action-link">Store detail</a></td>
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
