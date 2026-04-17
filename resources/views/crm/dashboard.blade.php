@extends('layouts.app')

@section('title', 'DTFTA CRM - Dashboard')
@section('page-title', 'Dashboard Overview')

@section('content')
    @php
        $totalOrders = max((int) ($stats['totalOrders'] ?? 0), 1);
        $pendingJobs = (int) ($stats['pendingJobs'] ?? 0);
        $inProduction = (int) ($stats['inProduction'] ?? 0);
        $shippedThisMonth = (int) ($stats['shippedThisMonth'] ?? 0);
        $exceptions = (int) ($stats['exceptions'] ?? 0);
        $ordersToday = (int) ($stats['ordersToday'] ?? 0);
        $ordersThisWeek = (int) ($stats['ordersThisWeek'] ?? 0);
        $exceptionRate = round(($exceptions / $totalOrders) * 100, 1);
        $workloadTotal = max($pendingJobs + $inProduction + $shippedThisMonth + $exceptions, 1);
    @endphp

    <style>
        .main-content > .page-header {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: linear-gradient(130deg, rgba(15, 23, 42, 0.88), rgba(30, 41, 59, 0.84));
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.26);
        }
        .main-content > .page-header .page-heading {
            gap: 7px;
        }
        .main-content > .page-header .page-breadcrumbs {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(30, 41, 59, 0.52);
            font-size: 11px;
            letter-spacing: 0.02em;
            color: #9fb1cf;
        }
        .main-content > .page-header .page-breadcrumbs .current {
            color: #dbe6f7;
            font-weight: 600;
        }
        .main-content > .page-header h1 {
            font-size: 33px;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: #f8fafc;
            margin: 0;
        }
        .main-content > .page-header .header-actions {
            gap: 10px;
            padding: 4px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.35);
            border: 1px solid rgba(148, 163, 184, 0.16);
        }
        .main-content > .page-header .btn-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border-color: rgba(148, 163, 184, 0.28);
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.85), rgba(15, 23, 42, 0.82));
        }
        .main-content > .page-header .btn-icon:hover {
            border-color: rgba(96, 165, 250, 0.55);
            color: #e2e8f0;
        }
        .main-content > .page-header .user-dropdown-trigger {
            border-radius: 12px;
            border-color: rgba(148, 163, 184, 0.28);
            background: linear-gradient(140deg, rgba(37, 99, 235, 0.18), rgba(15, 23, 42, 0.75));
            min-height: 44px;
        }
        .main-content > .page-header .user-dropdown-trigger:hover {
            border-color: rgba(96, 165, 250, 0.5);
            background: linear-gradient(140deg, rgba(59, 130, 246, 0.24), rgba(15, 23, 42, 0.84));
        }
        .main-content > .page-header .user-avatar-sm {
            border: 2px solid rgba(255, 255, 255, 0.14);
        }

        .dashboard-pro {
            display: grid;
            gap: 18px;
            position: relative;
            isolation: isolate;
        }
        .db-hero {
            background: linear-gradient(120deg, rgba(15, 23, 42, 0.98), rgba(30, 58, 138, 0.9) 48%, rgba(15, 118, 110, 0.88));
            border: 1px solid rgba(125, 211, 252, 0.42);
            border-radius: 16px;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            box-shadow: 0 14px 34px rgba(2, 6, 23, 0.38);
            backdrop-filter: blur(9px);
        }
        .db-hero h2 {
            margin: 0;
            font-size: 22px;
            color: #f8fafc;
        }
        .db-hero p {
            margin: 6px 0 0;
            color: #cbd5e1;
            font-size: 13px;
        }
        .db-hero-tag {
            background: rgba(14, 165, 233, 0.2);
            color: #e0f2fe;
            border: 1px solid rgba(56, 189, 248, 0.45);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .db-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .db-kpi {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.94));
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 14px;
            padding: 16px;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease, background 0.25s ease;
            box-shadow: 0 10px 24px rgba(2, 6, 23, 0.24);
        }
        .db-kpi::before {
            content: "";
            position: absolute;
            right: -38px;
            top: -38px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(148, 163, 184, 0.18), rgba(148, 163, 184, 0));
            pointer-events: none;
        }
        .db-kpi::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            pointer-events: none;
        }
        .db-kpi:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(30, 64, 175, 0.25);
            border-color: rgba(96, 165, 250, 0.5);
            background: linear-gradient(145deg, rgba(30, 41, 59, 1), rgba(51, 65, 85, 0.88));
        }
        .db-kpi:nth-child(1) { border-top: 3px solid #38bdf8; }
        .db-kpi:nth-child(2) { border-top: 3px solid #f59e0b; }
        .db-kpi:nth-child(3) { border-top: 3px solid #6366f1; }
        .db-kpi:nth-child(4) { border-top: 3px solid #ef4444; }
        .db-kpi-label {
            font-size: 12px;
            color: #9db0cf;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .db-kpi-value {
            font-size: 34px;
            line-height: 1.1;
            color: #f8fbff;
            font-weight: 700;
            transition: color 0.25s ease, transform 0.25s ease;
        }
        .db-kpi:hover .db-kpi-value {
            color: #bfdbfe;
        }
        .db-kpi-sub {
            margin-top: 6px;
            font-size: 12px;
            color: #afc0da;
        }
        .db-main-grid {
            display: grid;
            grid-template-columns: 1.65fr 1fr;
            gap: 12px;
        }
        .db-panel {
            position: relative;
            overflow: hidden;
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.94), rgba(15, 23, 42, 0.94));
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 14px;
            padding: 18px;
            transition: border-color 0.22s ease, box-shadow 0.22s ease;
            box-shadow: 0 12px 26px rgba(2, 6, 23, 0.2);
        }
        .db-panel::before {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 1px;
            background: linear-gradient(90deg, rgba(56, 189, 248, 0.25), rgba(45, 212, 191, 0.15), rgba(56, 189, 248, 0.25));
            pointer-events: none;
        }
        .db-panel:hover {
            border-color: rgba(45, 212, 191, 0.45);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.25);
        }
        .db-panel h3 {
            margin: 0 0 6px;
            color: #f8fafc;
            font-size: 24px;
            line-height: 1.15;
            letter-spacing: -0.01em;
        }
        .db-panel p {
            margin: 0;
            color: #9eb1cf;
            font-size: 13px;
        }
        .db-chart-wrap {
            margin-top: 16px;
            min-height: 260px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: linear-gradient(165deg, rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.4));
            padding: 10px 12px 4px;
        }
        .db-side-stack {
            display: grid;
            gap: 14px;
        }
        .db-list {
            margin: 14px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 10px;
        }
        .db-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 10px;
            padding: 10px 12px;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.84));
            gap: 10px;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }
        .db-list li:hover {
            border-color: rgba(96, 165, 250, 0.4);
            transform: translateY(-1px);
        }
        .db-list-name {
            font-size: 14px;
            color: var(--text-primary);
            word-break: break-word;
        }
        .db-list-value {
            font-weight: 600;
            color: #93c5fd;
            font-size: 13px;
            white-space: nowrap;
        }
        .db-chip-row {
            margin-top: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            min-height: 42px;
            align-items: flex-start;
        }
        .db-chip {
            background: linear-gradient(135deg, rgba(51, 65, 85, 0.72), rgba(30, 41, 59, 0.7));
            border: 1px solid rgba(148, 163, 184, 0.25);
            color: #dbe6f7;
            border-radius: 999px;
            font-size: 12px;
            padding: 6px 10px;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .db-chip:hover {
            border-color: rgba(45, 212, 191, 0.42);
            color: #ccfbf1;
        }
        .db-bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: stretch;
        }
        .db-activity-panel,
        .db-snapshot-panel {
            min-height: 460px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .db-activity-list {
            margin-top: 14px;
            flex: 1;
            overflow: visible;
            padding-right: 6px;
            display: grid;
            gap: 10px;
            align-content: start;
        }
        .db-activity-list .activity-item {
            margin: 0;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.24);
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.86));
            padding: 12px;
            gap: 12px;
            align-items: flex-start;
            transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        .db-activity-list .activity-item:hover {
            border-color: rgba(96, 165, 250, 0.45);
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(2, 6, 23, 0.22);
        }
        .db-activity-list .activity-icon {
            width: 30px;
            height: 30px;
            font-size: 11px;
            border-radius: 10px;
            background: linear-gradient(145deg, rgba(59, 130, 246, 0.35), rgba(59, 130, 246, 0.15));
            border: 1px solid rgba(96, 165, 250, 0.45);
            color: #dbeafe;
            font-weight: 700;
            text-transform: uppercase;
        }
        .db-activity-list .activity-content p {
            margin: 0;
            line-height: 1.38;
            color: #d9e4f5;
            font-size: 13px;
        }
        .db-activity-list .activity-content strong {
            color: #f8fafc;
            font-weight: 700;
        }
        .db-activity-list .activity-time {
            margin-top: 4px;
            display: inline-block;
            font-size: 11px;
            color: #94a3b8;
        }
        .db-snapshot-stack {
            margin-top: 14px;
            display: grid;
            gap: 12px;
            flex: 1;
        }
        .db-snapshot-card {
            background: linear-gradient(140deg, rgba(30, 41, 59, 0.88), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            padding: 12px;
            display: grid;
            gap: 8px;
            transition: transform 0.2s ease, border-color 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .db-snapshot-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.45);
        }
        .db-snapshot-card.pending::before { background: #f59e0b; }
        .db-snapshot-card.production::before { background: #3b82f6; }
        .db-snapshot-card.shipped::before { background: #10b981; }
        .db-snapshot-card.exception::before { background: #ef4444; }
        .db-snapshot-card > * {
            margin-left: 4px;
        }
        .db-snapshot-card:hover {
            transform: translateY(-2px);
            border-color: rgba(45, 212, 191, 0.55);
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.92), rgba(15, 23, 42, 0.88));
        }
        .db-health-row {
            margin-top: 0;
        }
        .db-health-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #c7d5ec;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .db-health-track {
            width: 100%;
            height: 9px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.16);
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.22);
        }
        .db-health-fill {
            height: 100%;
            border-radius: 999px;
        }
        .db-health-fill.pending { background: #f59e0b; }
        .db-health-fill.production { background: #3b82f6; }
        .db-health-fill.shipped { background: #10b981; }
        .db-health-fill.exception { background: #ef4444; }
        .db-health-fill {
            box-shadow: 0 0 16px rgba(148, 163, 184, 0.3);
        }
        .db-snapshot-panel p,
        .db-activity-panel p {
            color: #9fb1cf;
        }
        .db-activity-pagination {
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--text-secondary);
        }
        .db-activity-pagination a,
        .db-activity-pagination button {
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: linear-gradient(135deg, rgba(51, 65, 85, 0.72), rgba(30, 41, 59, 0.7));
            color: var(--text-primary);
            border-radius: 8px;
            padding: 6px 10px;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .db-activity-pagination a:hover,
        .db-activity-pagination button:hover:not([disabled]) {
            border-color: rgba(96, 165, 250, 0.45);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.12));
        }
        .db-activity-pagination button[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .db-bounce {
            animation: dbBounce 0.45s ease;
        }
        @keyframes dbBounce {
            0% { transform: translateY(0); }
            35% { transform: translateY(-6px); }
            70% { transform: translateY(2px); }
            100% { transform: translateY(0); }
        }
        @media (max-width: 1100px) {
            .main-content > .page-header {
                padding: 12px;
            }
            .main-content > .page-header h1 {
                font-size: 28px;
            }
            .db-main-grid, .db-bottom-grid {
                grid-template-columns: 1fr;
            }
            .db-panel h3 {
                font-size: 20px;
            }
            .db-activity-panel,
            .db-snapshot-panel {
                min-height: auto;
            }
        }
    </style>

    <div class="dashboard-pro">
        <section class="db-hero">
            <div>
                <h2>Operations Dashboard</h2>
                <p>Real-time fulfillment overview for orders, production workload, and risk signals.</p>
            </div>
            <span class="db-hero-tag">{{ now()->format('M d, Y') }}</span>
        </section>

        <section class="db-kpi-grid">
            <div class="db-kpi">
                <div class="db-kpi-label">Total Orders</div>
                <div class="db-kpi-value db-counter" data-target="{{ (int)($stats['totalOrders'] ?? 0) }}" data-type="number">0</div>
                <div class="db-kpi-sub">Today {{ $ordersToday }} | This week {{ $ordersThisWeek }}</div>
            </div>
            <div class="db-kpi">
                <div class="db-kpi-label">Pending Jobs</div>
                <div class="db-kpi-value db-counter" data-target="{{ $pendingJobs }}" data-type="number">0</div>
                <div class="db-kpi-sub">Waiting for production flow</div>
            </div>
            <div class="db-kpi">
                <div class="db-kpi-label">In Production</div>
                <div class="db-kpi-value db-counter" data-target="{{ $inProduction }}" data-type="number">0</div>
                <div class="db-kpi-sub">Actively being processed</div>
            </div>
            <div class="db-kpi">
                <div class="db-kpi-label">Exception Rate</div>
                <div class="db-kpi-value db-counter" data-target="{{ $exceptionRate }}" data-type="percent">0%</div>
                <div class="db-kpi-sub">{{ $exceptions }} exception cases detected</div>
            </div>
        </section>

        <section class="db-main-grid">
            <article class="db-panel">
                <h3>Order Volume Trend (7 Days)</h3>
                <p>Primary performance trend for recent order intake.</p>
                <div class="db-chart-wrap">
                    <canvas id="dashboardOrdersTrend"></canvas>
                </div>
            </article>

            <div class="db-side-stack">
                <article class="db-panel">
                    <h3>Top Stores</h3>
                    <p>Highest order sources in the current snapshot.</p>
                    <ul class="db-list">
                        @forelse($ordersByShop as $store)
                            <li>
                                <span class="db-list-name">{{ $store->shop_domain }}</span>
                                <span class="db-list-value">{{ $store->count }} orders</span>
                            </li>
                        @empty
                            <li>
                                <span class="db-list-name">No store data available</span>
                            </li>
                        @endforelse
                    </ul>
                </article>
                <article class="db-panel">
                    <h3>Job Type Mix</h3>
                    <p>Distribution by job category.</p>
                    <div class="db-chip-row">
                        @forelse($jobTypeDistribution as $jobType)
                            <span class="db-chip">{{ $jobType->job_type ?: 'Unknown' }}: {{ $jobType->count }}</span>
                        @empty
                            <span class="db-chip">No job type data</span>
                        @endforelse
                    </div>
                </article>
            </div>
        </section>

        <section class="db-bottom-grid">
            <article class="db-panel db-activity-panel">
                <h3>Recent Activity</h3>
                <p>Latest order and job updates.</p>
                <div class="activity-list db-activity-list">
                    @forelse($recentActivity as $activity)
                        <div class="activity-item">
                            <div class="activity-icon status-{{ strtolower($activity->action) }}">{{ substr($activity->action, 0, 1) }}</div>
                            <div class="activity-content">
                                <p>
                                    <strong>{{ $activity->model_type }}#{{ $activity->model_id }}</strong> - {{ $activity->action }}
                                    @if($activity->user)
                                        by {{ $activity->user->name }}
                                    @endif
                                </p>
                                <span class="activity-time">{{ $activity->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="activity-item">
                            <p>No recent activity</p>
                        </div>
                    @endforelse
                </div>
                @if(method_exists($recentActivity, 'hasPages') && $recentActivity->hasPages())
                    <div class="db-activity-pagination">
                        <div>Page {{ $recentActivity->currentPage() }} of {{ $recentActivity->lastPage() }}</div>
                        <div style="display:flex; gap:8px;">
                            @if($recentActivity->onFirstPage())
                                <button type="button" disabled>Previous</button>
                            @else
                                <a href="{{ $recentActivity->previousPageUrl() }}">Previous</a>
                            @endif
                            @if($recentActivity->hasMorePages())
                                <a href="{{ $recentActivity->nextPageUrl() }}">Next</a>
                            @else
                                <button type="button" disabled>Next</button>
                            @endif
                        </div>
                    </div>
                @endif
            </article>
            <article class="db-panel db-snapshot-panel">
                <h3>Operational Snapshot</h3>
                <p>Current workload balance across the pipeline.</p>

                <div class="db-snapshot-stack">
                    <div class="db-snapshot-card pending">
                        <div class="db-health-row">
                            <div class="db-health-label">
                                <span>Pending</span>
                                <span>{{ $pendingJobs }}</span>
                            </div>
                            <div class="db-health-track">
                                <div class="db-health-fill pending" style="width: {{ round(($pendingJobs / $workloadTotal) * 100, 2) }}%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="db-snapshot-card production">
                        <div class="db-health-row">
                            <div class="db-health-label">
                                <span>In Production</span>
                                <span>{{ $inProduction }}</span>
                            </div>
                            <div class="db-health-track">
                                <div class="db-health-fill production" style="width: {{ round(($inProduction / $workloadTotal) * 100, 2) }}%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="db-snapshot-card shipped">
                        <div class="db-health-row">
                            <div class="db-health-label">
                                <span>Shipped (This Month)</span>
                                <span>{{ $shippedThisMonth }}</span>
                            </div>
                            <div class="db-health-track">
                                <div class="db-health-fill shipped" style="width: {{ round(($shippedThisMonth / $workloadTotal) * 100, 2) }}%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="db-snapshot-card exception">
                        <div class="db-health-row">
                            <div class="db-health-label">
                                <span>Exceptions</span>
                                <span>{{ $exceptions }}</span>
                            </div>
                            <div class="db-health-track">
                                <div class="db-health-fill exception" style="width: {{ round(($exceptions / $workloadTotal) * 100, 2) }}%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const trendCanvas = document.getElementById('dashboardOrdersTrend');
        if (trendCanvas && typeof Chart !== 'undefined') {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: {!! json_encode($ordersByDay->pluck('label')) !!},
                    datasets: [{
                        label: 'Orders',
                        data: {!! json_encode($ordersByDay->pluck('count')) !!},
                        borderColor: '#60a5fa',
                        backgroundColor: 'rgba(96, 165, 250, 0.14)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3.5,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            borderColor: 'rgba(96, 165, 250, 0.45)',
                            borderWidth: 1,
                            titleColor: '#e2e8f0',
                            bodyColor: '#cbd5e1',
                            padding: 10
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: 'rgba(148, 163, 184, 0.12)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#94a3b8', precision: 0 },
                            grid: { color: 'rgba(148, 163, 184, 0.12)' }
                        }
                    }
                }
            });
        }

        function animateDashboardCounters() {
            const counters = document.querySelectorAll('.db-counter');
            counters.forEach((counter) => {
                const target = parseFloat(counter.dataset.target || '0');
                const type = counter.dataset.type || 'number';
                const duration = 900;
                const startTime = performance.now();

                function frame(now) {
                    const progress = Math.min((now - startTime) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const current = target * eased;

                    if (type === 'percent') {
                        counter.textContent = `${current.toFixed(1)}%`;
                    } else {
                        counter.textContent = Math.round(current).toLocaleString();
                    }

                    if (progress < 1) {
                        requestAnimationFrame(frame);
                    } else {
                        counter.classList.add('db-bounce');
                        setTimeout(() => counter.classList.remove('db-bounce'), 500);
                    }
                }
                requestAnimationFrame(frame);
            });
        }

        function bindKpiHoverBounce() {
            document.querySelectorAll('.db-kpi').forEach((card) => {
                const value = card.querySelector('.db-counter');
                if (!value) return;
                card.addEventListener('mouseenter', () => {
                    value.classList.remove('db-bounce');
                    void value.offsetWidth;
                    value.classList.add('db-bounce');
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            animateDashboardCounters();
            bindKpiHoverBounce();
        });
    </script>
@endsection
