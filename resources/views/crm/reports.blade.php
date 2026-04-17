@extends('layouts.app')

@section('title', 'DTFTA CRM - Reports & Analytics')
@section('page-title', 'Reports & Analytics')

@section('content')
    @php
        $reportType = $filters['report_type'] ?? 'sales';
        $showSales = in_array($reportType, ['sales', 'performance'], true);
        $showFulfillment = in_array($reportType, ['fulfillment', 'performance'], true);
        $showInventory = in_array($reportType, ['inventory', 'performance'], true);
        $showPerformance = $reportType === 'performance';
    @endphp

    <div class="reports-page">
    <div class="reports-hero">
        <div>
            <p class="reports-eyebrow">Performance Intelligence</p>
            <h2>Reports & Analytics Workspace</h2>
            <p class="reports-subtitle">
                Track sales, fulfillment, and product movement with one unified reporting view.
            </p>
        </div>
        <div class="reports-hero-pill">
            {{ ucfirst($reportType) }} report
            <span>{{ $filters['from_input'] }} to {{ $filters['to_input'] }}</span>
        </div>
    </div>

    <form id="reportsFilterForm" method="GET" action="{{ route('crm.reports') }}" class="reports-filters">
        <div class="filter-group">
            <label for="report_type">Report Type</label>
            <select id="report_type" name="report_type" class="filter-select">
                @foreach($filters['allowed_report_types'] as $type)
                    <option value="{{ $type }}" {{ $filters['report_type'] === $type ? 'selected' : '' }}>
                        {{ ucfirst($type) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label for="from">From</label>
            <input id="from" name="from" type="date" class="filter-select" value="{{ $filters['from_input'] }}">
        </div>
        <div class="filter-group">
            <label for="to">To</label>
            <input id="to" name="to" type="date" class="filter-select" value="{{ $filters['to_input'] }}">
        </div>
        <div class="filter-group">
            <label for="shop_id">Store</label>
            <select id="shop_id" name="shop_id" class="filter-select">
                <option value="">All Stores</option>
                @foreach($availableShops as $shop)
                    <option value="{{ $shop->id }}" {{ (int) ($filters['shop_id'] ?? 0) === (int) $shop->id ? 'selected' : '' }}>
                        {{ $shop->shop_domain }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label for="fulfillment_status">Fulfillment</label>
            <select id="fulfillment_status" name="fulfillment_status" class="filter-select">
                <option value="">All</option>
                @foreach($availableFulfillmentStatuses as $status)
                    <option value="{{ $status }}" {{ $filters['fulfillment_status'] === $status ? 'selected' : '' }}>
                        {{ strtoupper(str_replace('_', ' ', $status)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label for="job_type">Job Type</label>
            <select id="job_type" name="job_type" class="filter-select">
                <option value="">All</option>
                @foreach($availableJobTypes as $type)
                    <option value="{{ $type }}" {{ $filters['job_type'] === $type ? 'selected' : '' }}>
                        {{ $type }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="reports-filter-actions">
            <button class="btn-secondary" type="submit">Apply</button>
            <a href="{{ route('crm.reports') }}" class="btn-secondary">Clear</a>
            <a href="{{ route('crm.reports.export-csv', request()->query()) }}" class="btn-primary">Export CSV</a>
        </div>
    </form>

    <div class="cards-grid reports-kpi-grid">
        <div class="card report-kpi-card card-blue">
            <div class="card-content">
                <h3>Total Orders</h3>
                <p class="card-value">{{ number_format((int) $metrics['total_orders']) }}</p>
                <p class="card-subtitle">Filtered date range</p>
            </div>
        </div>
        <div class="card report-kpi-card card-green">
            <div class="card-content">
                <h3>Total Revenue</h3>
                <p class="card-value">${{ number_format((float) $metrics['total_revenue'], 2) }}</p>
                <p class="card-subtitle">Order totals</p>
            </div>
        </div>
        <div class="card report-kpi-card card-orange">
            <div class="card-content">
                <h3>Fulfillment Rate</h3>
                <p class="card-value">{{ number_format((float) $metrics['fulfillment_rate'], 2) }}%</p>
                <p class="card-subtitle">Fulfilled / Total Orders</p>
            </div>
        </div>
        <div class="card report-kpi-card card-purple">
            <div class="card-content">
                <h3>Avg Order Value</h3>
                <p class="card-value">${{ number_format((float) $metrics['avg_order_value'], 2) }}</p>
                <p class="card-subtitle">Revenue / Orders</p>
            </div>
        </div>
    </div>

    <div class="cards-grid reports-kpi-grid reports-kpi-grid-secondary">
        <div class="card report-kpi-card card-green">
            <div class="card-content">
                <h3>Fulfilled Orders</h3>
                <p class="card-value">{{ number_format((int) $metrics['fulfilled_orders']) }}</p>
                <p class="card-subtitle">Shipped/Fulfilled/Delivered</p>
            </div>
        </div>
        <div class="card report-kpi-card card-orange">
            <div class="card-content">
                <h3>Pending Orders</h3>
                <p class="card-value">{{ number_format((int) $metrics['pending_orders']) }}</p>
                <p class="card-subtitle">In-progress statuses</p>
            </div>
        </div>
        <div class="card report-kpi-card card-red">
            <div class="card-content">
                <h3>Exception Orders</h3>
                <p class="card-value">{{ number_format((int) $metrics['exception_orders']) }}</p>
                <p class="card-subtitle">Failed/Exception/Cancelled</p>
            </div>
        </div>
        <div class="card report-kpi-card card-blue">
            <div class="card-content">
                <h3>Jobs (Done / In Progress / Failed)</h3>
                <p class="card-value">
                    {{ number_format((int) $metrics['jobs_completed']) }}
                    /
                    {{ number_format((int) $metrics['jobs_in_progress']) }}
                    /
                    {{ number_format((int) $metrics['jobs_failed']) }}
                </p>
                <p class="card-subtitle">Total jobs: {{ number_format((int) $metrics['total_jobs']) }}</p>
            </div>
        </div>
    </div>

    @if($showSales)
    <div class="chart-card report-panel">
        <h3>Order Trend</h3>
        <canvas id="ordersTrendChart" height="100"></canvas>
    </div>

    <div class="chart-card report-panel">
        <h3>Revenue Trend</h3>
        <canvas id="revenueTrendChart" height="100"></canvas>
    </div>
    @endif

    @if($showSales || $showPerformance)
    <div class="chart-card report-panel">
        <h3>Orders by Store</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Total Orders</th>
                        <th>Fulfilled</th>
                        <th>Pending</th>
                        <th>Exceptions</th>
                        <th>Revenue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ordersByStore as $row)
                        <tr>
                            <td>{{ $row->shop_domain }}</td>
                            <td>{{ number_format((int) $row->total_orders) }}</td>
                            <td>{{ number_format((int) $row->fulfilled_orders) }}</td>
                            <td>{{ number_format((int) $row->pending_orders) }}</td>
                            <td>{{ number_format((int) $row->exception_orders) }}</td>
                            <td>${{ number_format((float) $row->revenue, 2) }}</td>
                            <td>
                                <a href="{{ route('crm.store-detail', $row->shop_id) }}" class="btn-link">Store detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #999;">No data found for selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination" style="margin-top: 16px;">
            @if($ordersByStore->onFirstPage())
                <button class="btn-pagination" disabled>Previous</button>
            @else
                <a href="{{ $ordersByStore->previousPageUrl() }}" class="btn-pagination">Previous</a>
            @endif
            <span class="page-info">Page {{ $ordersByStore->currentPage() }} of {{ $ordersByStore->lastPage() }}</span>
            @if($ordersByStore->hasMorePages())
                <a href="{{ $ordersByStore->nextPageUrl() }}" class="btn-pagination">Next</a>
            @else
                <button class="btn-pagination" disabled>Next</button>
            @endif
        </div>
    </div>
    @endif

    @if($showFulfillment)
    <div class="chart-card report-panel">
        <h3>Status Distribution</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Total Orders</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($statusDistribution as $item)
                        <tr>
                            <td>{{ strtoupper(str_replace('_', ' ', $item->label)) }}</td>
                            <td>{{ number_format((int) $item->total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" style="text-align: center; padding: 20px; color: #999;">No status data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($showPerformance)
    <div class="chart-card report-panel">
        <h3>Job Type Distribution</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Job Type</th>
                        <th>Total Jobs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobTypeDistribution as $item)
                        <tr>
                            <td>{{ $item->label }}</td>
                            <td>{{ number_format((int) $item->total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" style="text-align: center; padding: 20px; color: #999;">No job type data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($showSales)
    <div class="chart-card report-panel">
        <h3>Top Stores by Revenue</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Revenue</th>
                        <th>Total Orders</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topStoresByRevenue as $store)
                        <tr>
                            <td>{{ $store->shop_domain }}</td>
                            <td>${{ number_format((float) $store->revenue, 2) }}</td>
                            <td>{{ number_format((int) $store->total_orders) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px; color: #999;">No revenue data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($showInventory)
    <div class="chart-card report-panel">
        <h3>Top Products (By Quantity)</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>SKU / Product</th>
                        <th>Total Qty</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topProducts as $item)
                        <tr>
                            <td>{{ $item->sku_label }}</td>
                            <td>{{ number_format((int) $item->total_qty) }}</td>
                            <td>${{ number_format((float) $item->total_value, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px; color: #999;">No product data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="chart-card report-panel">
        <h3>Product Type Performance</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Product Type</th>
                        <th>Total Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productTypePerformance as $item)
                        <tr>
                            <td>{{ $item->label }}</td>
                            <td>{{ number_format((int) $item->total_qty) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" style="text-align: center; padding: 20px; color: #999;">No product type data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($showFulfillment)
    <div class="chart-card report-panel">
        <h3>Recent Exceptions</h3>
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Store</th>
                        <th>Order ID</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exceptionJobs as $job)
                        <tr>
                            <td>#{{ $job->id }}</td>
                            <td>{{ $job->shop->shop_domain ?? '-' }}</td>
                            <td>#{{ $job->order_id ?? '-' }}</td>
                            <td>{{ strtoupper(str_replace('_', ' ', $job->status)) }}</td>
                            <td>{{ $job->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: #999;">No exception jobs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination" style="margin-top: 16px;">
            @if($exceptionJobs->onFirstPage())
                <button class="btn-pagination" disabled>Previous</button>
            @else
                <a href="{{ $exceptionJobs->previousPageUrl() }}" class="btn-pagination">Previous</a>
            @endif
            <span class="page-info">Page {{ $exceptionJobs->currentPage() }} of {{ $exceptionJobs->lastPage() }}</span>
            @if($exceptionJobs->hasMorePages())
                <a href="{{ $exceptionJobs->nextPageUrl() }}" class="btn-pagination">Next</a>
            @else
                <button class="btn-pagination" disabled>Next</button>
            @endif
        </div>
    </div>
    @endif
    </div>
@endsection

@push('styles')
    <style>
        .reports-page {
            display: grid;
            gap: 14px;
        }

        .reports-page .reports-hero {
            border-radius: 16px;
            border: 1px solid rgba(96, 165, 250, 0.28);
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.2), transparent 45%),
                linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.96));
            box-shadow: 0 18px 34px rgba(2, 6, 23, 0.34);
            padding: 18px 20px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: center;
        }

        .reports-page .reports-eyebrow {
            margin: 0 0 6px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #93c5fd;
            font-weight: 700;
        }

        .reports-page .reports-hero h2 {
            margin: 0;
            color: #f8fafc;
            font-size: 27px;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .reports-page .reports-subtitle {
            margin: 9px 0 0;
            color: #a8bcda;
            font-size: 14px;
        }

        .reports-page .reports-hero-pill {
            border-radius: 999px;
            border: 1px solid rgba(125, 211, 252, 0.38);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.3), rgba(14, 116, 144, 0.24));
            color: #e0f2fe;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            padding: 10px 14px;
            display: inline-grid;
            gap: 2px;
            justify-items: end;
            text-transform: uppercase;
        }

        .reports-page .reports-hero-pill span {
            font-size: 11px;
            color: #c7ddff;
            letter-spacing: 0.01em;
            text-transform: none;
            font-weight: 600;
        }

        .reports-page .reports-filters {
            margin: 0;
            padding: 14px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.24);
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 48%),
                linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.92));
            box-shadow: 0 14px 28px rgba(2, 6, 23, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.03);
            display: grid;
            grid-template-columns: repeat(6, minmax(130px, 1fr)) auto;
            gap: 12px;
            align-items: end;
        }

        .reports-page .reports-filters .filter-group {
            min-width: 0;
            margin: 0;
        }

        .reports-page .reports-filters .filter-group label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #b5c9e8;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .reports-page .reports-filters .filter-select {
            height: 42px;
            border-radius: 10px;
            border-color: rgba(148, 163, 184, 0.33);
            background-color: rgba(15, 23, 42, 0.8);
            color: #f8fafc;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
        }

        .reports-page .reports-filter-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(104px, 1fr));
            gap: 10px;
            min-width: 340px;
            align-items: stretch;
        }

        .reports-page .reports-filter-actions .btn-secondary,
        .reports-page .reports-filter-actions .btn-primary {
            height: 42px;
            min-height: 42px;
            border-radius: 10px;
            margin: 0;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            box-sizing: border-box;
            appearance: none;
            -webkit-appearance: none;
        }

        .reports-page .reports-filter-actions .btn-primary {
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.28);
        }

        .reports-page .reports-kpi-grid {
            margin-top: 2px;
            gap: 14px;
            perspective: 1000px;
        }

        .reports-page .reports-kpi-grid-secondary {
            margin-top: 0;
        }

        .reports-page .report-panel {
            margin-top: 0 !important;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background:
                radial-gradient(circle at top right, rgba(56, 189, 248, 0.08), transparent 44%),
                linear-gradient(165deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.94));
            box-shadow: 0 14px 30px rgba(2, 6, 23, 0.28);
            padding: 18px 18px;
        }

        .reports-page .report-panel h3 {
            margin: 0 0 14px;
            color: #f8fafc;
            font-size: 18px;
            letter-spacing: -0.01em;
        }

        .reports-page .report-panel .jobs-table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #a9bddf;
            background: rgba(15, 23, 42, 0.52);
            border-bottom-color: rgba(148, 163, 184, 0.2);
        }

        .reports-page .report-panel .jobs-table td {
            border-bottom-color: rgba(148, 163, 184, 0.15);
        }

        .reports-page .report-panel .jobs-table tbody tr:hover {
            background: linear-gradient(120deg, rgba(59, 130, 246, 0.1), rgba(15, 23, 42, 0.16));
        }

        .reports-page .report-panel .pagination {
            margin-top: 14px !important;
        }

        .reports-page .report-chart-shell {
            transition: transform 220ms ease, box-shadow 220ms ease, border-color 220ms ease;
            border: 1px solid rgba(148, 163, 184, 0.14);
        }

        .reports-page .report-chart-shell.chart-hovered {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 14px 28px rgba(2, 6, 23, 0.4);
            border-color: rgba(59, 130, 246, 0.35);
        }

        .reports-page .report-chart-shell.chart-bounce {
            animation: reportChartBounce 320ms ease;
        }

        @keyframes reportChartBounce {
            0% { transform: translateY(0) scale(1); }
            40% { transform: translateY(-5px) scale(1.016); }
            70% { transform: translateY(-2px) scale(1.006); }
            100% { transform: translateY(-4px) scale(1.01); }
        }

        .reports-page .reports-kpi-grid {
            perspective: 1000px;
        }

        .reports-page .report-kpi-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 16px;
            transition: transform 280ms ease, box-shadow 280ms ease, border-color 280ms ease, filter 280ms ease;
            transform-style: preserve-3d;
            animation: reportsCardEnter 560ms ease both;
            background-size: 140% 140%;
            background-position: 0% 50%;
        }

        .reports-page .report-kpi-card:nth-child(2) { animation-delay: 70ms; }
        .reports-page .report-kpi-card:nth-child(3) { animation-delay: 120ms; }
        .reports-page .report-kpi-card:nth-child(4) { animation-delay: 170ms; }

        .reports-page .report-kpi-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            opacity: 0;
            background: radial-gradient(260px circle at var(--mx, 50%) var(--my, 50%), rgba(255, 255, 255, 0.26), transparent 55%);
            transition: opacity 280ms ease;
            pointer-events: none;
        }

        .reports-page .report-kpi-card::after {
            content: '';
            position: absolute;
            top: -40%;
            left: -40%;
            width: 180%;
            height: 180%;
            background: linear-gradient(120deg, transparent 42%, rgba(255, 255, 255, 0.24) 50%, transparent 58%);
            transform: translateX(-70%) rotate(12deg);
            transition: transform 480ms ease;
            pointer-events: none;
        }

        .reports-page .report-kpi-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.32);
            border-color: rgba(255, 255, 255, 0.28);
            filter: saturate(1.08);
            background-position: 100% 50%;
        }

        .reports-page .report-kpi-card:hover::before {
            opacity: 1;
        }

        .reports-page .report-kpi-card:hover::after {
            transform: translateX(65%) rotate(12deg);
        }

        .reports-page .report-kpi-card .card-content {
            position: relative;
            z-index: 2;
        }

        .reports-page .report-kpi-card.card-blue {
            background-image: linear-gradient(135deg, rgba(37, 99, 235, 0.35), rgba(14, 165, 233, 0.22) 48%, rgba(59, 130, 246, 0.3));
        }

        .reports-page .report-kpi-card.card-green {
            background-image: linear-gradient(135deg, rgba(5, 150, 105, 0.34), rgba(16, 185, 129, 0.22) 48%, rgba(34, 197, 94, 0.28));
        }

        .reports-page .report-kpi-card.card-orange {
            background-image: linear-gradient(135deg, rgba(217, 119, 6, 0.34), rgba(245, 158, 11, 0.2) 48%, rgba(251, 146, 60, 0.3));
        }

        .reports-page .report-kpi-card.card-purple {
            background-image: linear-gradient(135deg, rgba(109, 40, 217, 0.34), rgba(168, 85, 247, 0.2) 48%, rgba(129, 140, 248, 0.3));
        }

        .reports-page .report-kpi-card.card-red {
            background-image: linear-gradient(135deg, rgba(185, 28, 28, 0.35), rgba(239, 68, 68, 0.2) 48%, rgba(248, 113, 113, 0.28));
        }

        .reports-page .report-kpi-card .card-value {
            letter-spacing: 0.2px;
            transition: transform 240ms ease, color 240ms ease;
        }

        .reports-page .report-kpi-card:hover .card-value {
            transform: translateY(-1px) scale(1.03);
            color: #f8fafc;
        }

        @keyframes reportsCardEnter {
            from {
                opacity: 0;
                transform: translateY(14px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 1200px) {
            .reports-page .reports-filters {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .reports-page .reports-filter-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                min-width: 0;
            }
        }

        @media (max-width: 992px) {
            .reports-page .reports-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .reports-page .reports-filter-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 860px) {
            .reports-page .reports-hero {
                grid-template-columns: 1fr;
            }

            .reports-page .reports-hero-pill {
                justify-items: start;
                width: fit-content;
            }

            .reports-page .reports-filters {
                grid-template-columns: 1fr;
            }

            .reports-page .reports-filter-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterForm = document.getElementById('reportsFilterForm');
            if (filterForm) {
                ['report_type', 'shop_id', 'fulfillment_status', 'job_type', 'from', 'to'].forEach(function (id) {
                    const field = document.getElementById(id);
                    if (!field) return;
                    field.addEventListener('change', function () {
                        filterForm.requestSubmit();
                    });
                });
            }

            const reportCards = document.querySelectorAll('.reports-page .report-kpi-card');
            reportCards.forEach(function (card) {
                card.addEventListener('mousemove', function (event) {
                    const rect = card.getBoundingClientRect();
                    const x = ((event.clientX - rect.left) / rect.width) * 100;
                    const y = ((event.clientY - rect.top) / rect.height) * 100;
                    card.style.setProperty('--mx', x + '%');
                    card.style.setProperty('--my', y + '%');
                });

                card.addEventListener('mouseleave', function () {
                    card.style.setProperty('--mx', '50%');
                    card.style.setProperty('--my', '50%');
                });
            });

            const labels = @json($timeline['labels']);
            const ordersData = @json($timeline['orders']);
            const revenueData = @json($timeline['revenue']);

            const ordersCanvas = document.getElementById('ordersTrendChart');
            if (ordersCanvas && typeof Chart !== 'undefined') {
                const ordersShell = ordersCanvas.closest('.chart-card');
                if (ordersShell) {
                    ordersShell.classList.add('report-chart-shell');
                    ordersShell.addEventListener('mouseenter', function () {
                        ordersShell.classList.add('chart-hovered', 'chart-bounce');
                    });
                    ordersShell.addEventListener('mouseleave', function () {
                        ordersShell.classList.remove('chart-hovered');
                    });
                    ordersShell.addEventListener('animationend', function () {
                        ordersShell.classList.remove('chart-bounce');
                    });
                }

                const ordersCtx = ordersCanvas.getContext('2d');
                const ordersGradient = ordersCtx.createLinearGradient(0, 0, 0, ordersCanvas.height || 320);
                ordersGradient.addColorStop(0, 'rgba(59,130,246,0.38)');
                ordersGradient.addColorStop(1, 'rgba(59,130,246,0.04)');

                new Chart(ordersCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Orders',
                            data: ordersData,
                            borderColor: '#3b82f6',
                            backgroundColor: ordersGradient,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 2.5,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: '#ffffff',
                            pointHoverBorderColor: '#3b82f6',
                            pointHoverBorderWidth: 2,
                            tension: 0.34
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        animation: {
                            duration: 1300,
                            easing: 'easeOutQuart'
                        },
                        transitions: {
                            active: {
                                animation: {
                                    duration: 260
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                grid: {
                                    color: 'rgba(148,163,184,0.2)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(148,163,184,0.12)'
                                }
                            }
                        }
                    }
                });
            }

            const revenueCanvas = document.getElementById('revenueTrendChart');
            if (revenueCanvas && typeof Chart !== 'undefined') {
                const revenueShell = revenueCanvas.closest('.chart-card');
                if (revenueShell) {
                    revenueShell.classList.add('report-chart-shell');
                    revenueShell.addEventListener('mouseenter', function () {
                        revenueShell.classList.add('chart-hovered', 'chart-bounce');
                    });
                    revenueShell.addEventListener('mouseleave', function () {
                        revenueShell.classList.remove('chart-hovered');
                    });
                    revenueShell.addEventListener('animationend', function () {
                        revenueShell.classList.remove('chart-bounce');
                    });
                }

                new Chart(revenueCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Revenue',
                            data: revenueData,
                            borderColor: '#10b981',
                            backgroundColor: function (context) {
                                const chart = context.chart;
                                const area = chart.chartArea;
                                if (!area) {
                                    return 'rgba(16,185,129,0.45)';
                                }
                                const gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                                gradient.addColorStop(0, 'rgba(16,185,129,0.82)');
                                gradient.addColorStop(1, 'rgba(16,185,129,0.32)');
                                return gradient;
                            },
                            hoverBackgroundColor: 'rgba(16,185,129,0.95)',
                            hoverBorderWidth: 2,
                            borderWidth: 1.5,
                            borderRadius: 8,
                            maxBarThickness: 34
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        animation: {
                            duration: 1200,
                            easing: 'easeOutQuart',
                            delay: function (context) {
                                return context.type === 'data' ? context.dataIndex * 36 : 0;
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(148,163,184,0.2)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(148,163,184,0.08)'
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
@endpush
