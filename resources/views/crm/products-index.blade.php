@extends('layouts.app')

@section('title', 'DTFTA CRM - Products')
@section('page-title', 'Products')

@section('content')
    @if(session('success'))
        <div class="chart-card" style="margin-bottom: 16px; border: 1px solid #166534;">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <div style="margin-bottom: 16px; display: flex; justify-content: flex-end;">
        <a href="{{ route('crm.products.create') }}" class="btn-primary">Add Product</a>
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
                <option value="regular_price" {{ ($filters['sort_by'] ?? '') === 'regular_price' ? 'selected' : '' }}>Price</option>
                <option value="stock_quantity" {{ ($filters['sort_by'] ?? '') === 'stock_quantity' ? 'selected' : '' }}>Stock Qty</option>
                <option value="brand" {{ ($filters['sort_by'] ?? '') === 'brand' ? 'selected' : '' }}>Brand</option>
                <option value="category" {{ ($filters['sort_by'] ?? '') === 'category' ? 'selected' : '' }}>Category</option>
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
                    <th>Image</th>
                    <th>Title</th>
                   
                    <th>Price</th>
                    <th>Sale Price</th>
                    <th>Stock</th>
                    <th>Stock Status</th>
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
                        <td>
                            @if(!empty($product->featured_image))
                                <img src="{{ asset('storage/' . $product->featured_image) }}" alt="Product image" style="width: 52px; height: 52px; object-fit: cover; border-radius: 6px;">
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $product->title }}</td>
                       
                        <td>{{ $product->regular_price !== null ? number_format((float) $product->regular_price, 2) . ' ' . ($product->currency ?? 'USD') : '-' }}</td>
                        <td>{{ $product->sale_price !== null ? number_format((float) $product->sale_price, 2) . ' ' . ($product->currency ?? 'USD') : '-' }}</td>
                        <td>{{ (int) $product->stock_quantity }}</td>
                        <td>{{ ($product->stock_status ?? 'in_stock') === 'in_stock' ? 'In Stock' : 'Out of Stock' }}</td>
                        <td><span class="status-badge {{ $badgeClass }}">{{ ucfirst($status) }}</span></td>
                        <td>
                            <a href="{{ route('crm.products.view', $product->id) }}" class="btn-secondary" style="margin-right: 8px;">View</a>
                            <a href="{{ route('crm.products.edit', $product->id) }}" class="btn-secondary" style="margin-right: 8px;">Edit</a>
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
                        <td colspan="13" style="text-align: center; padding: 20px; color: #999;">No products found.</td>
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
