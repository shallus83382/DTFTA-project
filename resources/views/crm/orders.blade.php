@extends('layouts.app')

@section('title', 'Orders / Jobs Management')

@push('styles')
<style>
    #boardView.orders-board-view .filters-section {
        position: relative;
        padding: 14px;
        margin-bottom: 14px;
        gap: 10px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 14px;
        background: linear-gradient(130deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.9));
        box-shadow: 0 12px 30px rgba(2, 6, 23, 0.35);
        backdrop-filter: blur(8px);
    }

    #boardView.orders-board-view .lead-filters-left {
        gap: 8px;
    }

    #boardView.orders-board-view .lead-search-input {
        border-radius: 10px;
        border-color: rgba(148, 163, 184, 0.28);
        background: rgba(15, 23, 42, 0.65);
        height: 40px;
        font-size: 13px;
    }

    #boardView.orders-board-view .lead-search-input:focus {
        border-color: rgba(96, 165, 250, 0.8);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
    }

    #boardView.orders-board-view #boardFilterBtn {
        height: 40px;
        padding: 0 16px;
        border-radius: 10px;
        border-color: rgba(96, 165, 250, 0.35);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.24), rgba(37, 99, 235, 0.24));
        font-weight: 600;
    }

    #boardView.orders-board-view .lead-filters-right {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    #boardView.orders-board-view .lead-filters-right .filter-select {
        height: 40px;
        border-radius: 10px;
        min-width: 165px;
    }

    #boardView.orders-board-view .view-toggle-section {
        margin-left: auto;
    }

    #boardView.orders-board-view .view-toggle-btn {
        width: 34px;
        height: 34px;
        padding: 0;
        justify-content: center;
        border-radius: 9px;
    }

    #boardView.orders-board-view .leads-board {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        min-height: 340px;
        height: auto;
        margin-top: 0;
        padding: 10px;
        align-items: stretch;
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
        box-sizing: border-box;
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 16px;
        background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.08), transparent 45%),
                    linear-gradient(180deg, rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.35));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03), 0 16px 30px rgba(2, 6, 23, 0.3);
    }

    #boardView.orders-board-view .lead-column {
        position: relative;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding: 10px;
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: linear-gradient(180deg, rgba(30, 41, 59, 0.86), rgba(15, 23, 42, 0.9));
        box-shadow: inset 0 1px 0 rgba(226, 232, 240, 0.04), 0 12px 24px rgba(2, 6, 23, 0.28);
    }

    #boardView.orders-board-view .lead-column::before {
        content: "";
        display: block;
        height: 3px;
        border-radius: 999px;
        margin-bottom: 8px;
        background: linear-gradient(90deg, rgba(148, 163, 184, 0.5), rgba(148, 163, 184, 0.12));
    }

    #boardView.orders-board-view .lead-column:nth-child(1)::before {
        background: linear-gradient(90deg, #3b82f6, rgba(59, 130, 246, 0.2));
    }
    #boardView.orders-board-view .lead-column:nth-child(2)::before {
        background: linear-gradient(90deg, #f59e0b, rgba(245, 158, 11, 0.2));
    }
    #boardView.orders-board-view .lead-column:nth-child(3)::before {
        background: linear-gradient(90deg, #8b5cf6, rgba(139, 92, 246, 0.2));
    }
    #boardView.orders-board-view .lead-column:nth-child(4)::before {
        background: linear-gradient(90deg, #10b981, rgba(16, 185, 129, 0.2));
    }
    #boardView.orders-board-view .lead-column:nth-child(5)::before {
        background: linear-gradient(90deg, #ef4444, rgba(239, 68, 68, 0.2));
    }

    #boardView.orders-board-view .lead-column-header h3 {
        font-size: 12px;
        letter-spacing: 0.35px;
        color: #dbe5f6;
        font-weight: 700;
        line-height: 1.25;
    }

    #boardView.orders-board-view .lead-column-header .btn-secondary {
        width: 26px;
        height: 26px;
        min-width: 26px;
        border-radius: 8px;
        border-color: rgba(148, 163, 184, 0.34);
        background: rgba(15, 23, 42, 0.75);
        color: #dbe5f6;
        font-size: 14px;
        padding: 0;
    }

    #boardView.orders-board-view .lead-column-body {
        gap: 8px;
        min-height: 150px;
        max-height: 460px;
        padding-right: 2px;
    }

    #boardView.orders-board-view .lead-card {
        position: relative;
        padding: 10px;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.97), rgba(15, 23, 42, 0.97));
        gap: 7px;
        box-shadow: 0 8px 20px rgba(2, 6, 23, 0.24);
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    #boardView.orders-board-view .lead-card::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: 12px;
        border: 1px solid rgba(226, 232, 240, 0.03);
        pointer-events: none;
    }

    #boardView.orders-board-view .lead-card:hover {
        transform: translateY(-2px) scale(1.01);
        border-color: rgba(96, 165, 250, 0.55);
        box-shadow: 0 14px 32px rgba(30, 64, 175, 0.22);
    }

    #boardView.orders-board-view .lead-avatar {
        width: 30px;
        height: 30px;
        font-size: 12px;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.75);
    }

    #boardView.orders-board-view .lead-name {
        font-size: 12px;
        color: #f8fafc;
        font-weight: 700;
    }

    #boardView.orders-board-view .lead-company,
    #boardView.orders-board-view .lead-time {
        font-size: 10px;
        color: #8ea0bd;
    }

    #boardView.orders-board-view .lead-description {
        font-size: 11px;
        margin: 0;
        -webkit-line-clamp: 2;
        color: #cbd5e1;
        line-height: 1.4;
    }

    #boardView.orders-board-view .lead-card .status-badge {
        padding: 2px 9px;
        font-size: 9px;
        font-weight: 700;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.45px;
        backdrop-filter: blur(4px);
    }

    #boardView.orders-board-view .lead-empty-state {
        margin: 0;
        min-height: 100px;
        display: grid;
        place-items: center;
        text-align: center;
        color: #95a9c8;
        font-size: 12px;
        border: 1px dashed rgba(148, 163, 184, 0.3);
        border-radius: 10px;
        background: linear-gradient(165deg, rgba(30, 41, 59, 0.38), rgba(15, 23, 42, 0.26));
        padding: 12px;
    }

    /* Final premium refinement layer */
    #boardView.orders-board-view .filters-section {
        border-color: rgba(96, 165, 250, 0.28);
        box-shadow: 0 14px 30px rgba(2, 6, 23, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }

    #boardView.orders-board-view .lead-column {
        border-color: rgba(96, 165, 250, 0.22);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.035), 0 14px 28px rgba(2, 6, 23, 0.3);
    }

    #boardView.orders-board-view .lead-column:hover {
        border-color: rgba(96, 165, 250, 0.36);
    }

    #boardView.orders-board-view .lead-column:nth-child(1) {
        background: linear-gradient(180deg, rgba(30, 58, 138, 0.22), rgba(15, 23, 42, 0.92));
    }
    #boardView.orders-board-view .lead-column:nth-child(2) {
        background: linear-gradient(180deg, rgba(217, 119, 6, 0.18), rgba(15, 23, 42, 0.92));
    }
    #boardView.orders-board-view .lead-column:nth-child(3) {
        background: linear-gradient(180deg, rgba(124, 58, 237, 0.18), rgba(15, 23, 42, 0.92));
    }
    #boardView.orders-board-view .lead-column:nth-child(4) {
        background: linear-gradient(180deg, rgba(5, 150, 105, 0.18), rgba(15, 23, 42, 0.92));
    }
    #boardView.orders-board-view .lead-column:nth-child(5) {
        background: linear-gradient(180deg, rgba(220, 38, 38, 0.16), rgba(15, 23, 42, 0.92));
    }

    /* Orders modal dropdown readability fix */
    #addLeadModal .form-group select {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.94), rgba(15, 23, 42, 0.86)) !important;
        color: #e2e8f0 !important;
        border-color: rgba(148, 163, 184, 0.35) !important;
        font-weight: 600;
    }

    #addLeadModal .form-group select option {
        background: rgba(15, 23, 42, 0.98) !important;
        color: #e2e8f0 !important;
    }

    #addLeadModal .form-group select option:disabled {
        color: #94a3b8 !important;
    }

    #boardView.orders-board-view .lead-column-header {
        padding-bottom: 6px;
        border-bottom: 1px dashed rgba(148, 163, 184, 0.2);
        margin-bottom: 10px;
    }

    #boardView.orders-board-view .lead-column-header h3 {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    #boardView.orders-board-view .lead-column-header h3::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: rgba(191, 219, 254, 0.9);
        box-shadow: 0 0 8px rgba(96, 165, 250, 0.8);
        flex-shrink: 0;
    }

    #boardView.orders-board-view .lead-column:nth-child(2) .lead-column-header h3::before {
        background: rgba(253, 230, 138, 0.95);
        box-shadow: 0 0 8px rgba(245, 158, 11, 0.85);
    }
    #boardView.orders-board-view .lead-column:nth-child(3) .lead-column-header h3::before {
        background: rgba(216, 180, 254, 0.95);
        box-shadow: 0 0 8px rgba(168, 85, 247, 0.85);
    }
    #boardView.orders-board-view .lead-column:nth-child(4) .lead-column-header h3::before {
        background: rgba(167, 243, 208, 0.95);
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.85);
    }
    #boardView.orders-board-view .lead-column:nth-child(5) .lead-column-header h3::before {
        background: rgba(254, 202, 202, 0.95);
        box-shadow: 0 0 8px rgba(239, 68, 68, 0.85);
    }

    #boardView.orders-board-view .lead-card {
        box-shadow: 0 10px 20px rgba(2, 6, 23, 0.24), inset 0 1px 0 rgba(255, 255, 255, 0.02);
    }

    #boardView.orders-board-view .lead-card:hover {
        box-shadow: 0 16px 30px rgba(30, 64, 175, 0.24);
    }

    #boardView.orders-board-view .lead-empty-state {
        border-style: solid;
        border-color: rgba(148, 163, 184, 0.22);
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.42), rgba(15, 23, 42, 0.3));
        color: #a9bddb;
        font-weight: 600;
    }

    #boardView.orders-board-view .lead-column-body::-webkit-scrollbar {
        width: 6px;
    }

    #boardView.orders-board-view .lead-column-body::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.3);
        border-radius: 999px;
    }

    @media (max-width: 1200px) {
        #boardView.orders-board-view .leads-board {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        #boardView.orders-board-view .filters-section {
            padding: 12px;
        }

        #boardView.orders-board-view .leads-board {
            grid-template-columns: minmax(0, 1fr);
            min-height: 280px;
        }
    }

    /* Board + table toolbars: stack on narrow screens (no horizontal page scroll) */
    @media (max-width: 900px) {
        #boardView.orders-board-view .filters-section {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        #boardView.orders-board-view .lead-filters-left {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            min-width: 0;
        }

        #boardView.orders-board-view .lead-filters-right {
            width: 100%;
            min-width: 0;
        }

        #boardView.orders-board-view .lead-filters-right .filter-select {
            width: 100%;
            min-width: 0;
        }

        #boardView.orders-board-view .lead-search-input {
            width: 100%;
            min-width: 0;
        }

        #boardView.orders-board-view .view-toggle-section {
            margin-left: 0;
            align-self: flex-end;
        }

        #tableView .filters-section .view-toggle-section {
            margin-left: 0;
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }
    }
</style>
@endpush

@section('content')
    <!-- Board View Content (Leads) -->
    <div id="boardView" class="view-content orders-board-view">
        <!-- Search / Controls -->
        <div class="filters-section">
            <div class="lead-filters-left">
                <input type="text" id="boardSearchInput" class="lead-search-input" placeholder="Search by job ID or order number">
                <button class="btn-secondary" id="boardFilterBtn" type="button">Filter</button>
            </div>
            <div class="lead-filters-right">
                <select class="filter-select" id="boardStatusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="artwork_needed">Artwork Needed</option>
                    <option value="in_production">In Production</option>
                    <option value="shipped">Shipped</option>
                    <option value="cancelled">Cancelled/Exception</option>
                </select>
            </div>

            <div class="view-toggle-section">
                <button class="view-toggle-btn active" id="boardViewBtnTop" data-view-toggle="board" onclick="switchView('board')">
                    <span class="view-toggle-icon-board" aria-hidden="true"></span>
                </button>
                <button class="view-toggle-btn" id="tableViewBtnTop" data-view-toggle="table" onclick="switchView('table')">
                    <span class="view-toggle-icon-table" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <div class="leads-board">
            <!-- Column: New (Pending) -->
            <div class="lead-column" data-type="New">
                <div class="lead-column-header">
                    <h3>NEW ({{ count($jobsByStatus['pending']) }})</h3>
                    <!-- <button class="btn-secondary" onclick="openModal(this)">+</button> -->
                </div>
                <div class="lead-column-body" data-status="pending">
                    @forelse($jobsByStatus['pending'] as $job)
                        <div class="lead-card job-card"
                             draggable="true"
                             data-job-id="{{ $job->id }}"
                             data-order-id="{{ $job->order_id }}"
                             data-current-status="pending"
                             data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}"
                             data-job-type="{{ strtolower($job->job_type ?? '') }}"
                             data-created-date="{{ $job->created_at->format('Y-m-d') }}"
                             onclick="navigateToOrder(event, {{ $job->order_id }})"
                             style="cursor: grab;">
                            <div class="lead-card-header">
                                <div class="lead-card-info">
                                    <div class="lead-avatar avatar-blue">{{ substr($job->shop->shop_domain ?? 'N/A', 0, 1) }}</div>
                                    <div>
                                        <div class="lead-name">Job #{{ $job->id }}</div>
                                        <div class="lead-company">{{ $job->shop->shop_domain ?? 'Unknown' }}</div>
                                    </div>
                                </div>
                                <div class="lead-time">{{ $job->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="lead-description">Order #{{ $job->order_id }} - {{ $job->job_type }}</div>
                            <div class="lead-tags">
                                <span class="status-badge status-pending">{{ strtoupper(str_replace('_', ' ', $job->status)) }}</span>
                                <span class="status-badge tag-muted">{{ $job->job_type }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="lead-empty-state">No new jobs</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: Artwork Needed -->
            <div class="lead-column" data-type="Artwork Needed">
                <div class="lead-column-header">
                    <h3>ARTWORK NEEDED ({{ count($jobsByStatus['artwork_needed']) }})</h3>
                    <!-- <button class="btn-secondary" onclick="openModal(this)">+</button> -->
                </div>
                <div class="lead-column-body" data-status="artwork_needed">
                    @forelse($jobsByStatus['artwork_needed'] as $job)
                        <div class="lead-card job-card"
                             draggable="true"
                             data-job-id="{{ $job->id }}"
                             data-order-id="{{ $job->order_id }}"
                             data-current-status="artwork_needed"
                             data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}"
                             data-job-type="{{ strtolower($job->job_type ?? '') }}"
                             data-created-date="{{ $job->created_at->format('Y-m-d') }}"
                             onclick="navigateToOrder(event, {{ $job->order_id }})"
                             style="cursor: grab;">
                            <div class="lead-card-header">
                                <div class="lead-card-info">
                                    <div class="lead-avatar avatar-orange">{{ substr($job->shop->shop_domain ?? 'N/A', 0, 1) }}</div>
                                    <div>
                                        <div class="lead-name">Job #{{ $job->id }}</div>
                                        <div class="lead-company">{{ $job->shop->shop_domain ?? 'Unknown' }}</div>
                                    </div>
                                </div>
                                <div class="lead-time">{{ $job->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="lead-description">Order #{{ $job->order_id }} - {{ $job->job_type }}</div>
                            <div class="lead-tags">
                                <span class="status-badge status-artwork-needed">{{ strtoupper(str_replace('_', ' ', $job->status)) }}</span>
                                <span class="status-badge tag-orange">{{ $job->job_type }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="lead-empty-state">No artwork needed</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: In Production -->
            <div class="lead-column" data-type="In Production">
                <div class="lead-column-header">
                    <h3>IN PRODUCTION ({{ count($jobsByStatus['in_production']) }})</h3>
                    <!-- <button class="btn-secondary" onclick="openModal(this)">+</button> -->
                </div>
                <div class="lead-column-body" data-status="in_production">
                    @forelse($jobsByStatus['in_production'] as $job)
                        <div class="lead-card job-card"
                             draggable="true"
                             data-job-id="{{ $job->id }}"
                             data-order-id="{{ $job->order_id }}"
                             data-current-status="in_production"
                             data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}"
                             data-job-type="{{ strtolower($job->job_type ?? '') }}"
                             data-created-date="{{ $job->created_at->format('Y-m-d') }}"
                             onclick="navigateToOrder(event, {{ $job->order_id }})"
                             style="cursor: grab;">
                            <div class="lead-card-header">
                                <div class="lead-card-info">
                                    <div class="lead-avatar avatar-purple">{{ substr($job->shop->shop_domain ?? 'N/A', 0, 1) }}</div>
                                    <div>
                                        <div class="lead-name">Job #{{ $job->id }}</div>
                                        <div class="lead-company">{{ $job->shop->shop_domain ?? 'Unknown' }}</div>
                                    </div>
                                </div>
                                <div class="lead-time">{{ $job->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="lead-description">Order #{{ $job->order_id }} - {{ $job->job_type }}</div>
                            <div class="lead-tags">
                                <span class="status-badge status-in-production">{{ strtoupper(str_replace('_', ' ', $job->status)) }}</span>
                                <span class="status-badge tag-purple">{{ $job->job_type }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="lead-empty-state">No jobs in production</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: Shipped -->
            <div class="lead-column" data-type="Shipped">
                <div class="lead-column-header">
                    <h3>SHIPPED ({{ count($jobsByStatus['shipped']) }})</h3>
                    <!-- <button class="btn-secondary" onclick="openModal(this)">+</button> -->
                </div>
                <div class="lead-column-body" data-status="shipped">
                    @forelse($jobsByStatus['shipped'] as $job)
                        <div class="lead-card job-card"
                             draggable="true"
                             data-job-id="{{ $job->id }}"
                             data-order-id="{{ $job->order_id }}"
                             data-current-status="shipped"
                             data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}"
                             data-job-type="{{ strtolower($job->job_type ?? '') }}"
                             data-created-date="{{ $job->created_at->format('Y-m-d') }}"
                             onclick="navigateToOrder(event, {{ $job->order_id }})"
                             style="cursor: grab;">
                            <div class="lead-card-header">
                                <div class="lead-card-info">
                                    <div class="lead-avatar avatar-green">{{ substr($job->shop->shop_domain ?? 'N/A', 0, 1) }}</div>
                                    <div>
                                        <div class="lead-name">Job #{{ $job->id }}</div>
                                        <div class="lead-company">{{ $job->shop->shop_domain ?? 'Unknown' }}</div>
                                    </div>
                                </div>
                                <div class="lead-time">{{ $job->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="lead-description">Order #{{ $job->order_id }} - {{ $job->job_type }}</div>
                            <div class="lead-tags">
                                <span class="status-badge status-shipped">{{ strtoupper(str_replace('_', ' ', $job->status)) }}</span>
                                <span class="status-badge tag-green">{{ $job->job_type }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="lead-empty-state">No shipped jobs</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: Cancelled/Exception -->
            <div class="lead-column" data-type="Cancelled">
                <div class="lead-column-header">
                    <h3>CANCELLED/EXCEPTION ({{ count($jobsByStatus['cancelled']) }})</h3>
                    <!-- <button class="btn-secondary" onclick="openModal(this)">+</button> -->
                </div>
                <div class="lead-column-body" data-status="cancelled">
                    @forelse($jobsByStatus['cancelled'] as $job)
                        <div class="lead-card job-card"
                             draggable="true"
                             data-job-id="{{ $job->id }}"
                             data-order-id="{{ $job->order_id }}"
                             data-current-status="cancelled"
                             data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}"
                             data-job-type="{{ strtolower($job->job_type ?? '') }}"
                             data-created-date="{{ $job->created_at->format('Y-m-d') }}"
                             onclick="navigateToOrder(event, {{ $job->order_id }})"
                             style="cursor: grab;">
                            <div class="lead-card-header">
                                <div class="lead-card-info">
                                    <div class="lead-avatar avatar-red">{{ substr($job->shop->shop_domain ?? 'N/A', 0, 1) }}</div>
                                    <div>
                                        <div class="lead-name">Job #{{ $job->id }}</div>
                                        <div class="lead-company">{{ $job->shop->shop_domain ?? 'Unknown' }}</div>
                                    </div>
                                </div>
                                <div class="lead-time">{{ $job->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="lead-description">Order #{{ $job->order_id }} - {{ $job->job_type }}</div>
                            <div class="lead-tags">
                                <span class="status-badge status-cancelled">{{ strtoupper(str_replace('_', ' ', $job->status)) }}</span>
                                <span class="status-badge tag-red">{{ $job->job_type }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="lead-empty-state">No cancelled/exception jobs</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Table View Content (Orders) -->
    <div id="tableView" class="view-content" style="display: none;">
        <div class="filters-section">
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending">NEW</option>
                    <option value="artwork_needed">ARTWORK NEEDED</option>
                    <option value="in_production">IN PRODUCTION</option>
                    <option value="shipped">SHIPPED</option>
                    <option value="cancelled">CANCELLED/EXCEPTION</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Store</label>
                <select id="filterStore" class="filter-select">
                    <option value="">All Stores</option>
                    @foreach($stores as $store)
                        <option value="{{ $store }}">{{ $store }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label>Date</label>
                <input type="date" id="filterDate" class="filter-select">
            </div>

            <button class="btn-secondary lead-clear-filters" onclick="clearFilters()">Clear Filters</button>

            <div class="view-toggle-section">
                <button class="view-toggle-btn" id="boardViewBtnBottom" data-view-toggle="board" onclick="switchView('board')">
                    <span class="view-toggle-icon-board" aria-hidden="true"></span>
                </button>
                <button class="view-toggle-btn active" id="tableViewBtnBottom" data-view-toggle="table" onclick="switchView('table')">
                    <span class="view-toggle-icon-table" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Store Name</th>
                        <th>Order ID</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="jobsTableBody">
                    @forelse($allJobs as $job)
                        @php
                            $rawJobStatus = strtolower((string) ($job->status ?? 'pending'));
                            $jobStatus = in_array($rawJobStatus, ['failed', 'exception', 'cancelled'], true)
                                ? 'cancelled'
                                : ((str_contains($rawJobStatus, 'billing') || str_contains($rawJobStatus, 'issue') || in_array($rawJobStatus, ['payment_pending', 'payment_required'], true))
                                    ? 'pending'
                                    : $rawJobStatus);

                            $jobStatusClass = str_replace('_', '-', $jobStatus);

                            $jobStatusLabel = match ($jobStatus) {
                                'pending' => 'NEW',
                                'artwork_needed' => 'ARTWORK NEEDED',
                                'in_production' => 'IN PRODUCTION',
                                'shipped' => 'SHIPPED',
                                'cancelled' => 'CANCELLED/EXCEPTION',
                                default => strtoupper(str_replace('_', ' ', $jobStatus)),
                            };
                        @endphp
                        <tr
                            data-job-id="{{ $job->id }}"
                            data-order-id="{{ $job->order_id }}"
                            data-store="{{ strtolower($job->shop->shop_domain ?? 'n/a') }}"
                            data-status="{{ $jobStatus }}"
                            data-created-date="{{ $job->created_at->format('Y-m-d') }}"
                        >
                            <td>#{{ $job->id }}</td>
                            <td>{{ $job->shop->shop_domain ?? 'N/A' }}</td>
                            <td>#{{ $job->order_id ?? 'N/A' }}</td>
                            <td><span class="status-badge status-{{ $jobStatusClass }}">{{ $jobStatusLabel }}</span></td>
                            <td>{{ $job->created_at->format('Y-m-d H:i') }}</td>
                            <td><a href="{{ route('crm.order-detail', $job->order_id) }}" class="btn-link">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: #999;">No jobs found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            @if($allJobs->onFirstPage())
                <button class="btn-pagination" disabled>Previous</button>
            @else
                <a href="{{ $allJobs->previousPageUrl() }}" class="btn-pagination">Previous</a>
            @endif

            <span class="page-info">Page {{ $allJobs->currentPage() }} of {{ $allJobs->lastPage() }}</span>

            @if($allJobs->hasMorePages())
                <a href="{{ $allJobs->nextPageUrl() }}" class="btn-pagination">Next</a>
            @else
                <button class="btn-pagination" disabled>Next</button>
            @endif
        </div>
    </div>

    <!-- Lead Modal -->
    <div class="modal-overlay" id="addLeadModal">
        <div class="modal">
            <h3 id="modalTitle">Add New Job</h3>

            <div class="form-group">
                <label>Job Type</label>
                <input type="text" id="jobType" placeholder="DTF or Apparel POD">
            </div>

            <div class="form-group">
                <label>Order ID</label>
                <input type="text" id="orderId" placeholder="Order ID">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select id="jobStatus">
                    <option value="pending">Pending</option>
                    <option value="in_production">In Production</option>
                    <option value="completed">Completed</option>
                    <option value="shipped">Shipped</option>
                </select>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea id="jobNotes" placeholder="Job notes"></textarea>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn-primary" onclick="addJob()">Add Job</button>
            </div>
        </div>
    </div>

    <script>
        let draggedJobId = null;
        let draggedOrderCard = null;

        function normalizeFilterStatus(status) {
            if (!status) return '';
            if (status === 'new') return 'pending';
            if (status.includes('billing') || status.includes('issue')) return 'pending';
            if (status === 'payment_pending' || status === 'payment_required') return 'pending';
            if (status === 'failed' || status === 'exception') return 'cancelled';
            return status;
        }

        function normalizeBoardStatus(status) {
            if (status === 'failed' || status === 'exception') {
                return 'cancelled';
            }
            return status;
        }

        function switchView(viewType) {
            const boardView = document.getElementById('boardView');
            const tableView = document.getElementById('tableView');
            if (!boardView || !tableView) return;

            const boardButtons = document.querySelectorAll('[data-view-toggle="board"]');
            const tableButtons = document.querySelectorAll('[data-view-toggle="table"]');

            if (viewType === 'table') {
                boardView.style.display = 'none';
                tableView.style.display = 'block';
                boardButtons.forEach(btn => btn.classList.remove('active'));
                tableButtons.forEach(btn => btn.classList.add('active'));
            } else {
                boardView.style.display = 'block';
                tableView.style.display = 'none';
                tableButtons.forEach(btn => btn.classList.remove('active'));
                boardButtons.forEach(btn => btn.classList.add('active'));
            }
        }

        function navigateToOrder(event, orderId) {
            if (event.target.closest('[draggable]') === event.currentTarget) {
                window.location.href = `/crm/order/detail/${orderId}`;
            }
        }

        function setupDragAndDrop() {
            const cards = document.querySelectorAll('.job-card');
            const columns = document.querySelectorAll('.lead-column-body');

            cards.forEach(card => {
                card.addEventListener('dragstart', handleDragStart);
                card.addEventListener('dragend', handleDragEnd);
            });

            columns.forEach(column => {
                column.addEventListener('dragover', handleDragOver);
                column.addEventListener('drop', handleDrop);
                column.addEventListener('dragleave', handleDragLeave);
            });
        }

        function handleDragStart(e) {
            draggedOrderCard = this;
            draggedJobId = this.dataset.jobId;
            this.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleDragEnd() {
            if (draggedOrderCard) {
                draggedOrderCard.style.opacity = '1';
            }
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.style.backgroundColor = '#f0f0f0';
        }

        function handleDragLeave() {
            this.style.backgroundColor = '';
        }

        function handleDrop(e) {
            e.preventDefault();
            this.style.backgroundColor = '';

            if (!draggedJobId || !draggedOrderCard) return;

            const newStatus = this.dataset.status;
            const oldStatus = draggedOrderCard.dataset.currentStatus;

            if (newStatus === oldStatus) {
                draggedOrderCard.style.opacity = '1';
                return;
            }

            updateJobStatusAjax(draggedJobId, newStatus, draggedOrderCard);
        }

        function updateJobStatusAjax(jobId, newStatus, cardElement) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            fetch(`/api/v1/update-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id: jobId,
                    type: 'job',
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateSingleKanbanCard(jobId, newStatus);
                    updateTableJobStatus(jobId, newStatus);
                    console.log('Job status updated successfully');
                } else {
                    window.crmAlert('Error updating status', 'error');
                    cardElement.style.opacity = '1';
                }

                draggedOrderCard = null;
                draggedJobId = null;
            })
            .catch(error => {
                console.error('Error:', error);
                window.crmAlert('Error updating status', 'error');
                cardElement.style.opacity = '1';
                draggedOrderCard = null;
                draggedJobId = null;
            });
        }

        function updateSingleKanbanCard(jobId, newStatus) {
            const normalizedStatus = normalizeBoardStatus(newStatus);
            const targetColumn = document.querySelector(`.lead-column-body[data-status="${normalizedStatus}"]`);
            const card = document.querySelector(`.job-card[data-job-id="${jobId}"]`);

            if (!targetColumn || !card) return;

            targetColumn.appendChild(card);
            card.dataset.currentStatus = normalizedStatus;
            updateCardStatusBadge(card, normalizedStatus);
            card.style.opacity = '1';

            applyBoardFilters();
            refreshColumnEmptyStates();
            refreshColumnCounts();
        }

        function updateCardStatusBadge(card, status) {
            const statusBadge = card.querySelector('.lead-tags .status-badge:first-child');
            if (!statusBadge) return;

            const displayStatus = getStatusDisplayName(status);
            const className = getStatusClassName(status);

            statusBadge.textContent = displayStatus;
            statusBadge.className = `status-badge status-${className}`;
        }

        function updateTableJobStatus(jobId, newStatus) {
            const row = document.querySelector(`#jobsTableBody tr[data-job-id="${jobId}"]`);
            if (!row) return;

            const statusCell = row.querySelector('td:nth-child(5)');
            if (statusCell) {
                const displayStatus = getStatusDisplayName(newStatus);
                const className = getStatusClassName(newStatus);
                statusCell.innerHTML = `<span class="status-badge status-${className}">${displayStatus}</span>`;
            }

            row.dataset.status = normalizeFilterStatus(newStatus);
        }

        function refreshColumnCounts() {
            const columns = document.querySelectorAll('.lead-column');
            columns.forEach(column => {
                const header = column.querySelector('.lead-column-header h3');
                const body = column.querySelector('.lead-column-body');
                if (!header || !body) return;

                const totalCards = Array.from(body.querySelectorAll('.job-card'))
                    .filter(card => card.style.display !== 'none').length;

                const title = header.textContent.split('(')[0].trim();
                header.textContent = `${title} (${totalCards})`;
            });
        }

        function refreshColumnEmptyStates() {
            const columns = document.querySelectorAll('.lead-column-body');
            columns.forEach(column => {
                const cards = Array.from(column.querySelectorAll('.job-card'))
                    .filter(card => card.style.display !== 'none');

                const emptyMessage = column.querySelector('p');

                if (cards.length > 0 && emptyMessage) {
                    emptyMessage.remove();
                }

                if (cards.length === 0 && !emptyMessage) {
                    const message = document.createElement('p');
                    message.className = 'lead-empty-state';
                    message.textContent = getEmptyColumnMessage(column.dataset.status);
                    column.appendChild(message);
                }
            });
        }

        function getEmptyColumnMessage(status) {
            const messages = {
                pending: 'No new jobs',
                artwork_needed: 'No artwork needed',
                in_production: 'No jobs in production',
                shipped: 'No shipped jobs',
                cancelled: 'No cancelled/exception jobs'
            };

            return messages[status] || 'No jobs';
        }

        function applyBoardFilters() {
            const searchInput = document.getElementById('boardSearchInput');
            const statusSelect = document.getElementById('boardStatusFilter');
            const searchTerm = (searchInput?.value || '').trim().toLowerCase();
            const selectedStatus = normalizeFilterStatus(statusSelect?.value || '');

            const cards = document.querySelectorAll('.job-card');
            cards.forEach(card => {
                const jobId = (card.dataset.jobId || '').toLowerCase();
                const orderId = (card.dataset.orderId || '').toLowerCase();
                const cardStatus = normalizeFilterStatus(card.dataset.currentStatus || '');

                const matchesSearch = !searchTerm || jobId.includes(searchTerm) || orderId.includes(searchTerm);
                const matchesStatus = !selectedStatus || cardStatus === selectedStatus;

                card.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });

            refreshColumnEmptyStates();
            refreshColumnCounts();
        }

        function applyTableFilters() {
            const statusValue = normalizeFilterStatus(document.getElementById('filterStatus')?.value || '');
            const storeValue = (document.getElementById('filterStore')?.value || '').trim().toLowerCase();
            const dateValue = (document.getElementById('filterDate')?.value || '').trim();

            const rows = document.querySelectorAll('#jobsTableBody tr[data-order-id]');
            rows.forEach(row => {
                const rowStatus = normalizeFilterStatus(row.dataset.status || '');
                const rowStore = (row.dataset.store || '').trim().toLowerCase();
                const rowDate = (row.dataset.createdDate || '').trim();

                const matchesStatus = !statusValue || rowStatus === statusValue;
                const matchesStore = !storeValue || rowStore === storeValue;
                const matchesDate = !dateValue || rowDate === dateValue;

                row.style.display = (matchesStatus && matchesStore && matchesDate) ? '' : 'none';
            });
        }

        function setupFilters() {
            const boardFilterBtn = document.getElementById('boardFilterBtn');
            const boardSearchInput = document.getElementById('boardSearchInput');
            const boardStatusFilter = document.getElementById('boardStatusFilter');
            const filterStatus = document.getElementById('filterStatus');
            const filterStore = document.getElementById('filterStore');
            const filterDate = document.getElementById('filterDate');

            if (boardFilterBtn) boardFilterBtn.addEventListener('click', applyBoardFilters);
            if (boardStatusFilter) boardStatusFilter.addEventListener('change', applyBoardFilters);

            if (boardSearchInput) {
                boardSearchInput.addEventListener('keyup', (e) => {
                    if (e.key === 'Enter') {
                        applyBoardFilters();
                    }
                });
                boardSearchInput.addEventListener('input', applyBoardFilters);
            }

            if (filterStatus) filterStatus.addEventListener('change', applyTableFilters);
            if (filterStore) filterStore.addEventListener('change', applyTableFilters);
            if (filterDate) filterDate.addEventListener('change', applyTableFilters);
        }

        function clearFilters() {
            const filterStatus = document.getElementById('filterStatus');
            const filterStore = document.getElementById('filterStore');
            const filterDate = document.getElementById('filterDate');
            const boardSearchInput = document.getElementById('boardSearchInput');
            const boardStatusFilter = document.getElementById('boardStatusFilter');

            if (filterStatus) filterStatus.value = '';
            if (filterStore) filterStore.value = '';
            if (filterDate) filterDate.value = '';
            if (boardSearchInput) boardSearchInput.value = '';
            if (boardStatusFilter) boardStatusFilter.value = '';

            applyTableFilters();
            applyBoardFilters();
        }

        function getStatusDisplayName(status) {
            const statusMap = {
                pending: 'NEW',
                artwork_needed: 'ARTWORK NEEDED',
                in_production: 'IN PRODUCTION',
                shipped: 'SHIPPED',
                cancelled: 'CANCELLED/EXCEPTION',
                failed: 'CANCELLED/EXCEPTION',
                exception: 'CANCELLED/EXCEPTION'
            };

            return statusMap[status] || status.toUpperCase().replace(/_/g, ' ');
        }

        function getStatusClassName(status) {
            const classMap = {
                pending: 'pending',
                artwork_needed: 'artwork-needed',
                in_production: 'in-production',
                shipped: 'shipped',
                cancelled: 'cancelled',
                failed: 'cancelled',
                exception: 'cancelled'
            };

            return classMap[status] || status.replace(/_/g, '-');
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupDragAndDrop();
            setupFilters();
        });
    </script>
@endsection