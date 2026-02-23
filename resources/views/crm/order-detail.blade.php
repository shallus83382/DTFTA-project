@extends('layouts.app')

@section('title', 'DTFTA CRM - Order Detail')
@section('page-title', 'Order Details')
@section('content')

    <div class="ld-page">
        <!-- RIGHT PANEL -->
        <div class="ld-header-panel">
            <div class="ld-right-header">
                @if (isset($order))
                    <div class="job-info-card ld-card ld-right-header-block">
                        <div class="job-info-row">
                            <span>Order ID</span>
                            <b>#{{ $order->id ?? 'N/A' }}</b>
                        </div>
                        <div class="job-info-row">
                            <span>Store Name</span>
                            <b>{{ $order->shop->shop_domain ?? 'N/A' }}</b>
                        </div>
                        <div class="job-info-row">
                            <span>Customer</span>
                            <b>{{ $order->customer_name ?? 'N/A' }}</b>
                        </div>
                        <div class="job-info-row">
                            <span>Customer Email</span>
                            <b>{{ $order->customer_email ?? 'N/A' }}</b>
                        </div>
                        <div class="job-info-row status-row">
                            <span>Current Status</span>
                            <span id="statusBadge"
                                class="status-badge">{{ strtoupper(str_replace('_', ' ', $order->fulfillment_status)) }}</span>
                        </div>
                    </div>
                @endif
                <div class="ld-section ld-right-header-block">
                    <div class="ld-card">
                        <h3>Print Details</h3>
                        <div class="ld-info">
                            <div><span>Status</span><b>{{ $order->fulfillment_status ?? 'pending' }}</b></div>
                            <div><span>Created
                                    At</span><b>{{ $order->created_at_shopify?->format('Y-m-d H:i') ?? 'N/A' }}</b>
                            </div>
                            <div><span>Updated
                                    At</span><b>{{ $order->updated_at_shopify?->format('Y-m-d H:i') ?? 'N/A' }}</b>
                            </div>
                            @if (isset($job) && $job->payload)
                                <div class="artwork-preview">
                                    <span>Additional Info</span>
                                    <p>{{ json_encode($job->payload) }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
            <h3>Update Order Status</h3>

            <div class="ld-stepper" id="ldStepper">
                @php
                    // Always use order's fulfillment_status
                    $currStatus = isset($order) ? $order->fulfillment_status : null;
                @endphp
                <div class="step step-new {{ $currStatus === 'pending' ? 'active' : '' }}"
                    onclick="updateJobStatus('pending', this)" data-status="pending">NEW</div>
                <div class="step step-artwork {{ $currStatus === 'artwork_needed' ? 'active' : '' }}"
                    onclick="updateJobStatus('artwork_needed', this)" data-status="artwork_needed">ARTWORK NEEDED<span
                        class="asterisk">*</span></div>
                <div class="step step-production {{ $currStatus === 'in_production' ? 'active' : '' }}"
                    onclick="updateJobStatus('in_production', this)" data-status="in_production">IN PRODUCTION</div>
                <div class="step step-shipped {{ $currStatus === 'shipped' ? 'active' : '' }}"
                    onclick="updateJobStatus('shipped', this)" data-status="shipped">SHIPPED</div>
                <div class="step step-cancelled {{ in_array($currStatus, ['cancelled', 'failed', 'exception']) ? 'active' : '' }}"
                    onclick="updateJobStatus('cancelled', this)" data-status="cancelled">CANCELLED/EXCEPTION</div>
            </div>

            <style>
                .ld-stepper .step {
                    cursor: pointer;
                    transition: all 0.3s ease;
                    padding: 10px 15px;
                    border-radius: 4px;
                    background-color: #f0f0f0;
                    color: #333;
                    font-weight: 500;
                }

                .ld-stepper .step-new {
                    border-left: 4px solid #3B82F6;
                }

                .ld-stepper .step-artwork {
                    border-left: 4px solid #F59E0B;
                }

                .ld-stepper .step-production {
                    border-left: 4px solid #8B5CF6;
                }

                .ld-stepper .step-shipped {
                    border-left: 4px solid #10B981;
                }

                .ld-stepper .step-cancelled {
                    border-left: 4px solid #EF4444;
                }

                .ld-stepper .step.active {
                    font-weight: 700;
                    transform: scale(1.05);
                }

                .ld-stepper .step-new.active {
                    background-color: #DBEAFE;
                    color: #1E40AF;
                }

                .ld-stepper .step-artwork.active {
                    background-color: #FEF3C7;
                    color: #92400E;
                }

                .ld-stepper .step-production.active {
                    background-color: #EDE9FE;
                    color: #5B21B6;
                }

                .ld-stepper .step-shipped.active {
                    background-color: #DCFCE7;
                    color: #166534;
                }

                .ld-stepper .step-cancelled.active {
                    background-color: #FEE2E2;
                    color: #991B1B;
                }

                .ld-stepper .step:hover:not(.active) {
                    background-color: #e0e0e0;
                }

                .log-status-highlight {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.3px;
                    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
                    color: #1e3a8a;
                    border: 1px solid #93c5fd;
                    margin: 0 2px;
                    text-transform: uppercase;
                }

                .log-status-info {
                    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
                    color: #1e3a8a;
                    border-color: #93c5fd;
                }

                .log-status-warning {
                    background: linear-gradient(135deg, #fef3c7, #fde68a);
                    color: #92400e;
                    border-color: #f59e0b;
                }

                .log-status-success {
                    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
                    color: #166534;
                    border-color: #34d399;
                }

                .log-status-danger {
                    background: linear-gradient(135deg, #fee2e2, #fecaca);
                    color: #991b1b;
                    border-color: #f87171;
                }
            </style>


            <div class="ld-tabs">
                <div class="tab active" onclick="switchTab('all')">All</div>

            </div>

            <div class="ld-content" id="tabContent">
                @forelse($activityLog as $activity)
                    @php
                        $actionText = (string) ($activity->action ?? '');
                        $actionHtml = e($actionText);
                        if (preg_match('/(?:updated status to|status updated to)\s+([a-z_]+)/i', $actionText, $matches)) {
                            $statusToken = $matches[1];
                            $statusLabel = strtoupper(str_replace('_', ' ', $statusToken));
                            $statusClass = match (strtolower($statusToken)) {
                                'artwork_needed', 'pending', 'new', 'processing' => 'log-status-warning',
                                'shipped', 'fulfilled', 'delivered' => 'log-status-success',
                                'cancelled', 'failed', 'exception' => 'log-status-danger',
                                default => 'log-status-info',
                            };
                            $actionHtml = preg_replace(
                                '/\b' . preg_quote($statusToken, '/') . '\b/i',
                                '<span class="log-status-highlight ' . $statusClass . '">' . e($statusLabel) . '</span>',
                                e($actionText),
                                1,
                            );
                        }
                    @endphp
                    <div class="ld-timeline-item">
                        <div class="ld-icon" aria-hidden="true"></div>
                        <div>
                            <b>{!! $actionHtml !!}: {{ $activity->model_type }}</b>
                            <p>{{ $activity->created_at->format('d M Y, g:i A') }}@if ($activity->user)
                                    , By {{ $activity->user->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                    @empty
                    <div class="ld-timeline-item">
                        <div class="ld-icon" aria-hidden="true"></div>
                        <div>
                            <b>No activity recorded</b>
                            <p>This order/job has no activity log yet</p>
                        </div>
                    </div>
                    @endforelse
                </div>
                <div id="activityPagination" class="pagination" style="margin-top:12px;">
                    @if($activityLog->onFirstPage())
                        <button class="btn-pagination" disabled>Previous</button>
                    @else
                        <a href="{{ $activityLog->previousPageUrl() }}" class="btn-pagination">Previous</a>
                    @endif
                    <span class="page-info">Page {{ $activityLog->currentPage() }} of {{ $activityLog->lastPage() }}</span>
                    @if($activityLog->hasMorePages())
                        <a href="{{ $activityLog->nextPageUrl() }}" class="btn-pagination">Next</a>
                    @else
                        <button class="btn-pagination" disabled>Next</button>
                    @endif
                </div>

            </div>
        </div>
        <script src="public/assets/js/script.js"></script>
        <script>
            function loadCurrentOrderStatus() {
                const orderId = {{ $order->id ?? 'null' }};
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                    document.querySelector('input[name="_token"]')?.value || '';

                if (!orderId) return;

                fetch(`/api/v1/get-order-status/${orderId}`, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.status) {
                            updateUIWithStatus(data.status);
                        }
                    })
                    .catch(error => console.error('Error loading status:', error));
            }

            function updateUIWithStatus(status) {
                document.querySelectorAll('.ld-stepper .step').forEach(step => {
                    step.classList.remove('active');
                });

                const activeStep = document.querySelector(`.ld-stepper .step[data-status="${status}"]`);
                if (activeStep) {
                    activeStep.classList.add('active');
                }

                const statusBadge = document.getElementById('statusBadge');
                if (statusBadge) {
                    const displayText = getStatusDisplayName(status);
                    statusBadge.textContent = displayText;
                    statusBadge.className = 'status-badge status-' + getStatusClassName(status);
                }
            }

            function getStatusDisplayName(status) {
                const statusMap = {
                    'pending': 'NEW',
                    'artwork_needed': 'ARTWORK NEEDED',
                    'in_production': 'IN PRODUCTION',
                    'shipped': 'SHIPPED',
                    'cancelled': 'CANCELLED/EXCEPTION',
                    'failed': 'CANCELLED/EXCEPTION',
                    'exception': 'CANCELLED/EXCEPTION'
                };
                return statusMap[status] || status.toUpperCase().replace(/_/g, ' ');
            }

            function getStatusClassName(status) {
                const classMap = {
                    'pending': 'pending',
                    'artwork_needed': 'artwork-needed',
                    'in_production': 'in-production',
                    'shipped': 'shipped',
                    'cancelled': 'cancelled',
                    'failed': 'cancelled',
                    'exception': 'cancelled'
                };
                return classMap[status] || status.replace(/_/g, '-');
            }

            document.addEventListener('DOMContentLoaded', loadCurrentOrderStatus);

            function updateJobStatus(newStatus, element) {
                const orderId = {{ $order->id ?? 'null' }};

                if (!orderId) return;

                document.querySelectorAll('.ld-stepper .step').forEach(step => {
                    step.classList.remove('active');
                });

                element.classList.add('active');

                const id = orderId;
                const type = 'order';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                    document.querySelector('input[name="_token"]')?.value || '';

                fetch(`/api/v1/update-status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            id: id,
                            type: type,
                            status: newStatus
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Status updated successfully');

                            document.querySelectorAll('.ld-stepper .step').forEach(step => {
                                step.classList.remove('active');
                            });

                            const activeStep = document.querySelector(`.ld-stepper .step[data-status="${newStatus}"]`);
                            if (activeStep) {
                                activeStep.classList.add('active');
                            }

                            const statusBadge = document.getElementById('statusBadge');
                            if (statusBadge) {
                                const displayText = getStatusDisplayName(newStatus);
                                statusBadge.textContent = displayText;
                                statusBadge.className = 'status-badge status-' + getStatusClassName(newStatus);
                            }

                            refreshActivityLog(id, type);

                            if (typeof showNotification === 'function') {
                                showNotification('Status updated successfully', 'success');
                            }
                        } else {
                            window.crmAlert('Error updating status: ' + (data.message || 'Unknown error'), 'error');
                            document.querySelectorAll('.ld-stepper .step').forEach(step => {
                                step.classList.remove('active');
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        window.crmAlert('Error updating status', 'error');
                        document.querySelectorAll('.ld-stepper .step').forEach(step => {
                            step.classList.remove('active');
                        });
                    });
            }
            function refreshActivityLog(id, type, page = 1) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                fetch(`/api/v1/get-activity-log`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            id: id,
                            type: type,
                            page: page,
                            per_page: 10
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateActivityContent(data.activities, data.pagination, id, type);
                        }
                    })
                    .catch(error => {
                        console.error('Error refreshing activity log:', error);
                    });
            }

            function updateActivityContent(activities, pagination, id, type) {
                const tabContent = document.getElementById('tabContent');
                const paginationNode = document.getElementById('activityPagination');

                if (!activities || activities.length === 0) {
                    tabContent.innerHTML = `
                    <div class="ld-timeline-item">
                        <div class="ld-icon" aria-hidden="true"></div>
                        <div>
                            <b>No activity recorded</b>
                            <p>This order/job has no activity log yet</p>
                        </div>
                    </div>
                `;
                    if (paginationNode) paginationNode.innerHTML = '';
                    return;
                }

                let html = '';
                activities.forEach(activity => {
                    const date = new Date(activity.created_at);
                    const formattedDate = date.toLocaleDateString('en-US', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    }) + ', ' + date.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    const userName = activity.user ? activity.user.name : 'System';
                    const actionHtml = formatActivityAction(activity.action || '');
                    const modelType = escapeHtml(activity.model_type || '');

                    html += `
                    <div class="ld-timeline-item">
                        <div class="ld-icon" aria-hidden="true"></div>
                        <div>
                            <b>${actionHtml}: ${modelType}</b>
                            <p>${formattedDate}, By ${userName}</p>
                        </div>
                    </div>
                `;
                });

                tabContent.innerHTML = html;
                if (!paginationNode) return;
                if (!pagination || (pagination.last_page || 1) <= 1) {
                    paginationNode.innerHTML = '';
                    return;
                }

                const current = pagination.current_page || 1;
                const last = pagination.last_page || 1;
                const prevDisabled = current <= 1 ? 'disabled' : '';
                const nextDisabled = current >= last ? 'disabled' : '';

                paginationNode.innerHTML = `
                    <button class="btn-pagination" ${prevDisabled} onclick="refreshActivityLog(${id}, '${type}', ${current - 1})">Previous</button>
                    <span class="page-info">Page ${current} of ${last}</span>
                    <button class="btn-pagination" ${nextDisabled} onclick="refreshActivityLog(${id}, '${type}', ${current + 1})">Next</button>
                `;
            }

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value ?? '';
                return div.innerHTML;
            }

            function formatActivityAction(action) {
                const safeAction = escapeHtml(action || '');
                const statusMatch = (action || '').match(/(?:updated status to|status updated to)\s+([a-z_]+)/i);
                if (!statusMatch || !statusMatch[1]) return safeAction;

                const statusToken = statusMatch[1];
                const statusLabel = escapeHtml(statusToken.replace(/_/g, ' ').toUpperCase());
                const tokenRegex = new RegExp(`\\b${statusToken}\\b`, 'i');
                const statusClass = getLogStatusClass(statusToken);

                return safeAction.replace(
                    tokenRegex,
                    `<span class="log-status-highlight ${statusClass}">${statusLabel}</span>`
                );
            }

            function getLogStatusClass(statusToken) {
                const key = (statusToken || '').toLowerCase();
                if (['artwork_needed', 'pending', 'new', 'processing'].includes(key)) return 'log-status-warning';
                if (['shipped', 'fulfilled', 'delivered'].includes(key)) return 'log-status-success';
                if (['cancelled', 'failed', 'exception'].includes(key)) return 'log-status-danger';
                return 'log-status-info';
            }
            function switchTab(tabName) {
                document.querySelectorAll('.ld-tabs .tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                event.target.classList.add('active');
                const content = document.getElementById('tabContent');
                if (tabName === 'all') {
                    content.style.display = 'block';
                } else if (tabName === 'activity') {
                    content.style.display = 'block';
                }
            }
        </script>
    @endsection
