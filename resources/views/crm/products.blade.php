@extends('layouts.app')

@section('title', 'DTFTA CRM - Products')
@section('page-title', 'Products')

@section('content')
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

    <div class="chart-card" style="margin-bottom: 18px;">
        <h3 style="margin-bottom: 12px;">Add Product</h3>
        <form method="POST" action="{{ route('crm.products.store') }}">
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

    <form id="productsFiltersForm" method="GET" action="{{ route('crm.products') }}" class="filters-section">
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
        <button class="btn-secondary" type="submit">Apply</button>
        <a href="{{ route('crm.products') }}" class="btn-secondary">Clear</a>
    </form>

    <div class="table-container" style="margin-top: 16px;">
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
                        <td style="min-width: 220px;">
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
                        <td style="min-width: 180px;">
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
