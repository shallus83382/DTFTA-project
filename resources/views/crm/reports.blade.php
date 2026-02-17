@extends('layouts.app')

@section('title', 'DTFTA CRM - Reports & Analytics')
@section('page-title', 'Reports & Analytics')

@section('content')
    <div class="reports-filters">
        <div class="filter-group">
            <label>From</label>
            <input type="date" class="filter-select" id="dateFrom">
        </div>
        <div class="filter-group">
            <label>To</label>
            <input type="date" class="filter-select" id="dateTo">
        </div>
        <button class="btn-secondary" id="applyDateFilter">Apply</button>
        <div style="flex:1"></div>
        <button class="btn-primary" id="exportCsv">Export CSV</button>
    </div>

    <!-- Basic Reports (Phase 1) -->
    <div class="reports-section">
        <h2 class="reports-section-title"><span class="section-icon section-icon-chart" aria-hidden="true"></span>Basic
            Reports (Phase 1)</h2>
        <div class="cards-grid">
            <div class="card card-blue">
                <div class="card-icon card-icon-store" aria-hidden="true"></div>
                <div class="card-content">
                    <h3>Orders per store</h3>
                    <p class="card-value">—</p>
                    <p class="card-subtitle">Breakdown by connected store</p>
                </div>
            </div>
            <div class="card card-green">
                <div class="card-icon card-icon-check" aria-hidden="true"></div>
                <div class="card-content">
                    <h3>Total fulfilled orders</h3>
                    <p class="card-value">987</p>
                    <p class="card-subtitle">Shipped in selected range</p>
                </div>
            </div>
            <div class="card card-orange">
                <div class="card-icon card-icon-clock" aria-hidden="true"></div>
                <div class="card-content">
                    <h3>Pending vs shipped</h3>
                    <p class="card-value">89 / 987</p>
                    <p class="card-subtitle">Pending | Shipped</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders per store table -->
    <div class="chart-card" style="margin-top: 24px;">
        <h3>Orders per store (date range)</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Store (domain)</th>
                        <th>Total Orders</th>
                        <th>Fulfilled</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>store-abc.myshopify.com</td>
                        <td>456</td>
                        <td>440</td>
                        <td>16</td>
                    </tr>
                    <tr>
                        <td>store-123.myshopify.com</td>
                        <td>312</td>
                        <td>298</td>
                        <td>14</td>
                    </tr>
                    <tr>
                        <td>store-xyz.myshopify.com</td>
                        <td>219</td>
                        <td>249</td>
                        <td>0</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="public/assets/js/script.js"></script>
    <script>
        document.getElementById('exportCsv').addEventListener('click', function() {
            alert('CSV download would start here. Connect to backend for real export.');
        });
        document.getElementById('applyDateFilter').addEventListener('click', function() {
            var from = document.getElementById('dateFrom').value;
            var to = document.getElementById('dateTo').value;
            if (from || to) alert('Filter applied for: ' + (from || 'start') + ' to ' + (to || 'today'));
        });
    </script>

@endsection
