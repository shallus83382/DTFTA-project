@extends('layouts.app')

@section('title', 'DTFTA CRM - Products')
@section('page-title', 'Products')

@push('styles')
<style>
    .products-pro-wrap {
        display: grid;
        gap: 14px;
    }

    .products-panel {
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: linear-gradient(160deg, rgba(30, 41, 59, 0.94), rgba(15, 23, 42, 0.94));
        box-shadow: 0 14px 30px rgba(2, 6, 23, 0.26);
        position: relative;
        overflow: hidden;
    }

    .products-panel::before {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        height: 1px;
        background: linear-gradient(90deg, rgba(56, 189, 248, 0.28), rgba(45, 212, 191, 0.15), rgba(56, 189, 248, 0.28));
        pointer-events: none;
    }

    .products-add-card {
        margin-bottom: 0 !important;
        padding: 16px;
    }

    .products-add-card h3 {
        margin-bottom: 14px !important;
        color: #f8fafc;
        font-size: 22px;
        line-height: 1.15;
        letter-spacing: -0.01em;
    }

    .products-add-form .filters-section,
    .products-filters .filters-section {
        margin-bottom: 0 !important;
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.38));
        box-shadow: none;
        gap: 10px;
    }

    .products-add-form .filter-group,
    .products-filters .filter-group {
        margin-bottom: 0;
    }

    .products-add-form .filter-group label,
    .products-filters .filter-group label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a9bddb;
        font-weight: 700;
    }

    .products-add-form .filter-select,
    .products-filters .filter-select,
    .products-table-wrap .filter-select {
        height: 38px;
        border-radius: 10px;
        border-color: rgba(148, 163, 184, 0.28);
        background-color: rgba(15, 23, 42, 0.78);
        color: #f8fafc;
        font-size: 13px;
    }

    .products-add-form textarea.filter-select {
        height: auto;
        min-height: 40px;
    }

    .products-filters {
        margin-top: 0;
    }

    .products-filters .filters-section {
        display: grid;
        grid-template-columns: minmax(220px, 1.35fr) repeat(5, minmax(120px, 0.7fr)) auto auto;
        align-items: stretch;
        gap: 12px;
        padding: 14px;
        border-radius: 14px;
        border: 1px solid rgba(96, 165, 250, 0.24);
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 48%),
            linear-gradient(135deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.94));
        box-shadow: 0 14px 28px rgba(2, 6, 23, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .products-filters .btn-secondary {
        height: 42px;
        min-height: 42px;
        width: 112px;
        min-width: 112px;
        padding: 0;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        white-space: nowrap;
        font-weight: 700;
    }

    .products-filters .products-filter-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        align-items: stretch;
        min-width: 234px;
    }

    .products-filters .products-filter-actions .btn-secondary {
        width: 100%;
        min-width: 0;
        height: 44px;
        min-height: 44px;
        margin: 0;
        box-sizing: border-box;
        border-width: 1px;
        border-style: solid;
        appearance: none;
        -webkit-appearance: none;
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        font-weight: 700;
        line-height: 1;
        vertical-align: middle;
    }

    .products-filters .filter-group {
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }

    .products-filters .filter-group label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #b2c4df;
        font-weight: 700;
        margin-bottom: 7px;
    }

    .products-filters .filter-select {
        height: 42px;
        min-height: 42px;
        width: 100%;
        box-sizing: border-box;
        border-radius: 11px;
        border-color: rgba(148, 163, 184, 0.3);
        background-color: rgba(15, 23, 42, 0.82);
        color: #f8fafc;
        font-size: 13px;
        font-weight: 600;
        padding-inline: 13px;
        line-height: 1.2;
    }

    .products-filters .filter-select::placeholder {
        color: #7f93b2;
        font-weight: 500;
    }

    .products-filters .filter-select:hover {
        border-color: rgba(96, 165, 250, 0.45);
    }

    .products-filters .filter-select:focus {
        border-color: rgba(96, 165, 250, 0.72);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
    }

    .products-filters button[type="submit"].btn-secondary {
        padding-inline: 0;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border-color: rgba(125, 211, 252, 0.45);
        color: #eff6ff;
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.24);
    }

    .products-filters a.btn-secondary {
        padding-inline: 0;
        background: linear-gradient(135deg, rgba(71, 85, 105, 0.88), rgba(51, 65, 85, 0.82));
        border-color: rgba(148, 163, 184, 0.34);
        color: #e2e8f0;
        text-decoration: none;
        box-shadow: 0 8px 16px rgba(15, 23, 42, 0.26);
    }

    .products-filters a.btn-secondary:hover {
        border-color: rgba(148, 163, 184, 0.52);
        background: linear-gradient(135deg, rgba(100, 116, 139, 0.9), rgba(71, 85, 105, 0.86));
    }

    .products-table-wrap {
        margin-top: 0 !important;
        border-radius: 14px;
        border-color: rgba(148, 163, 184, 0.2);
        background: linear-gradient(165deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.92));
        box-shadow: 0 14px 30px rgba(2, 6, 23, 0.26);
    }

    .products-table-wrap .jobs-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #a7bce0;
        padding: 13px 14px;
        border-bottom-color: rgba(148, 163, 184, 0.2);
        background: rgba(15, 23, 42, 0.58);
    }

    .products-table-wrap .jobs-table td {
        padding: 12px 14px;
        border-bottom-color: rgba(148, 163, 184, 0.16);
        vertical-align: middle;
    }

    .products-table-wrap .jobs-table tbody tr:hover {
        background: linear-gradient(120deg, rgba(59, 130, 246, 0.1), rgba(15, 23, 42, 0.14));
    }

    .product-title-cell {
        min-width: 240px;
    }

    .product-actions-cell {
        min-width: 220px;
        white-space: nowrap;
    }

    .product-actions-cell .btn-secondary,
    .product-actions-cell .btn-danger {
        height: 34px;
        min-height: 34px;
        border-radius: 9px;
        padding: 0 11px;
        font-size: 12px;
        font-weight: 700;
        margin-right: 6px;
    }

    .product-actions-cell form {
        display: inline-block;
        margin: 0;
    }

    .products-table-wrap .status-badge {
        border-radius: 999px;
        padding: 3px 10px;
        font-size: 10px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        font-weight: 700;
    }

    @media (max-width: 1200px) {
        .products-filters .filters-section {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 992px) {
        .products-filters .filters-section {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .products-filters .filters-section {
            grid-template-columns: 1fr;
        }
        .products-filters .btn-secondary {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
    <div class="products-pro-wrap">
    @if(session('success'))
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #166534;">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    @if($errors->any())
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #991b1b;">
            <strong>Validation failed:</strong>
            <ul style="margin: 8px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="chart-card products-panel products-add-card">
        <h3 style="margin-bottom: 12px;">Add Product</h3>
        <form method="POST" action="{{ route('crm.products.store') }}" class="products-add-form">
            @csrf
            <div class="filters-section" style="margin-bottom: 0;">
                <div class="filter-group">
                    <label for="shop_id">Store</label>
                    <select id="shop_id" name="shop_id" class="filter-select" required>
                        <option value="">Select store</option>
                        @foreach($shopsForProducts as $shop)
                            <option value="{{ $shop->id }}">{{ $shop->shop_domain }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label for="title">Title</label>
                    <input id="title" name="title" type="text" class="filter-select" required>
                </div>
                <div class="filter-group">
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" type="text" class="filter-select">
                </div>
                <div class="filter-group">
                    <label for="shopify_product_id">Shopify Product ID</label>
                    <input id="shopify_product_id" name="shopify_product_id" type="text" class="filter-select">
                </div>
                <div class="filter-group">
                    <label for="price">Price</label>
                    <input id="price" name="price" type="number" min="0" step="0.01" class="filter-select">
                </div>
                <div class="filter-group">
                    <label for="currency">Currency</label>
                    <input id="currency" name="currency" type="text" value="USD" class="filter-select">
                </div>
                <div class="filter-group">
                    <label for="stock_quantity">Stock Qty</label>
                    <input id="stock_quantity" name="stock_quantity" type="number" min="0" value="0" class="filter-select">
                </div>
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="filter-select" required>
                        @foreach($productStatusOptions as $statusOption)
                            <option value="{{ $statusOption }}">{{ ucfirst($statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group" style="min-width: 260px;">
                    <label for="description">Description</label>
                    <input id="description" name="description" type="text" class="filter-select">
                </div>
                <button type="submit" class="btn-primary">Create Product</button>
            </div>
        </form>
    </div>

    <form id="productsFiltersForm" method="GET" action="{{ route('crm.products') }}" class="filters-section products-filters">
        <div class="filter-group">
            <label for="search">Search</label>
            <input id="search" name="search" type="text" placeholder="Title, SKU, Shopify product ID" class="filter-select"
                value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="filter-group">
            <label for="shop_filter">Store</label>
            <select id="shop_filter" name="shop_id" class="filter-select">
                <option value="0">All Stores</option>
                @foreach($shopsForProducts as $shop)
                    <option value="{{ $shop->id }}" {{ (int) ($filters['shop_id'] ?? 0) === (int) $shop->id ? 'selected' : '' }}>
                        {{ $shop->shop_domain }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label for="status_filter">Status</label>
            <select id="status_filter" name="status" class="filter-select">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                @foreach($productStatusOptions as $statusOption)
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
                <option value="title" {{ ($filters['sort_by'] ?? '') === 'title' ? 'selected' : '' }}>Title</option>
                <option value="price" {{ ($filters['sort_by'] ?? '') === 'price' ? 'selected' : '' }}>Price</option>
                <option value="stock_quantity" {{ ($filters['sort_by'] ?? '') === 'stock_quantity' ? 'selected' : '' }}>Stock Qty</option>
                <option value="status" {{ ($filters['sort_by'] ?? '') === 'status' ? 'selected' : '' }}>Status</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="sort_dir">Direction</label>
            <select id="sort_dir" name="sort_dir" class="filter-select">
                <option value="desc" {{ ($filters['sort_dir'] ?? 'desc') === 'desc' ? 'selected' : '' }}>DESC</option>
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
        <div class="products-filter-actions">
            <button class="btn-secondary" type="submit">Apply</button>
            <a href="{{ route('crm.products') }}" class="btn-secondary">Clear</a>
        </div>
    </form>

    <div class="table-container products-table-wrap" style="margin-top: 16px;">
        <table class="jobs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Store</th>
                    <th>Title</th>
                    <th>SKU</th>
                    <th>Shopify ID</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $status = strtolower((string) ($product->status ?? 'active'));
                        $badgeClass = match($status) {
                            'active' => 'status-shipped',
                            'draft' => 'status-artwork',
                            'archived' => 'status-cancelled',
                            default => 'status-production',
                        };
                    @endphp
                    <tr>
                        <td>#{{ $product->id }}</td>
                        <td>{{ $product->shop->shop_domain ?? '-' }}</td>
                        <td class="product-title-cell">
                            <form method="POST" action="{{ route('crm.products.update', $product->id) }}" class="product-inline-form">
                                @csrf
                                <input type="hidden" name="shop_id" value="{{ $product->shop_id }}">
                                <input type="text" name="title" value="{{ $product->title }}" class="filter-select" style="min-width: 180px;" required>
                        </td>
                        <td><input type="text" name="sku" value="{{ $product->sku }}" class="filter-select" style="min-width: 120px;"></td>
                        <td><input type="text" name="shopify_product_id" value="{{ $product->shopify_product_id }}" class="filter-select" style="min-width: 140px;"></td>
                        <td style="min-width: 120px;">
                            <input type="number" name="price" value="{{ $product->price }}" min="0" step="0.01" class="filter-select">
                            <input type="hidden" name="currency" value="{{ $product->currency ?? 'USD' }}">
                        </td>
                        <td style="min-width: 90px;">
                            <input type="number" name="stock_quantity" value="{{ (int) $product->stock_quantity }}" min="0" class="filter-select">
                        </td>
                        <td style="min-width: 140px;">
                            <select name="status" class="filter-select" required>
                                @foreach($productStatusOptions as $statusOption)
                                    <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>
                                        {{ ucfirst($statusOption) }}
                                    </option>
                                @endforeach
                            </select>
                            <div style="margin-top: 6px;">
                                <span class="status-badge {{ $badgeClass }}">{{ ucfirst($status) }}</span>
                            </div>
                        </td>
                        <td class="product-actions-cell">
                            <input type="hidden" name="description" value="{{ $product->description }}">
                            <button type="submit" class="btn-secondary" style="margin-right: 8px;">Save</button>
                            </form>
                            <form method="POST" action="{{ route('crm.products.destroy', $product->id) }}" style="display: inline-block;"
                                  onsubmit="return confirm('Delete this product?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px; color: #999;">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        @if($products->onFirstPage())
            <button class="btn-pagination" disabled>Previous</button>
        @else
            <a href="{{ $products->previousPageUrl() }}" class="btn-pagination">Previous</a>
        @endif
        <span class="page-info">Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
        @if($products->hasMorePages())
            <a href="{{ $products->nextPageUrl() }}" class="btn-pagination">Next</a>
        @else
            <button class="btn-pagination" disabled>Next</button>
        @endif
    </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('productsFiltersForm');
            if (!form) return;

            ['shop_filter', 'status_filter', 'sort_by', 'sort_dir', 'per_page'].forEach(function (fieldId) {
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
