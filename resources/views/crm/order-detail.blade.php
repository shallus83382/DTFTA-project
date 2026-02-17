@extends('layouts.app')

@section('title', 'DTFTA CRM - Order Detail')
@section('page-title', 'Order Details')
@section('content')

            <div class="ld-page">

                <!-- LEFT PANEL -->
                <div class="ld-left-panel">
                    <a href="{{ route('crm.orders') }}" class="btn-secondary">← Back to Orders</a>
                    <div class="ld-breadcrumb">
                        Dashboard / Orders / #{{ $order->id ?? 'N/A' }}
                    </div>

                    <h2 class="ld-title">
                        Order: {{ $order->order_number ?? 'N/A' }}
                    </h2>

                  
                    <div class="ld-section">
                        <div class="ld-card">
                            <h3>Order Info</h3>
                            <div class="ld-info">
                                <div><span>Store Name</span><b>{{ $order->shop->shop_domain ?? 'N/A' }}</b></div>
                                <div><span>Shopify Order ID</span><b>#{{ $order->shopify_order_id ?? 'N/A' }}</b></div>
                                <div><span>Customer Email</span><b>{{ $order->customer_email ?? 'N/A' }}</b></div>
                                <div><span>Total Price</span><b>{{ $order->currency ?? 'USD' }} {{ $order->total_price ?? '0' }}</b></div>
                                <div><span>Fulfillment Status</span><b>{{ $order->fulfillment_status ?? 'pending' }}</b></div>
                            </div>
                        </div>
                    </div>

                    @if(isset($orderItems) && count($orderItems) > 0)
                    <div class="ld-section">
                        <div class="ld-card">
                            <h3>Order Items</h3>
                            <div class="ld-info">
                                @foreach($orderItems as $item)
                                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                    <div><span>Product</span><b>{{ $item->title ?? 'N/A' }}</b></div>
                                    <div><span>Variant</span><b>{{ $item->variant_title ?? '-' }}</b></div>
                                    <div><span>SKU</span><b>{{ $item->sku ?? '-' }}</b></div>
                                    <div><span>Quantity</span><b>{{ $item->quantity ?? '0' }}</b></div>
                                    <div><span>Price</span><b>${{ $item->price ?? '0' }}</b></div>
                                    <div><span>DTFTA Type</span><b>{{ $item->dtfta_type ?? '-' }}</b></div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(isset($orderItems) && count($orderItems) > 0)
                    <div class="ld-section">
                        <div class="ld-card">
                            <h3>Garment Details</h3>
                            <div class="ld-info">
                                @php
                                    $firstItem = $orderItems->first();
                                    $properties = $firstItem->properties ?? [];
                                @endphp
                                <div><span>Product Title</span><b>{{ $firstItem->title ?? '-' }}</b></div>
                                <div><span>Variant</span><b>{{ $firstItem->variant_title ?? '-' }}</b></div>
                                <div><span>DTFTA Type</span><b>{{ $firstItem->dtfta_type ?? '-' }}</b></div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="ld-section">
                        <div class="ld-card">
                            <h3>Print Details</h3>
                            <div class="ld-info">
                                <div><span>Status</span><b>{{ $order->fulfillment_status ?? 'pending' }}</b></div>
                                <div><span>Created At</span><b>{{ $order->created_at_shopify?->format('Y-m-d H:i') ?? 'N/A' }}</b></div>
                                <div><span>Updated At</span><b>{{ $order->updated_at_shopify?->format('Y-m-d H:i') ?? 'N/A' }}</b></div>
                                @if(isset($job) && $job->payload)
                                    <div class="artwork-preview">
                                        <span>Additional Info</span>
                                        <p>{{ json_encode($job->payload) }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>


                </div>

                <!-- RIGHT PANEL -->
                <div class="ld-right-panel">

                    @if(isset($order))
                   
                   
                    <div class="job-info-card ld-card">
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
                            <span id="statusBadge" class="status-badge">{{ strtoupper(str_replace('_', ' ', $order->fulfillment_status )) }}</span>
                        </div>
                    </div>
                    @endif

                    <div class="ld-stepper" id="ldStepper">
                        @php
                            // Always use order's fulfillment_status
                            $currStatus = isset($order) ? $order->fulfillment_status : null;
                        @endphp
                        <div class="step step-new {{ $currStatus === 'pending' ? 'active' : '' }}" onclick="updateJobStatus('pending', this)" data-status="pending">NEW</div>
                        <div class="step step-artwork {{ $currStatus === 'artwork_needed' ? 'active' : '' }}" onclick="updateJobStatus('artwork_needed', this)" data-status="artwork_needed">ARTWORK NEEDED<span class="asterisk">*</span></div>
                        <div class="step step-production {{ $currStatus === 'in_production' ? 'active' : '' }}" onclick="updateJobStatus('in_production', this)" data-status="in_production">IN PRODUCTION</div>
                        <div class="step step-shipped {{ $currStatus === 'shipped' ? 'active' : '' }}" onclick="updateJobStatus('shipped', this)" data-status="shipped">SHIPPED</div>
                        <div class="step step-cancelled {{ in_array($currStatus, ['cancelled', 'failed', 'exception']) ? 'active' : '' }}" onclick="updateJobStatus('cancelled', this)" data-status="cancelled">CANCELLED/EXCEPTION</div>
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

                        .ld-stepper .step-new { border-left: 4px solid #3B82F6; }
                        .ld-stepper .step-artwork { border-left: 4px solid #F59E0B; }
                        .ld-stepper .step-production { border-left: 4px solid #8B5CF6; }
                        .ld-stepper .step-shipped { border-left: 4px solid #10B981; }
                        .ld-stepper .step-cancelled { border-left: 4px solid #EF4444; }

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
                    </style>


                    <div class="ld-tabs">
                        <div class="tab active" onclick="switchTab('all')">All</div>
                        
                    </div>

                    <div class="ld-content" id="tabContent">
                        @forelse($activityLog as $activity)
                        <div class="ld-timeline-item">
                            <div class="ld-icon" aria-hidden="true"></div>
                            <div>
                                <b>{{ $activity->action }}: {{ $activity->model_type }}</b>
                                <p>{{ $activity->created_at->format('d M Y, g:i A') }}@if($activity->user), By {{ $activity->user->name }}@endif</p>
                            </div>
                        </div>
                        @empty
                       
                        @endforelse
                    </div>

                </div>
            </div>

   

   
   

    

    <script src="public/assets/js/script.js"></script>
    <script>
        // Load current order status from server on page load
        function loadCurrentOrderStatus() {
            const orderId = {{ $order->id ?? 'null' }};
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                             document.querySelector('input[name="_token"]')?.value || '';

            if (!orderId) return;

            fetch(`/api/get-order-status/${orderId}`, {
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

        // Update UI with current status
        function updateUIWithStatus(status) {
            // Update stepper
            document.querySelectorAll('.ld-stepper .step').forEach(step => {
                step.classList.remove('active');
            });

            const activeStep = document.querySelector(`.ld-stepper .step[data-status="${status}"]`);
            if (activeStep) {
                activeStep.classList.add('active');
            }

            // Update status badge
            const statusBadge = document.getElementById('statusBadge');
            if (statusBadge) {
                const displayText = getStatusDisplayName(status);
                statusBadge.textContent = displayText;
                statusBadge.className = 'status-badge status-' + getStatusClassName(status);
            }
        }

        // Map database status to display names
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

        // Get status class name
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

        // Load status on page load
        document.addEventListener('DOMContentLoaded', loadCurrentOrderStatus);

        // Update job status when a step is clicked
        function updateJobStatus(newStatus, element) {
            const orderId = {{ $order->id ?? 'null' }};
            
            if (!orderId) return;

            // Remove active class from all steps and reset style
            document.querySelectorAll('.ld-stepper .step').forEach(step => {
                step.classList.remove('active');
            });

            // Add active class to clicked step
            element.classList.add('active');

            // Update status via AJAX - always update order
            const id = orderId;
            const type = 'order';

            // Get CSRF token from meta tag or form
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                             document.querySelector('input[name="_token"]')?.value || '';

            fetch(`/api/update-status`, {
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
                    
                    // Update all active steps
                    document.querySelectorAll('.ld-stepper .step').forEach(step => {
                        step.classList.remove('active');
                    });
                    
                    // Find and activate the new status step
                    const activeStep = document.querySelector(`.ld-stepper .step[data-status="${newStatus}"]`);
                    if (activeStep) {
                        activeStep.classList.add('active');
                    }
                    
                    // Update the status badge with proper display name
                    const statusBadge = document.getElementById('statusBadge');
                    if (statusBadge) {
                        const displayText = getStatusDisplayName(newStatus);
                        statusBadge.textContent = displayText;
                        statusBadge.className = 'status-badge status-' + getStatusClassName(newStatus);
                    }
                    
                    // Refresh the activity log
                    refreshActivityLog(id, type);
                    
                    // Show notification
                    if (typeof showNotification === 'function') {
                        showNotification('Status updated successfully', 'success');
                    }
                } else {
                    alert('Error updating status: ' + (data.message || 'Unknown error'));
                    // Revert the active state if error
                    document.querySelectorAll('.ld-stepper .step').forEach(step => {
                        step.classList.remove('active');
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status');
                // Revert the active state if error
                document.querySelectorAll('.ld-stepper .step').forEach(step => {
                    step.classList.remove('active');
                });
            });
        }

        // Refresh activity log
        function refreshActivityLog(id, type) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            fetch(`/api/get-activity-log`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id: id,
                    type: type
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateActivityContent(data.activities);
                }
            })
            .catch(error => {
                console.error('Error refreshing activity log:', error);
            });
        }

        // Update activity content
        function updateActivityContent(activities) {
            const tabContent = document.getElementById('tabContent');
            
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
                
                html += `
                    <div class="ld-timeline-item">
                        <div class="ld-icon" aria-hidden="true"></div>
                        <div>
                            <b>${activity.action}: ${activity.model_type}</b>
                            <p>${formattedDate}, By ${userName}</p>
                        </div>
                    </div>
                `;
            });
            
            tabContent.innerHTML = html;
        }

        // Switch tabs
        function switchTab(tabName) {
            // Remove active class from all tabs
            document.querySelectorAll('.ld-tabs .tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Add active class to clicked tab
            event.target.classList.add('active');

            // Update content based on tab
            const content = document.getElementById('tabContent');
            if (tabName === 'all') {
                content.style.display = 'block';
            } else if (tabName === 'activity') {
                content.style.display = 'block';
            }
        }
    </script>
@endsection
