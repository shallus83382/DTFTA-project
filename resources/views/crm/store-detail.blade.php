@extends('layouts.app')
@section('title', 'DTFTA CRM - Store Details')
@section('page-title', 'Store Details')
@section('content')
    <!-- Store info -->
    <section class="chart-card store-detail-card">
        <h3><span class="section-icon section-icon-store" aria-hidden="true"></span>Store info</h3>
        <div class="store-detail-grid">
            <div class="detail-row"><span class="detail-label">Store name (domain)</span><span>store-abc.myshopify.com</span>
            </div>
            <div class="detail-row"><span class="detail-label">Status</span><span
                    class="status-badge status-shipped">Active</span></div>
            <div class="detail-row"><span class="detail-label">Total orders</span><span>1,234</span></div>
            <div class="detail-row"><span class="detail-label">Pending jobs</span><span>12</span></div>
            <div class="detail-row"><span class="detail-label">Connected since</span><span>2024-01-10</span></div>
        </div>
    </section>

    <!-- Partner profile (brand name etc.) -->
    <section class="chart-card store-detail-card">
        <h3><span class="section-icon section-icon-user" aria-hidden="true"></span>Partner profile</h3>
        <div class="store-detail-grid">
            <div class="detail-row"><span class="detail-label">Brand name</span><span>Store ABC</span></div>
            <div class="detail-row"><span class="detail-label">Contact name</span><span>Example</span></div>
            <div class="detail-row"><span class="detail-label">Email</span><span>example@store.com</span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span>+1 (555) 123-4567</span></div>
            <div class="detail-row"><span class="detail-label">Location</span><span>New York, USA</span></div>
        </div>
    </section>

    <!-- Order history -->
    <section class="chart-card store-detail-card">
        <h3><span class="section-icon section-icon-list" aria-hidden="true"></span>Order history</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>#1234</td>
                        <td>2024-03-15</td>
                        <td><span class="status-badge status-shipped">Shipped</span></td>
                        <td>3</td>
                        <td><a href="job-detail.html" class="btn-link">View</a></td>
                    </tr>
                    <tr>
                        <td>#1230</td>
                        <td>2024-03-14</td>
                        <td><span class="status-badge status-production">In Production</span></td>
                        <td>5</td>
                        <td><a href="job-detail.html" class="btn-link">View</a></td>
                    </tr>
                    <tr>
                        <td>#1225</td>
                        <td>2024-03-12</td>
                        <td><span class="status-badge status-shipped">Shipped</span></td>
                        <td>2</td>
                        <td><a href="job-detail.html" class="btn-link">View</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination" style="margin-top: 16px;">
            <button class="btn-pagination">Previous</button>
            <span class="page-info">Page 1 of 5</span>
            <button class="btn-pagination">Next</button>
        </div>
    </section>
    <script src="public/assets/js/script.js"></script>
@endsection
