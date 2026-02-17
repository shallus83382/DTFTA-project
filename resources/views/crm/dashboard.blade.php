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
        .dashboard-pro {
            display: grid;
            gap: 20px;
            position: relative;
            isolation: isolate;
        }
        .dashboard-pro::before {
            content: "";
            position: absolute;
            inset: -80px -40px auto auto;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.22), rgba(59, 130, 246, 0));
            z-index: -1;
            pointer-events: none;
        }
        .db-hero {
            background: linear-gradient(120deg, #0f172a, #1e3a8a 45%, #0f766e);
            border: 1px solid rgba(125, 211, 252, 0.35);
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
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
            gap: 14px;
        }
        .db-kpi {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease, background 0.25s ease;
        }
        .db-kpi:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.28);
            border-color: rgba(96, 165, 250, 0.45);
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.95), rgba(51, 65, 85, 0.85));
        }
        .db-kpi:nth-child(1) { border-top: 3px solid #38bdf8; }
        .db-kpi:nth-child(2) { border-top: 3px solid #f59e0b; }
        .db-kpi:nth-child(3) { border-top: 3px solid #6366f1; }
        .db-kpi:nth-child(4) { border-top: 3px solid #ef4444; }
        .db-kpi-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }
        .db-kpi-value {
            font-size: 28px;
            line-height: 1.1;
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.25s ease, transform 0.25s ease;
        }
        .db-kpi:hover .db-kpi-value {
            color: #bfdbfe;
        }
        .db-kpi-sub {
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        .db-main-grid {
            display: grid;
            grid-template-columns: 1.65fr 1fr;
            gap: 16px;
        }
        .db-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 18px;
            transition: border-color 0.22s ease, box-shadow 0.22s ease;
        }
        .db-panel:hover {
            border-color: rgba(45, 212, 191, 0.45);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.16);
        }
        .db-panel h3 {
            margin: 0 0 6px;
            color: var(--text-primary);
            font-size: 17px;
        }
        .db-panel p {
            margin: 0;
            color: var(--text-muted);
            font-size: 12px;
        }
        .db-chart-wrap {
            margin-top: 16px;
            min-height: 260px;
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
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 12px;
            background: var(--bg-secondary);
            gap: 10px;
        }
        .db-list-name {
            font-size: 13px;
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
        }
        .db-chip {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: 999px;
            font-size: 12px;
            padding: 6px 10px;
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
            overflow-y: auto;
            padding-right: 4px;
        }
        .db-snapshot-stack {
            margin-top: 14px;
            display: grid;
            gap: 10px;
            flex: 1;
        }
        .db-snapshot-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 12px;
            display: grid;
            gap: 6px;
            transition: transform 0.2s ease, border-color 0.2s ease;
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
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        .db-health-track {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.18);
            overflow: hidden;
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
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 6px 10px;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
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
            .db-main-grid, .db-bottom-grid {
                grid-template-columns: 1fr;
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
                    <div class="db-snapshot-card">
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

                    <div class="db-snapshot-card">
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

                    <div class="db-snapshot-card">
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

                    <div class="db-snapshot-card">
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
                        legend: { display: false }
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
