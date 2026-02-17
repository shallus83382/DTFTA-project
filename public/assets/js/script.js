// ============================================
// DTFTA CRM - JavaScript
// Simple interactivity for dashboard
// ============================================

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeFilters();
    initializeJobDetail();
    initializeSidebarToggle();
    initializeStoreCheckboxes();
    initializeCounters();
    initializeViewToggle();
    initializeLeadDragDrop();
    initializeUserDropdown();
    initializeNotificationDropdown();
});

// ============================================
// Sidebar Toggle Functionality
// ============================================

function initializeSidebarToggle() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarClose = document.getElementById('sidebarClose');

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        if (menuToggle) menuToggle.classList.remove('active');
    }

    function openSidebar() {
        if (sidebar) sidebar.classList.add('active');
        if (overlay) overlay.classList.add('active');
        if (menuToggle) menuToggle.classList.add('active');
    }

    if (menuToggle && sidebar && overlay) {
        // Toggle sidebar on menu button click
        menuToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        // Close sidebar when close button is clicked
        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }

        // Close sidebar when overlay is clicked
        overlay.addEventListener('click', closeSidebar);

        // Close sidebar when clicking on a nav item (mobile only)
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        });
    }
}

// ============================================
// Stores checkbox select-all behavior
// ============================================
function initializeStoreCheckboxes() {
    const selectAll = document.getElementById('selectAllStores');
    const rowCheckboxes = Array.from(document.querySelectorAll('.row-checkbox'));

    if (!selectAll || rowCheckboxes.length === 0) return;

    selectAll.addEventListener('change', () => {
        rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
    });

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const allChecked = rowCheckboxes.every(r => r.checked);
            selectAll.checked = allChecked;
            // If some checked and some not, set indeterminate
            const someChecked = rowCheckboxes.some(r => r.checked);
            selectAll.indeterminate = !allChecked && someChecked;
        });
    });
}

// ============================================
// Chart Initialization
// ============================================

function initializeCharts() {
    // Orders Per Day Chart
    const ordersCtx = document.getElementById('ordersChart');
    if (ordersCtx) {
        new Chart(ordersCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Orders',
                    data: [45, 52, 48, 61, 55, 67, 58],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#cbd5e1'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#cbd5e1'
                        }
                    }
                }
            }
        });
    }

    // Orders Per Store Chart
    const storesCtx = document.getElementById('storesChart');
    if (storesCtx) {
        new Chart(storesCtx, {
            type: 'bar',
            data: {
                labels: ['Store ABC', 'Store XYZ', 'Store 123', 'Store DEF', 'Store GHI'],
                datasets: [{
                    label: 'Orders',
                    data: [234, 189, 156, 142, 98],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        '#3b82f6',
                        '#a855f7',
                        '#10b981',
                        '#f97316',
                        '#ef4444'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#cbd5e1'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#cbd5e1'
                        }
                    }
                }
            }
        });
    }

    // Apparel vs DTF Jobs Chart
    const productTypeCtx = document.getElementById('productTypeChart');
    if (productTypeCtx) {
        new Chart(productTypeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Apparel POD', 'DTF'],
                datasets: [{
                    data: [65, 35],
                    backgroundColor: [
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(59, 130, 246, 0.8)'
                    ],
                    borderColor: [
                        '#a855f7',
                        '#3b82f6'
                    ],
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#cbd5e1',
                            padding: 20,
                            font: {
                                family: 'Poppins',
                                size: 14,
                                weight: '500'
                            }
                        }
                    }
                }
            }
        });
    }
}

// ============================================
// Filter Functionality
// ============================================

function initializeFilters() {
    const filterStatus = document.getElementById('filterStatus');
    const filterStore = document.getElementById('filterStore');
    const filterProductType = document.getElementById('filterProductType');
    const filterDate = document.getElementById('filterDate');
    const tableBody = document.getElementById('jobsTableBody');

    if (!tableBody) return;

    const originalRows = Array.from(tableBody.querySelectorAll('tr'));

    function applyFilters() {
        const statusValue = filterStatus ? filterStatus.value : 'all';
        const storeValue = filterStore ? filterStore.value : 'all';
        const productTypeValue = filterProductType ? filterProductType.value : 'all';
        const dateValue = filterDate ? filterDate.value : '';

        originalRows.forEach(row => {
            let show = true;

            // Filter by status
            if (statusValue !== 'all') {
                const statusBadge = row.querySelector('.status-badge');
                if (statusBadge) {
                    const rowStatus = statusBadge.textContent.trim();
                    if (rowStatus !== statusValue) {
                        show = false;
                    }
                }
            }

            // Filter by store
            if (storeValue !== 'all' && show) {
                const storeCell = row.cells[1];
                if (storeCell && storeCell.textContent.trim() !== storeValue) {
                    show = false;
                }
            }

            // Filter by product type
            if (productTypeValue !== 'all' && show) {
                const productCell = row.cells[3];
                if (productCell && productCell.textContent.trim() !== productTypeValue) {
                    show = false;
                }
            }

            // Filter by date (simple implementation)
            if (dateValue && show) {
                const dateCell = row.cells[6];
                if (dateCell) {
                    const cellDate = dateCell.textContent.trim().split(' ')[0];
                    if (cellDate !== dateValue) {
                        show = false;
                    }
                }
            }

            row.style.display = show ? '' : 'none';
        });
    }

    if (filterStatus) filterStatus.addEventListener('change', applyFilters);
    if (filterStore) filterStore.addEventListener('change', applyFilters);
    if (filterProductType) filterProductType.addEventListener('change', applyFilters);
    if (filterDate) filterDate.addEventListener('change', applyFilters);
}

function clearFilters() {
    const filterStatus = document.getElementById('filterStatus');
    const filterStore = document.getElementById('filterStore');
    const filterProductType = document.getElementById('filterProductType');
    const filterDate = document.getElementById('filterDate');
    const tableBody = document.getElementById('jobsTableBody');

    if (filterStatus) filterStatus.value = 'all';
    if (filterStore) filterStore.value = 'all';
    if (filterProductType) filterProductType.value = 'all';
    if (filterDate) filterDate.value = '';

    if (tableBody) {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
            row.style.display = '';
        });
    }
}

// ============================================
// Job Detail - FINAL ARCHITECTURE
// ============================================

function initializeJobDetail() {
    const urlParams = new URLSearchParams(window.location.search);
    const statusParam = urlParams.get('status');

    // Default if nothing provided
    let status = statusParam ? statusParam.toUpperCase() : 'NEW';

    setJobStatus(status);
}


function setJobStatus(status) {
    status = status.toUpperCase();

    // Update Status Badge
    const badge = document.getElementById('statusBadge');
    if (badge) {
        badge.textContent = status.replace(/_/g, ' ');
        const statusClassMap = {
            'NEW': 'new',
            'ARTWORK_NEEDED': 'artwork',
            'IN_PRODUCTION': 'production',
            'SHIPPED': 'shipped',
            'CANCELLED': 'cancelled',
            'EXCEPTION': 'exception'
        };
        const cssSuffix = statusClassMap[status] || 'new';

        // Reset badge classes
        badge.className = 'status-badge';
        badge.classList.add(`status-${cssSuffix}`);
    }

    updateActionButtons(status);

    // 3. Update Stepper
    const stepMap = {
        'NEW': 0,
        'ARTWORK_NEEDED': 1,
        'IN_PRODUCTION': 2,
        'SHIPPED': 3
    };
    updateStepper(stepMap[status] ?? 0);
}

// ============================================
// Buttons Per Status
// ============================================

function updateActionButtons(status) {
    const actionSection = document.getElementById('actionButtons');
    if (!actionSection) return;

    actionSection.innerHTML = '';

    switch(status) {
        case 'NEW':
            actionSection.innerHTML = `
                <button class="btn-primary" onclick="markInProduction()">Mark as In Production</button>
                <button class="btn-warning" onclick="markArtworkNeeded()">Mark as Artwork Needed</button>
                <button class="btn-danger" onclick="cancelJob()">Cancel Job</button>
            `;
            break;
        case 'ARTWORK_NEEDED':
            actionSection.innerHTML = `
                <button class="btn-secondary" onclick="uploadArtwork()">Upload / View Artwork</button>
                <button class="btn-primary" onclick="moveToProduction()">Move to In Production</button>
                <button class="btn-danger" onclick="cancelJob()">Cancel Job</button>
            `;
            break;
        case 'IN_PRODUCTION':
            actionSection.innerHTML = `
                <button class="btn-primary" onclick="markShipped()">Mark as Shipped</button>
                <button class="btn-warning" onclick="markException()">Mark as Exception</button>
            `;
            break;
        case 'SHIPPED':
            actionSection.innerHTML = `
                <p style="color: var(--text-secondary);">
                    This job has been shipped. No further actions available.
                </p>
            `;
            break;
        case 'CANCELLED':
        case 'EXCEPTION':
            actionSection.innerHTML = `
                <p style="color: var(--text-secondary);">
                    This job is ${status.toLowerCase()}.
                </p>
                <button class="btn-secondary" onclick="toggleExceptionSection()">
                    Show reason + timestamp
                </button>
            `;
            break;
    }
}

// ============================================
// State Transitions
// ============================================

function markInProduction() {
    if (confirm('Mark this job as In Production?')) {
        setJobStatus('IN_PRODUCTION');
    }
}

function markArtworkNeeded() {
    const reason = prompt('Enter reason for artwork needed:');
    if (reason) {
        setJobStatus('ARTWORK_NEEDED');
        showExceptionSection(reason);
    }
}

function moveToProduction() {
    if (confirm('Move this job to Production?')) {
        setJobStatus('IN_PRODUCTION');
    }
}

function markShipped() {
    if (confirm('Mark this job as Shipped?')) {
        setJobStatus('SHIPPED');
    }
}

function markException() {
    const reason = prompt('Enter exception reason:');
    if (reason) {
        setJobStatus('EXCEPTION');
        showExceptionSection(reason);
    }
}

function cancelJob() {
    const reason = prompt('Enter cancellation reason:');
    if (reason && confirm('Are you sure?')) {
        setJobStatus('CANCELLED');
        showExceptionSection(reason);
    }
}

function uploadArtwork() {
    alert('Artwork upload modal here.');
}

function updateStepper(activeStep) {
    const steps = document.querySelectorAll('.stepper-step');
    steps.forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index < activeStep) {
            step.classList.add('completed');
        } else if (index === activeStep) {
            step.classList.add('active');
        }
    });
}

function showExceptionSection(reason, dateString) {
    const exceptionSection = document.getElementById('exceptionSection');
    const exceptionReason = document.getElementById('exceptionReason');
    const exceptionDate = document.getElementById('exceptionDate');

    if (exceptionSection && exceptionReason && exceptionDate) {
        exceptionReason.textContent = reason;
        if (dateString && typeof dateString === 'string' && dateString.trim()) {
            exceptionDate.textContent = dateString;
        } else {
            exceptionDate.textContent = new Date().toLocaleString();
        }
        exceptionSection.style.display = 'block';
    }
}

function toggleExceptionSection() {
    const exceptionSection = document.getElementById('exceptionSection');
    if (!exceptionSection) return;
    exceptionSection.style.display = exceptionSection.style.display === 'block' ? 'none' : 'block';
}

// ============================================
// Counters (animate card numbers)
// ============================================
function initializeCounters() {
    const counterElements = Array.from(document.querySelectorAll('.card-value'));
    if (counterElements.length === 0) return;

    const formatNumber = (num) => {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    counterElements.forEach(element => {
        const rawText = element.textContent.trim();
        const targetNumber = parseInt(rawText.replace(/,/g, ''), 10) || 0;
        const animationDurationMs = 1400;

        // Start from zero visually
        element.textContent = '0';
        element.classList.add('counter');

        let startTimestamp = null;

        function step(timestamp) {
            if (!startTimestamp) startTimestamp = timestamp;
            const elapsed = timestamp - startTimestamp;
            const progress = Math.min(elapsed / animationDurationMs, 1);
            const currentValue = Math.floor(progress * targetNumber);
            element.textContent = formatNumber(currentValue);

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                element.textContent = formatNumber(targetNumber);
                // small highlight when finished
                element.classList.add('animate');
                setTimeout(() => element.classList.remove('animate'), 350);
            }
        }

        requestAnimationFrame(step);
    });
}




// ===============================
// Leads Modal + Kanban Drag Drop
// ===============================

let activeColumnBody = null;
let draggedCard = null;

/* ---------- MODAL ---------- */

function openModal(btn) {
    const column = btn.closest('.lead-column');
    const type = column.getAttribute('data-type');

    activeColumnBody = column.querySelector('.lead-column-body');

    document.getElementById('modalTitle').innerText = `Add ${type} Lead`;
    document.getElementById('addLeadModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('addLeadModal').style.display = 'none';
}

function addLead() {
    const name = document.getElementById('leadName').value.trim();
    const company = document.getElementById('leadCompany').value.trim();
    const desc = document.getElementById('leadDesc').value.trim();
    const value = document.getElementById('leadValue').value;

    if (!name || !company) {
        alert("Name & Company required");
        return;
    }

    const status = document.getElementById('modalTitle')
        .innerText.replace('Add ', '')
        .replace(' Lead', '');

    const statusClassMap = {
        "New": "status-new",
        "Follow Up": "status-production",
        "Prospect": "status-artwork",
        "Won": "status-shipped"
    };

    const card = document.createElement('div');
    card.className = 'lead-card';
    card.innerHTML = `
        <div class="lead-card-header">
            <div class="lead-card-info">
                <div class="lead-avatar avatar-blue">${name[0].toUpperCase()}</div>
                <div>
                    <div class="lead-name">${name}</div>
                    <div class="lead-company">${company}</div>
                </div>
            </div>
            <div class="lead-time">Just now</div>
        </div>
        <div class="lead-description">${desc}</div>
        <div class="lead-tags">
            <span class="status-badge ${statusClassMap[status]}">${status}</span>
            ${value ? `<span class="status-badge tag-green">$${value}</span>` : ''}
        </div>
    `;

    activeColumnBody.prepend(card);
    makeCardDraggable(card);
    closeModal();

    // reset form
    document.getElementById('leadName').value = '';
    document.getElementById('leadCompany').value = '';
    document.getElementById('leadDesc').value = '';
    document.getElementById('leadValue').value = '';
}

/* ---------- DRAG & DROP ---------- */

function initializeLeadDragDrop() {
    // Make existing cards draggable
    document.querySelectorAll('.lead-card').forEach(makeCardDraggable);

    // Make columns droppable
    document.querySelectorAll('.lead-column-body').forEach(column => {
        column.addEventListener('dragover', e => {
            e.preventDefault();
            column.classList.add('drag-over');
        });

        column.addEventListener('dragleave', () => {
            column.classList.remove('drag-over');
        });

        column.addEventListener('drop', () => {
            column.classList.remove('drag-over');
            if (!draggedCard) return;

            column.prepend(draggedCard);
            updateCardStatus(draggedCard, column);
        });
    });
}

function makeCardDraggable(card) {
    card.setAttribute('draggable', 'true');

    card.addEventListener('dragstart', () => {
        draggedCard = card;
        card.classList.add('dragging');
    });

    card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        draggedCard = null;
    });
}

/* ---------- STATUS UPDATE ---------- */

function updateCardStatus(card, columnBody) {
    const column = columnBody.closest('.lead-column');
    const newType = column.getAttribute('data-type');

    const statusBadge = card.querySelector('.status-badge');

    const statusClassMap = {
        "New": "status-new",
        "Follow Up": "status-production",
        "Prospect": "status-artwork",
        "Won": "status-shipped"
    };

    statusBadge.innerText = newType;
    statusBadge.className = 'status-badge ' + statusClassMap[newType];
}

/* ---------- UX EXTRAS ---------- */

// Close modal on outside click
const addLeadModal = document.getElementById('addLeadModal');
if (addLeadModal) {
    addLeadModal.addEventListener('click', e => {
        if (e.target.id === 'addLeadModal') closeModal();
    });
}

// Close modal on ESC
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

// ============================================
// View Toggle Functionality
// ============================================

function initializeViewToggle() {
    // Set default view to board
    const boardView = document.getElementById('boardView');
    const tableView = document.getElementById('tableView');
    const boardViewBtn = document.getElementById('boardViewBtn');
    const tableViewBtn = document.getElementById('tableViewBtn');
    const headerCreateBtn = document.getElementById('headerCreateBtn');

    if (!boardView || !tableView || !boardViewBtn || !tableViewBtn) return;

    // Initialize: show board view by default
    boardView.style.display = 'block';
    tableView.style.display = 'none';
    boardViewBtn.classList.add('active');
    tableViewBtn.classList.remove('active');
    if (headerCreateBtn) {
        headerCreateBtn.textContent = 'Create Lead';
    }
}

function switchView(viewType) {
    const boardView = document.getElementById('boardView');
    const tableView = document.getElementById('tableView');
    const boardViewBtn = document.getElementById('boardViewBtn');
    const tableViewBtn = document.getElementById('tableViewBtn');
    const headerCreateBtn = document.getElementById('headerCreateBtn');

    if (!boardView || !tableView || !boardViewBtn || !tableViewBtn) return;

    if (viewType === 'board') {
        boardView.style.display = 'block';
        tableView.style.display = 'none';
        boardViewBtn.classList.add('active');
        tableViewBtn.classList.remove('active');
        if (headerCreateBtn) {
            headerCreateBtn.textContent = 'Create Lead';
        }
    } else if (viewType === 'table') {
        boardView.style.display = 'none';
        tableView.style.display = 'block';
        boardViewBtn.classList.remove('active');
        tableViewBtn.classList.add('active');
        if (headerCreateBtn) {
            headerCreateBtn.textContent = '+ New Job';
        }
    }
}


// Lead modals js
// =======================
// MODALS (GLOBAL)
// =======================
window.openLdModal = function (type) {
    closeLdModal();
    const modal = document.getElementById(type + 'Modal');
    if (modal) modal.style.display = 'flex';
};

window.closeLdModal = function () {
    document.querySelectorAll('.ld-modal-overlay').forEach(m => {
        m.style.display = 'none';
    });
};

// =======================
// DOM READY
// =======================
document.addEventListener("DOMContentLoaded", function () {

    // =======================
    // CLOSE ON OUTSIDE CLICK
    // =======================
    document.querySelectorAll('.ld-modal-overlay').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target.classList.contains('ld-modal-overlay')) {
                closeLdModal();
            }
        });
    });

    // =======================
    // CLOSE ON ESC
    // =======================
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeLdModal();
    });

    // =======================
    // TABS SYSTEM
    // =======================
    const tabs = document.querySelectorAll('.tab');
    const content = document.querySelector('.ld-content');

    function timeline(text) {
        return `
            <div class="ld-timeline-item">
                <div class="ld-icon">⚙️</div>
                <div>
                    <b>${text}</b>
                    <p>13 Feb 2026, 3:00 PM, By Example</p>
                </div>
            </div>
        `;
    }

    function baseTimeline() {
        return `
            ${timeline("Updated Stage: Won → New")}
            ${timeline("Updated Stage: Negotiation → Won")}
            ${timeline("Lead created")}
        `;
    }

    const tabData = {
        All: baseTimeline(),
        Planned: timeline("📅 Planned meeting with client"),
        Notes: timeline("📝 Internal note added"),
        Calls: timeline("📞 Call with Billy James"),
        Meetings: timeline("🤝 Zoom meeting scheduled"),
        Files: timeline("📁 Proposal.pdf uploaded"),
        Emails: timeline("✉️ Follow-up email sent"),
        Products: timeline("🧩 Product added: TaskMaster Pro"),
        Quotes: timeline("💰 Quote generated: $10,000")
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const key = tab.innerText.trim();
            content.innerHTML = tabData[key] || "";
        });
    });

});


// Lead Stepper JS 
document.addEventListener("DOMContentLoaded", function () {
    const steps = document.querySelectorAll("#ldStepper .step");

    steps.forEach((step, index) => {
        step.addEventListener("click", function () {

            steps.forEach((s, i) => {
                s.classList.remove("active");

                if (i <= index) {
                    s.classList.add("active");
                }
            });

        });
    });
});

// ============================================
// User profile dropdown (header)
// ============================================
function initializeUserDropdown() {
    const trigger = document.querySelector('.user-dropdown-trigger');
    const menu = document.querySelector('.user-dropdown-menu');
    if (!trigger || !menu) return;

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        closeNotificationDropdown();
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', !isOpen);
        menu.hidden = isOpen;
    });

    document.addEventListener('click', function() {
        trigger.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
    });

    menu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// ============================================
// Notification dropdown (header)
// ============================================
function closeNotificationDropdown() {
    const trigger = document.querySelector('.notification-trigger');
    const menu = document.querySelector('.notification-dropdown-menu');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    if (menu) menu.hidden = true;
}

function initializeNotificationDropdown() {
    const trigger = document.querySelector('.notification-trigger');
    const menu = document.querySelector('.notification-dropdown-menu');
    if (!trigger || !menu) return;

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelector('.user-dropdown-trigger')?.setAttribute('aria-expanded', 'false');
        var userMenu = document.querySelector('.user-dropdown-menu'); if (userMenu) userMenu.hidden = true;
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', !isOpen);
        menu.hidden = isOpen;
    });

    document.addEventListener('click', function() {
        closeNotificationDropdown();
    });

    menu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}
