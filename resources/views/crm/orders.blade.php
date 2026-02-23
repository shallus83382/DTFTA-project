@extends('layouts.app')

@section('title', 'Orders / Jobs Management')

@section('content')
    <!-- Board View Content (Leads) -->
    <div id="boardView" class="view-content">
        <!-- Search / Controls -->
        <div class="filters-section">
            <div class="lead-filters-left">
                <input type="text" id="boardSearchInput" class="lead-search-input" placeholder="Search by job ID or order number">
                <button class="btn-secondary" id="boardFilterBtn" type="button">Filter</button>
            </div>
            <div class="lead-filters-right" style="display:flex; gap:12px; align-items:center;">
                <select class="filter-select" id="boardStatusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="artwork_needed">Artwork Needed</option>
                    <option value="in_production">In Production</option>
                    <option value="shipped">Shipped</option>
                    <option value="cancelled">Cancelled/Exception</option>
                </select>
            </div>
            <!-- View Toggle Buttons -->
            <div class="view-toggle-section">
                <button class="view-toggle-btn active" id="boardViewBtnTop" data-view-toggle="board" onclick="switchView('board')">
                    <span class="view-toggle-icon-board" aria-hidden="true"></span>
                </button>
                <button class="view-toggle-btn" id="tableViewBtnTop" data-view-toggle="table" onclick="switchView('table')">
                    <span class="view-toggle-icon-table" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <!-- Leads Board (kanban style) -->
        <div class="leads-board">
            <!-- Column: New (Pending) -->
            <div class="lead-column" data-type="New">
                <div class="lead-column-header">
                    <h3>NEW ({{ count($jobsByStatus['pending']) }})</h3>
                    <button class="btn-secondary" onclick="openModal(this)">+</button>
                </div>
                <div class="lead-column-body" data-status="pending">
                    @forelse($jobsByStatus['pending'] as $job)
                        <div class="lead-card job-card" draggable="true" data-job-id="{{ $job->id }}" data-order-id="{{ $job->order_id }}" data-current-status="pending" data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}" data-job-type="{{ strtolower($job->job_type ?? '') }}" data-created-date="{{ $job->created_at->format('Y-m-d') }}" onclick="navigateToOrder(event, {{ $job->order_id }})" style="cursor: grab;">
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
                                    <span class="status-badge status-new">{{ strtoupper($job->status) }}</span>
                                    <span class="status-badge tag-muted">{{ $job->job_type }}</span>
                                </div>
                        </div>
                    @empty
                        <p style="padding: 20px; text-align: center; color: #999;">No new jobs</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: Artwork Needed -->
            <div class="lead-column" data-type="Artwork Needed">
                <div class="lead-column-header">
                    <h3>ARTWORK NEEDED ({{ count($jobsByStatus['artwork_needed']) }})</h3>
                    <button class="btn-secondary" onclick="openModal(this)">+</button>
                </div>
                <div class="lead-column-body" data-status="artwork_needed">
                    @forelse($jobsByStatus['artwork_needed'] as $job)
                        <div class="lead-card job-card" draggable="true" data-job-id="{{ $job->id }}" data-order-id="{{ $job->order_id }}" data-current-status="artwork_needed" data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}" data-job-type="{{ strtolower($job->job_type ?? '') }}" data-created-date="{{ $job->created_at->format('Y-m-d') }}" onclick="navigateToOrder(event, {{ $job->order_id }})" style="cursor: grab;">
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
                                    <span class="status-badge status-artwork">{{ strtoupper($job->status) }}</span>
                                    <span class="status-badge tag-orange">{{ $job->job_type }}</span>
                                </div>
                        </div>
                    @empty
                        <p style="padding: 20px; text-align: center; color: #999;">No artwork needed</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: In Production -->
            <div class="lead-column" data-type="In Production">
                <div class="lead-column-header">
                    <h3>IN PRODUCTION ({{ count($jobsByStatus['in_production']) }})</h3>
                    <button class="btn-secondary" onclick="openModal(this)">+</button>
                </div>
                <div class="lead-column-body" data-status="in_production">
                    @forelse($jobsByStatus['in_production'] as $job)
                        <div class="lead-card job-card" draggable="true" data-job-id="{{ $job->id }}" data-order-id="{{ $job->order_id }}" data-current-status="in_production" data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}" data-job-type="{{ strtolower($job->job_type ?? '') }}" data-created-date="{{ $job->created_at->format('Y-m-d') }}" onclick="navigateToOrder(event, {{ $job->order_id }})" style="cursor: grab;">
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
                                    <span class="status-badge status-production">{{ strtoupper($job->status) }}</span>
                                    <span class="status-badge tag-purple">{{ $job->job_type }}</span>
                                </div>
                        </div>
                    @empty
                        <p style="padding: 20px; text-align: center; color: #999;">No jobs in production</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: Shipped -->
            <div class="lead-column" data-type="Shipped">
                <div class="lead-column-header">
                    <h3>SHIPPED ({{ count($jobsByStatus['shipped']) }})</h3>
                    <button class="btn-secondary" onclick="openModal(this)">+</button>
                </div>
                <div class="lead-column-body" data-status="shipped">
                    @forelse($jobsByStatus['shipped'] as $job)
                        <div class="lead-card job-card" draggable="true" data-job-id="{{ $job->id }}" data-order-id="{{ $job->order_id }}" data-current-status="shipped" data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}" data-job-type="{{ strtolower($job->job_type ?? '') }}" data-created-date="{{ $job->created_at->format('Y-m-d') }}" onclick="navigateToOrder(event, {{ $job->order_id }})" style="cursor: grab;">
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
                                    <span class="status-badge status-shipped">{{ strtoupper($job->status) }}</span>
                                    <span class="status-badge tag-green">{{ $job->job_type }}</span>
                                </div>
                        </div>
                    @empty
                        <p style="padding: 20px; text-align: center; color: #999;">No shipped jobs</p>
                    @endforelse
                </div>
            </div>

            <!-- Column: Cancelled/Exception -->
            <div class="lead-column" data-type="Cancelled">
                <div class="lead-column-header">
                    <h3>CANCELLED/EXCEPTION ({{ count($jobsByStatus['cancelled']) }})</h3>
                    <button class="btn-secondary" onclick="openModal(this)">+</button>
                </div>
                <div class="lead-column-body" data-status="cancelled">
                    @forelse($jobsByStatus['cancelled'] as $job)
                        <div class="lead-card job-card" draggable="true" data-job-id="{{ $job->id }}" data-order-id="{{ $job->order_id }}" data-current-status="cancelled" data-store="{{ strtolower($job->shop->shop_domain ?? 'unknown') }}" data-job-type="{{ strtolower($job->job_type ?? '') }}" data-created-date="{{ $job->created_at->format('Y-m-d') }}" onclick="navigateToOrder(event, {{ $job->order_id }})" style="cursor: grab;">
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
                                    <span class="status-badge status-exception">{{ strtoupper($job->status) }}</span>
                                    <span class="status-badge tag-red">{{ $job->job_type }}</span>
                                </div>
                        </div>
                    @empty
                        <p style="padding: 20px; text-align: center; color: #999;">No cancelled/exception jobs</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Table View Content (Orders) -->
    <div id="tableView" class="view-content" style="display: none;">
        <!-- Filters Section -->
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
                <label>Product Type</label>
                <select id="filterProductType" class="filter-select">
                    <option value="">All Types</option>
                    @foreach($productTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label>Date</label>
                <input type="date" id="filterDate" class="filter-select">
            </div>

            <button class="btn-secondary lead-clear-filters" onclick="clearFilters()">Clear Filters</button>

            <!-- View Toggle Buttons -->
            <div class="view-toggle-section">
                <button class="view-toggle-btn " id="boardViewBtnBottom" data-view-toggle="board" onclick="switchView('board')">
                    <span class="view-toggle-icon-board" aria-hidden="true"></span>
                </button>
                <button class="view-toggle-btn active" id="tableViewBtnBottom" data-view-toggle="table" onclick="switchView('table')">
                    <span class="view-toggle-icon-table" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <!-- Jobs Table -->
        <div class="table-container">
            <table class="jobs-table">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Store Name</th>
                        <th>Order ID</th>
                        <th>Product Type</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="jobsTableBody">
                    @forelse($allJobs as $job)
                        @php
                            $orderStatus = strtolower($job->order->fulfillment_status ?? 'pending');
                            $orderStatusClass = str_replace('_', '-', $orderStatus);
                            $orderStatusLabel = strtoupper(str_replace('_', ' ', $orderStatus));
                        @endphp
                        <tr data-job-id="{{ $job->id }}" data-order-id="{{ $job->order_id }}" data-store="{{ strtolower($job->shop->shop_domain ?? 'n/a') }}" data-product-type="{{ strtolower($job->job_type ?? '-') }}" data-status="{{ $orderStatus }}" data-created-date="{{ $job->created_at->format('Y-m-d') }}">
                            <td>#{{ $job->id }}</td>
                            <td>{{ $job->shop->shop_domain ?? 'N/A' }}</td>
                            <td>#{{ $job->order_id ?? 'N/A' }}</td>
                            <td>{{ $job->job_type ?? '-' }}</td>
                            <td><span class="status-badge status-{{ $orderStatusClass }}">{{ $orderStatusLabel }}</span></td>
                            <td>{{ $job->created_at->format('Y-m-d H:i') }}</td>
                            <td><a href="{{ route('crm.order-detail', $job->order_id) }}" class="btn-link">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #999;">No jobs found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
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
            if (status === 'failed' || status === 'exception') return 'cancelled';
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

        // Navigate to order detail
        function navigateToOrder(event, orderId) {
            if (event.target.closest('[draggable]') === event.currentTarget) {
                window.location.href = `/crm/order/detail/${orderId}`;
            }
        }

        // Setup drag and drop for all cards
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

        function handleDragEnd(e) {
            if (draggedOrderCard) {
                draggedOrderCard.style.opacity = '1';
            }
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.style.backgroundColor = '#f0f0f0';
        }

        function handleDragLeave(e) {
            this.style.backgroundColor = '';
        }

        function handleDrop(e) {
            e.preventDefault();
            this.style.backgroundColor = '';

            if (!draggedJobId) return;

            const newStatus = this.dataset.status;
            const oldStatus = draggedOrderCard.dataset.currentStatus;

            if (newStatus === oldStatus) {
                draggedOrderCard.style.opacity = '1';
                return;
            }

            // Get the order ID from the card
            const orderId = draggedOrderCard.dataset.orderId;
            
            // Update status via AJAX
            updateOrderStatusAjax(orderId, newStatus, draggedOrderCard);
        }

        function updateOrderStatusAjax(orderId, newStatus, cardElement) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            fetch(`/api/v1/update-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id: orderId,
                    type: 'order',
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateKanbanCardsByOrder(orderId, newStatus);

                    // Update table status
                    updateTableOrderStatus(orderId, newStatus);

                    console.log('Order status updated successfully');
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

        function updateKanbanCardsByOrder(orderId, newStatus) {
            const normalizedStatus = normalizeBoardStatus(newStatus);
            const targetColumn = document.querySelector(`.lead-column-body[data-status="${normalizedStatus}"]`);
            if (!targetColumn) return;

            const matchingCards = document.querySelectorAll(`.job-card[data-order-id="${orderId}"]`);
            matchingCards.forEach(card => {
                targetColumn.appendChild(card);
                card.dataset.currentStatus = normalizedStatus;
                updateCardStatusBadge(card, normalizedStatus);
                card.style.opacity = '1';
            });

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
                    message.style.padding = '20px';
                    message.style.textAlign = 'center';
                    message.style.color = '#999';
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

        function normalizeBoardStatus(status) {
            if (status === 'failed' || status === 'exception') {
                return 'cancelled';
            }
            return status;
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
            const productTypeValue = (document.getElementById('filterProductType')?.value || '').trim().toLowerCase();
            const dateValue = (document.getElementById('filterDate')?.value || '').trim();

            const rows = document.querySelectorAll('#jobsTableBody tr[data-order-id]');
            rows.forEach(row => {
                const rowStatus = normalizeFilterStatus(row.dataset.status || '');
                const rowStore = (row.dataset.store || '').trim().toLowerCase();
                const rowProductType = (row.dataset.productType || '').trim().toLowerCase();
                const rowDate = (row.dataset.createdDate || '').trim();

                const matchesStatus = !statusValue || rowStatus === statusValue;
                const matchesStore = !storeValue || rowStore === storeValue;
                const matchesProductType = !productTypeValue || rowProductType === productTypeValue;
                const matchesDate = !dateValue || rowDate === dateValue;

                row.style.display = (matchesStatus && matchesStore && matchesProductType && matchesDate) ? '' : 'none';
            });
        }

        function setupFilters() {
            const boardFilterBtn = document.getElementById('boardFilterBtn');
            const boardSearchInput = document.getElementById('boardSearchInput');
            const boardStatusFilter = document.getElementById('boardStatusFilter');
            const filterStatus = document.getElementById('filterStatus');
            const filterStore = document.getElementById('filterStore');
            const filterProductType = document.getElementById('filterProductType');
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
            if (filterProductType) filterProductType.addEventListener('change', applyTableFilters);
            if (filterDate) filterDate.addEventListener('change', applyTableFilters);
        }

        function clearFilters() {
            const filterStatus = document.getElementById('filterStatus');
            const filterStore = document.getElementById('filterStore');
            const filterProductType = document.getElementById('filterProductType');
            const filterDate = document.getElementById('filterDate');
            const boardSearchInput = document.getElementById('boardSearchInput');
            const boardStatusFilter = document.getElementById('boardStatusFilter');

            if (filterStatus) filterStatus.value = '';
            if (filterStore) filterStore.value = '';
            if (filterProductType) filterProductType.value = '';
            if (filterDate) filterDate.value = '';
            if (boardSearchInput) boardSearchInput.value = '';
            if (boardStatusFilter) boardStatusFilter.value = '';

            applyTableFilters();
            applyBoardFilters();
        }

        function updateTableOrderStatus(orderId, newStatus) {
            // Find the table row with this order ID
            const table = document.querySelector('.jobs-table');
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const orderIdCell = row.querySelector('td:nth-child(3)');
                if (orderIdCell && orderIdCell.textContent.includes(`#${orderId}`)) {
                    // Update the status cell (5th column)
                    const statusCell = row.querySelector('td:nth-child(5)');
                    if (statusCell) {
                        const displayStatus = getStatusDisplayName(newStatus);
                        const className = getStatusClassName(newStatus);
                        statusCell.innerHTML = `<span class="status-badge status-${className}">${displayStatus}</span>`;
                    }
                    row.dataset.status = normalizeFilterStatus(newStatus);
                }
            });
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupDragAndDrop();
            setupFilters();
        });
    </script>
@endsection
