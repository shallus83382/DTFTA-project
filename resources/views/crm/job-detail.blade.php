@extends('layouts.app')

@section('title', 'DTFTA CRM - Job Detail')
@section('page-title', 'Job Details')

@section('content')


            <!-- Top Section -->
            <div class="job-header">
                <div class="job-header-info">
                    <h2>Job ID: #1234</h2>
                    <p>Store Name: <strong>Store ABC</strong></p>
                    <p>Shopify Order Number: <strong>#5678</strong></p>
                </div>
                <div class="job-status-large">
                    <span class="status-badge large" id="statusBadge"></span>
                </div>
            </div>

            <!-- Job Stepper -->
            <div class="stepper-container">
                <div class="stepper">
                    <div class="stepper-step completed">
                        <div class="stepper-circle">1</div>
                        <div class="stepper-label">NEW</div>
                    </div>
                    <div class="stepper-line"></div>
                    <div class="stepper-step active">
                        <div class="stepper-circle">2</div>
                        <div class="stepper-label">ARTWORK NEEDED</div>
                    </div>
                    <div class="stepper-line"></div>
                    <div class="stepper-step">
                        <div class="stepper-circle">3</div>
                        <div class="stepper-label">IN PRODUCTION</div>
                    </div>
                    <div class="stepper-line"></div>
                    <div class="stepper-step">
                        <div class="stepper-circle">4</div>
                        <div class="stepper-label">SHIPPED</div>
                    </div>
                </div>
            </div>

            <!-- Job Details Section -->
            <div class="job-details-grid">
                <!-- Order Info -->
                <div class="detail-card">
                    <h3>Order Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Store Name:</span>
                        <span class="detail-value">Store ABC</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Shopify Order ID:</span>
                        <span class="detail-value">#5678</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fulfillment Type:</span>
                        <span class="detail-value">DTF</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Created At:</span>
                        <span class="detail-value">2024-01-15 10:30 AM</span>
                    </div>
                </div>

                <!-- Garment Details (Apparel POD only) -->
                <div class="detail-card">
                    <h3>Garment Details</h3>
                    <div class="detail-item">
                        <span class="detail-label">Brand:</span>
                        <span class="detail-value">Gildan</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Style Code:</span>
                        <span class="detail-value">G500</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Color:</span>
                        <span class="detail-value">Black</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Size:</span>
                        <span class="detail-value">Large</span>
                    </div>
                </div>

                <!-- Print Details -->
                <div class="detail-card">
                    <h3>Print Details</h3>
                    <div class="detail-item">
                        <span class="detail-label">Placement:</span>
                        <span class="detail-value">Front</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Print Size:</span>
                        <span class="detail-value">12x16 inches</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Artwork:</span>
                        <div class="artwork-preview">
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Crect fill='%234A90E2' width='200' height='200'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23fff' font-family='sans-serif' font-size='14'%3EArtwork Preview%3C/text%3E%3C/svg%3E" alt="Artwork Preview" width="200" height="200">
                            <button class="btn-secondary">Download Artwork</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons (Status Based) -->
            <div class="action-buttons-section" id="actionButtons">  
            </div>

            <!-- Exception/Artwork Needed Info -->
            <div class="exception-section" id="exceptionSection" style="display: none;">
                <div class="exception-card">
                    <h3><span class="section-icon section-icon-alert" aria-hidden="true"></span>Exception / Artwork Needed</h3>
                    <div class="exception-content">
                        <p><strong>Reason:</strong> <span id="exceptionReason">Artwork quality is low, needs revision</span></p>
                        <p><strong>Created:</strong> <span id="exceptionDate">2024-01-15 10:30 AM</span></p>
                    </div>
                </div>
            </div>
    

@endsection

