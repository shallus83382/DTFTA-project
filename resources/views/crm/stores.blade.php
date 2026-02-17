@extends('layouts.app')

@section('title', 'DTFTA CRM - Stores & Partners')
@section('page-title', 'Stores & Partners')

@section('content')

    <p class="stores-intro">All connected Shopify stores — view and manage partners from your CRM.</p>

    <div class="filters-section">
        <div class="filter-group">
            <label>Search</label>
            <input type="text" placeholder="Search stores..." class="filter-select">
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select class="filter-select">
                <option>All</option>
                <option>Active</option>
                <option>Uninstalled</option>
            </select>
        </div>
        <div style="flex:1"></div>
        <button class="btn-secondary">Clear Filters</button>
    </div>

    <div class="table-container store-list-table">
        <table class="jobs-table">
            <thead>
                <tr>
                    <th class="checkbox-cell"><input type="checkbox" id="selectAllStores" aria-label="Select all stores">
                    </th>
                    <th>Store Name (domain)</th>
                    <th>Status</th>
                    <th>Total Orders</th>
                    <th>Pending Jobs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="checkbox-cell"><input type="checkbox" class="row-checkbox" aria-label="Select store 1"></td>
                    <td>store-abc.myshopify.com</td>
                    <td><span class="status-badge status-shipped">Active</span></td>
                    <td>1,234</td>
                    <td>12</td>
                    <td><a href="store-detail.html" class="btn-link">Store detail</a></td>
                </tr>
                <tr>
                    <td class="checkbox-cell"><input type="checkbox" class="row-checkbox" aria-label="Select store 2"></td>
                    <td>store-xyz.myshopify.com</td>
                    <td><span class="status-badge status-cancelled">Uninstalled</span></td>
                    <td>456</td>
                    <td>0</td>
                    <td><a href="store-detail.html" class="btn-link">Store detail</a></td>
                </tr>
                <tr>
                    <td class="checkbox-cell"><input type="checkbox" class="row-checkbox" aria-label="Select store 3"></td>
                    <td>store-123.myshopify.com</td>
                    <td><span class="status-badge status-shipped">Active</span></td>
                    <td>789</td>
                    <td>5</td>
                    <td><a href="store-detail.html" class="btn-link">Store detail</a></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <button class="btn-pagination">Previous</button>
        <span class="page-info">Page 1 of 1</span>
        <button class="btn-pagination">Next</button>
    </div>
    <script src="public/assets/js/script.js"></script>

@endsection
