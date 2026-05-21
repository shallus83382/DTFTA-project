@extends('layouts.app')

@section('title', 'DTFTA CRM - Print Areas')
@section('page-title', 'Print Areas')

@section('content')

    @if(session('success'))
        <div class="chart-card" style="margin-bottom:16px; border:1px solid #166534;">
            <strong>{{ session('success') }}</strong>
        </div>
    @endif

    <div style="margin-bottom:16px; display:flex; justify-content:flex-end;">
        <a href="{{ route('crm.print-areas.create') }}" class="btn-primary">
            Add Print Area
        </a>
    </div>

    {{-- ================= FILTERS ================= --}}
    <form id="printAreasFiltersForm"
          method="GET"
          action="{{ route('crm.print-areas.index') }}"
          class="filters-section">

        <div class="filter-group">
            <label for="search">Search</label>
            <input id="search"
                   name="search"
                   type="text"
                   placeholder="Placement title, T-shirt size"
                   class="filter-select"
                   value="{{ $filters['search'] ?? '' }}">
        </div>

        <div class="filter-group">
            <label for="status_filter">Status</label>
            <select id="status_filter"
                    name="status"
                    class="filter-select">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                <option value="1" {{ ($filters['status'] ?? '') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ ($filters['status'] ?? '') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="sort_by">Sort</label>
            <select id="sort_by"
                    name="sort_by"
                    class="filter-select">
                <option value="created_at" {{ ($filters['sort_by'] ?? '') === 'created_at' ? 'selected' : '' }}>Newest</option>
                <option value="title" {{ ($filters['sort_by'] ?? '') === 'title' ? 'selected' : '' }}>Title</option>
                <option value="display_order" {{ ($filters['sort_by'] ?? '') === 'display_order' ? 'selected' : '' }}>Display Order</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="sort_dir">Direction</label>
            <select id="sort_dir"
                    name="sort_dir"
                    class="filter-select">
                <option value="desc" {{ ($filters['sort_dir'] ?? 'desc') === 'desc' ? 'selected' : '' }}>DESC</option>
                <option value="asc" {{ ($filters['sort_dir'] ?? '') === 'asc' ? 'selected' : '' }}>ASC</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="per_page">Per Page</label>
            <select id="per_page"
                    name="per_page"
                    class="filter-select">
                @foreach([10,25,50,100] as $perPage)
                    <option value="{{ $perPage }}"
                        {{ (int)($filters['per_page'] ?? 10) === $perPage ? 'selected' : '' }}>
                        {{ $perPage }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn-secondary">Apply</button>
        <a href="{{ route('crm.print-areas.index') }}" class="btn-secondary">Clear</a>

    </form>


    {{-- ================= TABLE ================= --}}
    <div class="table-container" style="margin-top:16px;">
        <table class="jobs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <!-- <th>T-Shirt Size</th> -->
                    <th>Dimensions</th>
                    <th>Display Order</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($printAreas as $area)

                    @php
                        $badgeClass = $area->is_active
                            ? 'status-shipped'
                            : 'status-cancelled';
                    @endphp

                    <tr>
                        <td>#{{ $area->id }}</td>
                        <td>{{ $area->title }}</td>
                        <!-- <td>{{ $area->tshirt_size ?? '-' }}</td> -->
                        <td>
                            {{ $area->area_width }} x
                            {{ $area->area_height }}
                            {{ strtoupper($area->unit) }}
                        </td>
                        <td>{{ $area->display_order }}</td>
                        <td>
                            {{ $area->price !== null ? '$' . number_format((float) $area->price, 2) : '-' }}
                        </td>
                        <td>
                            <span class="status-badge {{ $badgeClass }}">
                                {{ $area->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            @if(!empty($area->images))
                             <x-selected-asset :category="$area->images['category'] ?? null" :asset-key="$area->images['asset_key'] ?? null" />
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('crm.print-areas.show',$area->id) }}"
                               class="btn-secondary"
                               style="margin-right:8px;">
                                View
                            </a>

                            <a href="{{ route('crm.print-areas.edit',$area->id) }}"
                               class="btn-secondary"
                               style="margin-right:8px;">
                                Edit
                            </a>

                            <form method="POST"
                                  action="{{ route('crm.print-areas.delete',$area->id) }}"
                                  style="display:inline-block;"
                                  onsubmit="return confirm('Delete this print area?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="8"
                            style="text-align:center; padding:20px; color:#999;">
                            No print areas found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <!-- <div class="pagination">
    {{ $printAreas->links() }}
</div> -->
    </div>


    {{-- ================= PAGINATION ================= --}}
    <div class="pagination">

        @if($printAreas->onFirstPage())
            <button class="btn-pagination" disabled>Previous</button>
        @else
            <a href="{{ $printAreas->previousPageUrl() }}"
               class="btn-pagination">Previous</a>
        @endif

        <span class="page-info">
            Page {{ $printAreas->currentPage() }}
            of {{ $printAreas->lastPage() }}
        </span>

        @if($printAreas->hasMorePages())
            <a href="{{ $printAreas->nextPageUrl() }}"
               class="btn-pagination">Next</a>
        @else
            <button class="btn-pagination" disabled>Next</button>
        @endif

    </div>


    {{-- ================= AUTO FILTER SCRIPT ================= --}}
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('printAreasFiltersForm');
            if (!form) return;

            ['status_filter','sort_by','sort_dir','per_page']
                .forEach(function (id) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.addEventListener('change', function () {
                        form.requestSubmit();
                    });
                });

            const search = document.getElementById('search');
            let timer = null;

            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    form.requestSubmit();
                }, 350);
            });

        });
    </script>
    @endpush

@endsection