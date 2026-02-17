@extends('layouts.app')

@section('title', 'DTFTA CRM - Notifications')
@section('page-title', 'Notifications')

@section('content')


    <div class="notifications-toolbar">
        <div class="filter-group">
            <label>Filter</label>
            <select class="filter-select">
                <option value="">All</option>
                <option value="orders">Orders</option>
                <option value="shipping">Shipping</option>
                <option value="stores">Stores</option>
                <option value="system">System</option>
            </select>
        </div>
    </div>

    <div class="notifications-list">
        <div class="notification-item unread">
            <div class="notification-icon status-new">N</div>
            <div class="notification-content">
                <p class="notification-title">New order received</p>
                <p class="notification-text">Job #1234 from Store ABC — 3 items</p>
                <span class="notification-time">2 minutes ago</span>
            </div>
        </div>
        <div class="notification-item unread">
            <div class="notification-icon status-production">P</div>
            <div class="notification-content">
                <p class="notification-title">Moved to production</p>
                <p class="notification-text">Job #1230 is now in production</p>
                <span class="notification-time">15 minutes ago</span>
            </div>
        </div>
        <div class="notification-item">
            <div class="notification-icon status-shipped">S</div>
            <div class="notification-content">
                <p class="notification-title">Order shipped</p>
                <p class="notification-text">Job #1225 shipped — Tracking: 1Z999AA10123456784</p>
                <span class="notification-time">1 hour ago</span>
            </div>
        </div>
        <div class="notification-item">
            <div class="notification-icon status-exception">E</div>
            <div class="notification-content">
                <p class="notification-title">Artwork needed</p>
                <p class="notification-text">Job #1220 is waiting for artwork upload</p>
                <span class="notification-time">2 hours ago</span>
            </div>
        </div>
        <div class="notification-item">
            <div class="notification-icon status-shipped">S</div>
            <div class="notification-content">
                <p class="notification-title">Store connected</p>
                <p class="notification-text">store-123.myshopify.com is now connected</p>
                <span class="notification-time">Yesterday</span>
            </div>
        </div>
    </div>

@endsection
