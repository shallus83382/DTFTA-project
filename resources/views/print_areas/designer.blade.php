@extends('layouts.app')

@section('title', 'DTFTA CRM - Print Area Preview')
@section('page-title', 'Print Area Preview')

@section('content')

    <div class="chart-card" style="margin-top:18px; padding:20px;">
        <div id="printAreaDesigner">
            <p style="color:#94a3b8;">Loading preview…</p>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fabric@7.2.0/dist/index.min.js"></script>
    <script src="{{ asset('js/print-area-preview.js') }}?v={{ @filemtime(public_path('js/print-area-preview.js')) ?: '1' }}"></script>
    <script>
        (function () {
            function boot() {
                var mount = document.getElementById('printAreaDesigner');
                if (!mount) return;
                mount.innerHTML = '';
                var preview = new window.PrintAreaPreview(mount, {
                    mode: 'designer',
                    data: @json($previewData),
                    initialProductId: @json($initialProductId),
                    initialPrintAreaId: @json($initialPrintAreaId),
                    initialColor: @json($initialColor)
                });
                window.addEventListener('beforeunload', function (e) {
                    if (preview.canvas && preview.isDirty()) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
@endpush
