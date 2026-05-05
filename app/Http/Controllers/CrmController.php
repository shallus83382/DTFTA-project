<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Job;
use App\Models\Shop;
use App\Models\Shipment;
use App\Models\FulfillmentService;
use App\Models\PartnerProfile;
use App\Models\Product;
use App\Models\AdminActivityLog;
use App\Models\BillingCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\PrintArea;
use App\Models\ProductVariant;
use App\Models\CustomProduct;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

class CrmController extends Controller
{
    /**
     * Get config from app config
     */
    private function getConfig()
    {
        return config('crm');
    }

    /**
     * Get navigation menu
     */
    private function getNavigation()
    {
        $config = $this->getConfig();
        return $config['navigation'] ?? [];
    }

    /**
     * Get app branding
     */
    private function getBranding()
    {
        $config = $this->getConfig();
        return $config['branding'] ?? [];
    }

    /**
     * Get theme colors
     */
    private function getTheme()
    {
        $config = $this->getConfig();
        return $config['theme'] ?? [];
    }

    /**
     * Get features config
     */
    private function getFeatures()
    {
        $config = $this->getConfig();
        return $config['features'] ?? [];
    }

    /**
     * Get status configurations
     */
    private function getStatuses()
    {
        $config = $this->getConfig();
        return $config['statuses'] ?? [];
    }

    /**
     * Prepare data for views
     */
    public function prepareViewData(): array
    {
        $route = request()->route();
        $routeName = $route ? $route->getName() : null;
        $routeParams = $route ? $route->parameters() : [];
        $headerNotificationsPayload = $this->buildHeaderNotifications();

        return [
            'config' => $this->getConfig(),
            'branding' => $this->getBranding(),
            'theme' => $this->getTheme(),
            'features' => $this->getFeatures(),
            'navigation' => $this->getNavigation(),
            'statuses' => $this->getStatuses(),
            'breadcrumbs' => $this->buildBreadcrumbs($routeName, $routeParams),
            'headerNotifications' => $headerNotificationsPayload['notifications'],
            'headerNotificationCount' => $headerNotificationsPayload['unread_count'],
        ];
    }
    /**
     * POST /api/v1/shipments
     * Create a new shipment and send fulfillment request to Shopify
     */
    private function getStoreStatusOptions(): array
    {
        return ['active', 'suspended', 'uninstalled'];
    }

    /**
     * Product status options for CRM filters/forms.
     */
    private function getProductStatusOptions(): array
    {
        return ['active', 'draft', 'archived', 'inactive'];
    }
    /**
     * GET /crm/dashboard
     * Display the CRM dashboard
     */
    private function buildBreadcrumbs(?string $routeName, array $routeParams = []): array
    {
        $dashboardCrumb = ['label' => 'Dashboard', 'url' => route('crm.dashboard')];

        switch ($routeName) {
            case 'crm.dashboard':
                return [['label' => 'Dashboard', 'url' => null]];

            case 'crm.orders':
                return [$dashboardCrumb, ['label' => 'Orders / Jobs', 'url' => null]];

            case 'crm.job-detail':
                return [
                    $dashboardCrumb,
                    ['label' => 'Orders / Jobs', 'url' => route('crm.orders')],
                    ['label' => 'Job #' . ($routeParams['jobId'] ?? ''), 'url' => null],
                ];

            case 'crm.order-detail':
                return [
                    $dashboardCrumb,
                    ['label' => 'Orders / Jobs', 'url' => route('crm.orders')],
                    ['label' => 'Order #' . ($routeParams['orderId'] ?? ''), 'url' => null],
                ];

            case 'crm.shipping':
                return [$dashboardCrumb, ['label' => 'Shipping', 'url' => null]];

            case 'crm.shipping-detail':
                return [
                    $dashboardCrumb,
                    ['label' => 'Shipping', 'url' => route('crm.shipping')],
                    ['label' => 'Shipment #' . ($routeParams['shipmentId'] ?? ''), 'url' => null],
                ];

            case 'crm.stores':
                return [$dashboardCrumb, ['label' => 'Stores', 'url' => null]];

            case 'crm.store-detail':
                return [
                    $dashboardCrumb,
                    ['label' => 'Stores', 'url' => route('crm.stores')],
                    ['label' => 'Store #' . ($routeParams['storeId'] ?? ''), 'url' => null],
                ];

            case 'crm.print-areas.index':
                return [
                    $dashboardCrumb,
                    ['label' => 'Print Areas', 'url' => null],
                ];

            case 'crm.print-areas.create':
                return [
                    $dashboardCrumb,
                    ['label' => 'Print Areas', 'url' => route('crm.print-areas.index')],
                    ['label' => 'Add Print Area', 'url' => null],
                ];

            case 'crm.print-areas.edit':
                return [
                    $dashboardCrumb,
                    ['label' => 'Print Areas', 'url' => route('crm.print-areas.index')],
                    ['label' => 'Edit Print Area', 'url' => null],
                ];

            case 'crm.print-areas.show':
                return [
                    $dashboardCrumb,
                    ['label' => 'Print Areas', 'url' => route('crm.print-areas.index')],
                    ['label' => 'Print Area #' . ($routeParams['id'] ?? ''), 'url' => null],
                ];
            case 'crm.products':
                return [$dashboardCrumb, ['label' => 'Products', 'url' => null]];
            case 'crm.products.create':
                return [
                    $dashboardCrumb,
                    ['label' => 'Products', 'url' => route('crm.products')],
                    ['label' => 'Add Product', 'url' => null],
                ];
            case 'crm.products.edit':
                return [
                    $dashboardCrumb,
                    ['label' => 'Products', 'url' => route('crm.products')],
                    ['label' => 'Edit Product', 'url' => null],
                ];
            case 'crm.products.view':
                return [
                    $dashboardCrumb,
                    ['label' => 'Products', 'url' => route('crm.products')],
                    ['label' => 'Product Details', 'url' => null],
                ];

            case 'crm.reports':
                return [$dashboardCrumb, ['label' => 'Reports', 'url' => null]];

            case 'crm.settings':
                return [$dashboardCrumb, ['label' => 'Settings', 'url' => null]];

            case 'crm.notifications':
                return [$dashboardCrumb, ['label' => 'Notifications', 'url' => null]];
            case 'crm.users':
                return [$dashboardCrumb, ['label' => 'Users', 'url' => null]];

            default:
                return [['label' => 'Dashboard', 'url' => null]];
        }
    }
    /**
     * Handle Shopify OAuth callback and exchange code for access token
     */
    private function resolveApiUser()
    {
        return auth()->user() ?? auth('sanctum')->user();
    }
    /**
     * POST /api/v1/orders/{order_id}/items
     * Create order items for a specific order
     */
    private function resolveNotificationIcon(string $type, string $status = ''): string
    {
        if ($type === 'shipment') {
            return 'S';
        }

        if ($type === 'job') {
            return 'J';
        }

        if (in_array($status, ['failed', 'exception', 'cancelled'], true)) {
            return 'E';
        }

        return 'O';
    }
    /**
     * POST /api/v1/orders
     * Create or update an order record based on Shopify webhook data
     */
    private function buildNotificationPool(int $limit = 60): \Illuminate\Support\Collection
    {
        $limit = max(1, min($limit, 300));
        $cacheKey = 'crm.notification_pool.' . $limit;

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($limit) {
            $orderNotifications = Order::with('shop')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($order) {
                    $title = 'Order #' . ($order->order_number ?: $order->id);
                    $shopDomain = $order->shop->shop_domain ?? 'Unknown store';
                    $status = strtolower((string) $order->fulfillment_status);
                    return [
                        'id' => 'order_' . $order->id,
                        'entity_type' => 'order',
                        'entity_id' => (int) $order->id,
                        'avatar' => $this->resolveNotificationIcon('order', $status),
                        'title' => $title,
                        'message' => 'Order update from ' . $shopDomain,
                        'status' => $status !== '' ? $status : 'pending',
                        'text_html' => '<strong>' . e($title) . '</strong> - ' . e('Order update from ' . $shopDomain),
                        'url' => route('crm.order-detail', $order->id),
                        'created_at' => $order->created_at,
                    ];
                });

            $jobNotifications = Job::with('shop')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($job) {
                    $status = strtoupper(str_replace('_', ' ', (string) $job->status));
                    return [
                        'id' => 'job_' . $job->id,
                        'entity_type' => 'job',
                        'entity_id' => (int) $job->id,
                        'avatar' => $this->resolveNotificationIcon('job', (string) $job->status),
                        'title' => 'Job #' . (string) $job->id,
                        'message' => $status,
                        'status' => strtolower((string) $job->status),
                        'text_html' => '<strong>Job #' . e((string) $job->id) . '</strong> - ' . e($status),
                        'url' => route('crm.job-detail', $job->id),
                        'created_at' => $job->created_at,
                    ];
                });

            $shipmentNotifications = Shipment::with('shop')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($shipment) {
                    $shipmentId = $shipment->shipment_id ?: $shipment->id;
                    $shopDomain = $shipment->shop->shop_domain ?? 'Unknown store';
                    $status = strtolower((string) $shipment->status);
                    return [
                        'id' => 'shipment_' . $shipment->id,
                        'entity_type' => 'shipment',
                        'entity_id' => (int) $shipment->id,
                        'avatar' => $this->resolveNotificationIcon('shipment', $status),
                        'title' => 'Shipment #' . $shipmentId,
                        'message' => 'Shipment update for ' . $shopDomain,
                        'status' => $status !== '' ? $status : 'pending',
                        'text_html' => '<strong>Shipment #' . e((string) $shipmentId) . '</strong> - ' . e('Shipment update for ' . $shopDomain),
                        'url' => route('crm.shipping-detail', $shipment->id),
                        'created_at' => $shipment->created_at,
                    ];
                });

            return $orderNotifications
                ->concat($jobNotifications)
                ->concat($shipmentNotifications)
                ->sortByDesc('created_at')
                ->values()
                ->take($limit);
        });
    }
    /**
     * Compute unread notifications count based on user's last read timestamp
     */
    private function computeUnreadCount(?Carbon $readAt): int
    {
        $cacheKey = 'crm.unread_count.' . ($readAt ? $readAt->timestamp : 'all');

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($readAt) {
            if (!$readAt) {
                return Order::count() + Job::count() + Shipment::count();
            }

            return Order::where('created_at', '>', $readAt)->count()
                + Job::where('created_at', '>', $readAt)->count()
                + Shipment::where('created_at', '>', $readAt)->count();
        });
    }
    /**
     * Build header notifications for the CRM dashboard
     */
    private function buildHeaderNotifications(int $limit = 5): array
    {
        $user = $this->resolveApiUser();
        $readAt = $user ? $user->notification_last_read_at : null;
        $items = $this->buildNotificationPool(max(20, $limit * 3))
            ->take($limit)
            ->map(function ($item) use ($readAt) {
                $createdAt = $item['created_at'];
                $isRead = $readAt ? $createdAt->lte($readAt) : false;
                return [
                    'id' => $item['id'],
                    'avatar' => $item['avatar'],
                    'title' => $item['title'],
                    'message' => $item['message'],
                    'status' => $item['status'],
                    'is_read' => $isRead,
                    'text_html' => $item['text_html'],
                    'url' => $item['url'],
                    'created_at' => optional($item['created_at'])->toDateTimeString(),
                    'time_ago' => $item['created_at'] ? $item['created_at']->diffForHumans() : '',
                ];
            })
            ->values()
            ->toArray();

        return [
            'notifications' => $items,
            'unread_count' => $this->computeUnreadCount($readAt),
        ];
    }
    /**
     * Handle Shopify OAuth callback and exchange code for access token
     */
    private function parseReportFilters(Request $request): array
    {
        $reportsConfig = $this->getFeatures()['reports'] ?? [];
        $allowedReportTypes = $reportsConfig['available_reports'] ?? ['sales', 'fulfillment', 'inventory', 'performance'];
        if (empty($allowedReportTypes)) {
            $allowedReportTypes = ['sales', 'fulfillment', 'inventory', 'performance'];
        }

        $fromInput = (string) $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $toInput = (string) $request->query('to', Carbon::now()->toDateString());

        try {
            $fromDate = Carbon::parse($fromInput)->startOfDay();
        } catch (\Throwable $e) {
            $fromDate = Carbon::now()->subDays(29)->startOfDay();
        }

        try {
            $toDate = Carbon::parse($toInput)->endOfDay();
        } catch (\Throwable $e) {
            $toDate = Carbon::now()->endOfDay();
        }

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        $reportType = strtolower((string) $request->query('report_type', $allowedReportTypes[0]));
        if (!in_array($reportType, $allowedReportTypes, true)) {
            $reportType = $allowedReportTypes[0];
        }

        $shopId = $request->query('shop_id');
        $shopId = is_numeric($shopId) ? (int) $shopId : null;
        if ($shopId !== null && !Shop::where('id', $shopId)->exists()) {
            $shopId = null;
        }

        $fulfillmentStatus = trim((string) $request->query('fulfillment_status', ''));
        $jobType = trim((string) $request->query('job_type', ''));

        return [
            'from_input' => $fromDate->toDateString(),
            'to_input' => $toDate->toDateString(),
            'from' => $fromDate,
            'to' => $toDate,
            'report_type' => $reportType,
            'allowed_report_types' => $allowedReportTypes,
            'shop_id' => $shopId,
            'fulfillment_status' => $fulfillmentStatus,
            'job_type' => $jobType,
        ];
    }
    /**
     * Build data for reports based on filters
     */
    private function buildReportsData(Request $request): array
    {
        $filters = $this->parseReportFilters($request);
        $from = $filters['from'];
        $to = $filters['to'];

        $ordersQuery = Order::query()->whereBetween('orders.created_at', [$from, $to]);
        $jobsQuery = Job::query()->whereBetween('created_at', [$from, $to]);

        if (!empty($filters['shop_id'])) {
            $ordersQuery->where('shop_id', $filters['shop_id']);
            $jobsQuery->where('shop_id', $filters['shop_id']);
        }

        if ($filters['fulfillment_status'] !== '') {
            $ordersQuery->where('fulfillment_status', $filters['fulfillment_status']);
        }

        if ($filters['job_type'] !== '') {
            $jobsQuery->where('job_type', $filters['job_type']);
        }

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (float) ((clone $ordersQuery)->sum('total_price') ?? 0);

        $fulfilledOrders = (clone $ordersQuery)
            ->whereIn('fulfillment_status', ['fulfilled', 'shipped', 'delivered'])
            ->count();

        $pendingOrders = (clone $ordersQuery)
            ->where(function ($query) {
                $query->whereNull('fulfillment_status')
                    ->orWhereIn('fulfillment_status', ['new', 'pending', 'artwork_needed', 'in_production', 'processing']);
            })
            ->count();

        $exceptionOrders = (clone $ordersQuery)
            ->whereIn('fulfillment_status', ['failed', 'exception', 'cancelled'])
            ->count();

        $totalJobs = (clone $jobsQuery)->count();
        $jobsCompleted = (clone $jobsQuery)->whereIn('status', ['completed', 'shipped'])->count();
        $jobsInProgress = (clone $jobsQuery)->whereIn('status', ['pending', 'artwork_needed', 'in_production'])->count();
        $jobsFailed = (clone $jobsQuery)->whereIn('status', ['failed', 'exception', 'cancelled'])->count();

        $avgOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;
        $fulfillmentRate = $totalOrders > 0 ? (($fulfilledOrders / $totalOrders) * 100) : 0;

        $ordersByStore = (clone $ordersQuery)
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->selectRaw("
                shops.id as shop_id,
                shops.shop_domain as shop_domain,
                COUNT(orders.id) as total_orders,
                SUM(CASE WHEN LOWER(COALESCE(orders.fulfillment_status, '')) IN ('fulfilled', 'shipped', 'delivered') THEN 1 ELSE 0 END) as fulfilled_orders,
                SUM(CASE WHEN orders.fulfillment_status IS NULL OR LOWER(orders.fulfillment_status) IN ('new', 'pending', 'artwork_needed', 'in_production', 'processing') THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN LOWER(COALESCE(orders.fulfillment_status, '')) IN ('failed', 'exception', 'cancelled') THEN 1 ELSE 0 END) as exception_orders,
                COALESCE(SUM(orders.total_price), 0) as revenue
            ")
            ->groupBy('shops.id', 'shops.shop_domain')
            ->orderByDesc('total_orders')
            ->paginate(10, ['*'], 'stores_page')
            ->appends($request->query());

        $statusDistribution = (clone $ordersQuery)
            ->selectRaw("COALESCE(NULLIF(fulfillment_status, ''), 'unknown') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $jobTypeDistribution = (clone $jobsQuery)
            ->selectRaw("COALESCE(NULLIF(job_type, ''), 'unknown') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $topStoresByRevenue = (clone $ordersQuery)
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->selectRaw('shops.shop_domain, COALESCE(SUM(orders.total_price), 0) as revenue, COUNT(orders.id) as total_orders')
            ->groupBy('shops.id', 'shops.shop_domain')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $timelineRaw = (clone $ordersQuery)
            ->selectRaw('DATE(created_at) as report_date, COUNT(*) as total_orders, COALESCE(SUM(total_price), 0) as revenue')
            ->groupBy('report_date')
            ->pluck('total_orders', 'report_date');

        $timelineRevenueRaw = (clone $ordersQuery)
            ->selectRaw('DATE(created_at) as report_date, COALESCE(SUM(total_price), 0) as revenue')
            ->groupBy('report_date')
            ->pluck('revenue', 'report_date');

        $daysDiff = $from->diffInDays($to);
        $timelineLabels = [];
        $timelineValues = [];
        $timelineRevenueValues = [];
        $cursor = $from->copy();
        $maxPoints = 31;

        if ($daysDiff <= $maxPoints) {
            while ($cursor->lte($to)) {
                $key = $cursor->toDateString();
                $timelineLabels[] = $cursor->format('M d');
                $timelineValues[] = (int) ($timelineRaw[$key] ?? 0);
                $timelineRevenueValues[] = (float) ($timelineRevenueRaw[$key] ?? 0);
                $cursor->addDay();
            }
        } else {
            $step = (int) ceil(($daysDiff + 1) / $maxPoints);
            while ($cursor->lte($to)) {
                $periodEnd = $cursor->copy()->addDays($step - 1)->endOfDay();
                if ($periodEnd->gt($to)) {
                    $periodEnd = $to->copy();
                }

                $bucketOrders = (clone $ordersQuery)
                    ->whereBetween('orders.created_at', [$cursor->copy()->startOfDay(), $periodEnd])
                    ->count();
                $bucketRevenue = (float) ((clone $ordersQuery)
                    ->whereBetween('orders.created_at', [$cursor->copy()->startOfDay(), $periodEnd])
                    ->sum('total_price') ?? 0);

                $timelineLabels[] = $cursor->format('M d') . ' - ' . $periodEnd->format('M d');
                $timelineValues[] = $bucketOrders;
                $timelineRevenueValues[] = $bucketRevenue;
                $cursor->addDays($step);
            }
        }

        $orderItemsQuery = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$from, $to]);

        if (!empty($filters['shop_id'])) {
            $orderItemsQuery->where('orders.shop_id', $filters['shop_id']);
        }

        $topProducts = (clone $orderItemsQuery)
            ->selectRaw("
                COALESCE(NULLIF(order_items.sku, ''), NULLIF(order_items.title, ''), 'unknown') as sku_label,
                SUM(order_items.quantity) as total_qty,
                COALESCE(SUM(order_items.quantity * COALESCE(order_items.price, 0)), 0) as total_value
            ")
            ->groupBy('sku_label')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $productTypePerformance = (clone $orderItemsQuery)
            ->selectRaw("
                COALESCE(NULLIF(order_items.dtfta_type, ''), 'unknown') as label,
                SUM(order_items.quantity) as total_qty
            ")
            ->groupBy('label')
            ->orderByDesc('total_qty')
            ->get();

        $exceptionJobs = (clone $jobsQuery)
            ->with(['shop', 'order'])
            ->whereIn('status', ['failed', 'exception', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'exceptions_page')
            ->appends($request->query());

        return [
            'filters' => $filters,
            'metrics' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'fulfilled_orders' => $fulfilledOrders,
                'pending_orders' => $pendingOrders,
                'exception_orders' => $exceptionOrders,
                'avg_order_value' => $avgOrderValue,
                'fulfillment_rate' => $fulfillmentRate,
                'total_jobs' => $totalJobs,
                'jobs_completed' => $jobsCompleted,
                'jobs_in_progress' => $jobsInProgress,
                'jobs_failed' => $jobsFailed,
            ],
            'ordersByStore' => $ordersByStore,
            'statusDistribution' => $statusDistribution,
            'jobTypeDistribution' => $jobTypeDistribution,
            'topStoresByRevenue' => $topStoresByRevenue,
            'timeline' => [
                'labels' => $timelineLabels,
                'orders' => $timelineValues,
                'revenue' => $timelineRevenueValues,
            ],
            'topProducts' => $topProducts,
            'productTypePerformance' => $productTypePerformance,
            'exceptionJobs' => $exceptionJobs,
            'availableShops' => Shop::orderBy('shop_domain')->get(['id', 'shop_domain']),
            'availableFulfillmentStatuses' => Order::whereNotNull('fulfillment_status')
                ->distinct()
                ->orderBy('fulfillment_status')
                ->pluck('fulfillment_status'),
            'availableJobTypes' => Job::whereNotNull('job_type')
                ->distinct()
                ->orderBy('job_type')
                ->pluck('job_type'),
        ];
    }

    /**
     * Show the dashboard.
     */
    public function dashboard(): View
    {
        $data = $this->prepareViewData();
        $data['dashboardConfig'] = $this->getFeatures()['dashboard'] ?? [];

        $today = carbon::now()->startOfDay();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();

        $data['stats'] = [
            'totalOrders' => Order::count(),
            'ordersToday' => Order::whereDate('created_at', $today)->count(),
            'ordersThisWeek' => Order::whereBetween('created_at', [$weekStart, Carbon::now()])->count(),
            'pendingJobs' => Job::where('status', 'pending')->count(),
            'inProduction' => Job::where('status', 'in_production')->count(),
            'shippedThisMonth' => Order::where('fulfillment_status', 'fulfilled')
                ->whereBetween('created_at', [$monthStart, Carbon::now()])
                ->count(),
            'exceptions' => Job::where(function ($query) {
                $query->where('status', 'failed')
                    ->orWhere('status', 'exception')
                    ->orWhere('status', 'cancelled');
            })->count(),
        ];

        $data['recentActivity'] = AdminActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(5, ['*'], 'activity_page');

        $ordersByDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Order::whereDate('created_at', $date)->count();
            $ordersByDay[] = [
                'label' => $date->format('M d'),
                'count' => $count
            ];
        }
        $data['ordersByDay'] = collect($ordersByDay);

        $data['ordersByShop'] = Order::selectRaw('shops.shop_domain, count(orders.id) as count')
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->groupBy('shops.id', 'shops.shop_domain')
            ->limit(5)
            ->get();

        $data['jobTypeDistribution'] = Job::selectRaw('job_type, count(*) as count')
            ->groupBy('job_type')
            ->get();

        return view('crm.dashboard', $data);
    }

    /**
     * Public landing / login page.
     */
    public function landing()
    {
        return view('login');
    }

    /**
     * Forgot password page.
     */
    public function forgotPassword(): View
    {
        return view('forgot-password');
    }

    /**
     * Reset password page (from email link; shows form to set new password).
     */
    public function resetPassword(): View
    {
        return view('reset-password', [
            'email' => request('email', ''),
            'token' => request('token', ''),
        ]);
    }

    /**
     * Show orders/jobs list.
     */
    public function orders(): View
    {
        $data = $this->prepareViewData();
        $data['ordersConfig'] = $this->getFeatures()['orders'] ?? [];
        $data['orderStatuses'] = $this->getStatuses()['order_statuses'] ?? [];
    
        $statuses = ['pending', 'artwork_needed', 'in_production', 'shipped', 'cancelled'];
        $allowedStatuses = [
            'new',
            'accepted',
            'pending',
            'artwork_needed',
            'in_production',
            'processing',
            'shipped',
            'cancelled',
            'failed',
            'exception',
            'billing_pending',
            'billing_required',
            'billing_issue',
            'payment_pending',
            'payment_required',
            'issue',
        ];
    
        $jobsByStatus = [];
        foreach ($statuses as $status) {
            $jobsByStatus[$status] = collect();
        }
    
        $jobsForBoard = Job::query()
            ->with(['shop', 'order'])
            ->whereIn('status', $allowedStatuses)
            ->orderByDesc('created_at')
            ->get();
    
        foreach ($jobsForBoard as $job) {
            $rawStatus = strtolower((string) $job->status);
            $bucket = match (true) {
                in_array($rawStatus, ['new', 'accepted', 'pending', 'billing_pending', 'billing_required', 'billing_issue', 'payment_pending', 'payment_required', 'issue'], true) => 'pending',
                str_contains($rawStatus, 'billing') || str_contains($rawStatus, 'issue') => 'pending',
                in_array($rawStatus, ['processing', 'in_production'], true) => 'in_production',
                in_array($rawStatus, ['failed', 'exception', 'cancelled'], true) => 'cancelled',
                default => $rawStatus,
            };
    
            if (!isset($jobsByStatus[$bucket])) {
                continue;
            }
    
            $jobsByStatus[$bucket]->push($job);
        }
    
        $data['jobsByStatus'] = $jobsByStatus;
    
        $data['allJobs'] = Job::query()
            ->with(['shop', 'order'])
            ->whereIn('status', $allowedStatuses)
            ->orderByDesc('created_at')
            ->paginate(15);
    
        $data['stores'] = Shop::pluck('shop_domain');
        $data['productTypes'] = Job::whereNotNull('job_type')->distinct()->pluck('job_type');
    
        return view('crm.orders', $data);
    }

    /**
     * Show job detail page.
     */
    public function jobDetail(string $jobId): View
    {
        $data = $this->prepareViewData();
        $data['jobId'] = $jobId;
        $data['orderStatuses'] = $this->getStatuses()['order_statuses'] ?? [];

        $job = Job::with('shop', 'order', 'order.orderItems')->findOrFail($jobId);
        $data['job'] = $job;

        $order = $job->order;
        $data['order'] = $order;
        $latestBillingCharge = $order
            ? BillingCharge::query()
                ->where('order_id', $order->id)
                ->where('charge_type', 'pre_fulfillment')
                ->latest('id')
                ->first()
            : null;
        $billingChargeStatus = strtolower((string) ($latestBillingCharge->status ?? ''));
        $data['billingChargeStatus'] = $billingChargeStatus !== '' ? $billingChargeStatus : null;
        $data['showRetryBilling'] = in_array($billingChargeStatus, ['pending', 'failed'], true);

        $data['orderItems'] = $order->orderItems;

        $data['activityLog'] = AdminActivityLog::where('model_type', 'Job')
            ->where('model_id', $jobId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('crm.order-detail', $data);
    }

    /**
     * Show shipping & fulfillment page.
     */
    public function shipping(): View
    {
        $data = $this->prepareViewData();
        $data['shippingConfig'] = $this->getFeatures()['shipping'] ?? [];
        $data['shipmentStatuses'] = $this->getStatuses()['shipment_statuses'] ?? [];
        $data['ordersForShipping'] = Order::with('shop')->orderBy('created_at', 'desc')->limit(100)->get();
        $data['shopsForShipping'] = Shop::orderBy('shop_domain')->get();
        $data['servicesForShipping'] = FulfillmentService::orderBy('name')->get();
        $data['profilesForShipping'] = PartnerProfile::get()->keyBy('shop_id');
        $data['recentShipments'] = Shipment::with(['order', 'shop'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        return view('crm.shipping', $data);
    }

    /**
     * Show shipment detail page.
     */
    public function shippingDetail(string $shipmentId): View
    {
        $data = $this->prepareViewData();
        $data['shippingConfig'] = $this->getFeatures()['shipping'] ?? [];
        $data['shipmentStatuses'] = $this->getStatuses()['shipment_statuses'] ?? [];

        $shipment = Shipment::with(['order.shop', 'shop', 'fulfillmentService', 'job'])->findOrFail($shipmentId);
        $data['shipment'] = $shipment;
        $data['shopProfile'] = $shipment->shop_id
            ? PartnerProfile::where('shop_id', $shipment->shop_id)->first()
            : null;

        return view('crm.shipping-detail', $data);
    }

    /**
     * Show stores & partners page.
     */
    public function stores(Request $request): View
    {
        $data = $this->prepareViewData();
        $data['storesConfig'] = $this->getFeatures()['stores'] ?? [];

        $statusOptions = $this->getStoreStatusOptions();
        $perPage = (int) ($request->integer('per_page') ?: ($data['storesConfig']['items_per_page'] ?? 10));
        $perPage = max(5, min($perPage, 100));

        $query = Shop::query()
            ->with('partnerProfile')
            ->withCount([
                'orders as total_orders_count',
                'jobs as pending_jobs_count' => function ($jobQuery) {
                    $jobQuery->whereIn('status', ['pending', 'artwork_needed', 'in_production']);
                },
            ]);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('shop_domain', 'like', '%' . $search . '%')
                    ->orWhereHas('partnerProfile', function ($profileQuery) use ($search) {
                        $profileQuery->where('brand_name', 'like', '%' . $search . '%')
                            ->orWhere('support_email', 'like', '%' . $search . '%');
                    });
            });
        }

        $statusFilter = strtolower((string) $request->query('status', 'all'));
        if (in_array($statusFilter, $statusOptions, true)) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }

        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc'));
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'shop_domain', 'status', 'installed_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $sortDir);

        $stores = $query
            ->paginate($perPage)
            ->appends($request->query());

        $data['stores'] = $stores;
        $data['storeStatusOptions'] = $statusOptions;
        $data['filters'] = [
            'search' => $search,
            'status' => $statusFilter,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'per_page' => $perPage,
        ];

        return view('crm.stores', $data);
    }

    /**
     * Show products management page.
     */
    public function products(Request $request): View
    {
        $data = $this->prepareViewData();
        $data['productsConfig'] = $this->getFeatures()['products'] ?? [];
    
        $statusOptions = $this->getProductStatusOptions();
        $perPage = (int) ($request->integer('per_page') ?: ($data['productsConfig']['items_per_page'] ?? 10));
        $perPage = max(5, min($perPage, 100));
    
        $query = Product::query()
            ->with(['variants', 'printAreas']);
    
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('title', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%')
                    ->orWhere('model_code', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhereHas('variants', function ($variantQuery) use ($search) {
                        $variantQuery->where('sku', 'like', '%' . $search . '%')
                            ->orWhere('color', 'like', '%' . $search . '%')
                            ->orWhere('size', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('printAreas', function ($printAreaQuery) use ($search) {
                        $printAreaQuery->where('title', 'like', '%' . $search . '%');
                    });
            });
        }
    
        $statusFilter = strtolower((string) $request->query('status', 'all'));
        if (in_array($statusFilter, $statusOptions, true)) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }
    
        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'title', 'status', 'brand', 'category', 'model_code'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
    
        $products = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->appends($request->query());
    
        $data['products'] = $products;
        $data['productStatusOptions'] = $statusOptions;
        $data['filters'] = [
            'search' => $search,
            'status' => $statusFilter,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
            'per_page' => $perPage,
        ];
    
        return view('crm.products-index', $data);
    }

    /**
     * Show add product page.
     */
    public function createProduct(): View
    {
        $data = $this->prepareViewData();
        $data['printAreas'] = PrintArea::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();
    
        $data['productStatusOptions'] = $this->getProductStatusOptions();
    
        return view('crm.products-add', $data);
    }

    /**
     * Show edit product page.
     */
    public function editProduct(string $productId): View
    {
        $data = $this->prepareViewData();
        $data['product'] = Product::with(['variants', 'printAreas'])->findOrFail($productId);
        $data['printAreas'] = PrintArea::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();
    
        $data['productStatusOptions'] = $this->getProductStatusOptions();
        $data['selectedColors'] = $data['product']->variants->pluck('color')->unique()->values();
        $data['selectedSizes'] = $data['product']->variants->pluck('size')->unique()->values();
    
        return view('crm.products-edit', $data);
    }

    /**
     * Show product detail page.
     */
    public function viewProduct(string $productId): View
    {
        $data = $this->prepareViewData();
        $data['product'] = Product::with(['variants', 'printAreas'])->findOrFail($productId);
        $data['printAreas'] = PrintArea::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();
    
        return view('crm.products-view', $data);
    }

    /**
     * Create product from CRM page.
     */
    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model_code' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,draft,archived,inactive',
            'price' => 'required|numeric|min:0',
    
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|max:5120',
    
            'colors' => 'required|array|min:1',
            'colors.*' => 'required|string|max:100',
    
            'sizes' => 'required|array|min:1',
            'sizes.*' => 'required|string|max:100',
    
            'print_area_ids' => 'nullable|array',
            'print_area_ids.*' => 'integer|exists:print_areas,id',
        ]);
    
        $product = null;
    
        DB::transaction(function () use ($request, $validated, &$product) {
            // $imagePaths = [];
    
            // if ($request->hasFile('images')) {
            //     foreach ((array) $request->file('images') as $file) {
            //         if ($file) {
            //             $imagePaths[] = $file->store('products/images', 'public');
            //         }
            //     }
            // }
    
            $product = Product::create([
                'title' => $validated['title'],
                'brand' => $validated['brand'] ?? null,
                'model_code' => $validated['model_code'] ?? null,
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'price' => $validated['price'],
                'images' => $validated['images'] ?: null,
            ]);
    
            $colors = collect($validated['colors'])
                ->map(fn ($color) => trim($color))
                ->filter()
                ->unique()
                ->values();
    
            $sizes = collect($validated['sizes'])
                ->map(fn ($size) => trim($size))
                ->filter()
                ->unique()
                ->values();
    
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $product->variants()->create([
                        'sku' => $this->generateProductVariantSku($product, $color, $size),
                        'color' => $color,
                        'size' => $size,
                        'price' => $validated['price'],
                        'is_active' => true,
                    ]);
                }
            }
    
            $product->printAreas()->sync($validated['print_area_ids'] ?? []);
        });
    
        AdminActivityLog::logActivity(
            auth()->id(),
            'Created Product',
            'Product',
            $product->id
        );
    
        return redirect()
            ->route('crm.products')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Update product from CRM page.
     */
    public function updateProduct(Request $request, string $productId): RedirectResponse
    {
        $product = Product::with(['variants', 'printAreas'])->findOrFail($productId);
    
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model_code' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,draft,archived,inactive',
            'price' => 'required|numeric|min:0',
    
            'images' => 'nullable|array',
            'images.*' => 'nullable|string|max:5120',
          //  'remove_existing_images' => 'nullable|boolean',
    
            'colors' => 'required|array|min:1',
            'colors.*' => 'required|string|max:100',
    
            'sizes' => 'required|array|min:1',
            'sizes.*' => 'required|string|max:100',
            'variant_prices' => 'nullable|array',
            'variant_prices.*' => 'nullable|numeric|min:0',
    
            'print_area_ids' => 'nullable|array',
            'print_area_ids.*' => 'integer|exists:print_areas,id',
        ]);
    
        DB::transaction(function () use ($request, $validated, $product) {
            // $existingImages = is_array($product->images) ? $product->images : [];
    
            // if ($request->boolean('remove_existing_images')) {
            //     foreach ($existingImages as $path) {
            //         Storage::disk('public')->delete((string) $path);
            //     }
            //     $existingImages = [];
            // }
    
            // $newImagePaths = [];
            // if ($request->hasFile('images')) {
            //     foreach ((array) $request->file('images') as $file) {
            //         if ($file) {
            //             $newImagePaths[] = $file->store('products/images', 'public');
            //         }
            //     }
            // }
    
            // $finalImages = array_values(array_filter(array_merge($existingImages, $newImagePaths)));
    
            $product->update([
                'title' => $validated['title'],
                'brand' => $validated['brand'] ?? null,
                'model_code' => $validated['model_code'] ?? null,
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'price' => $validated['price'],
                'images' => $validated['images'] ?: null,
            ]);
    
            $colors = collect($validated['colors'])
                ->map(fn ($color) => trim($color))
                ->filter()
                ->unique()
                ->values();
    
            $sizes = collect($validated['sizes'])
                ->map(fn ($size) => trim($size))
                ->filter()
                ->unique()
                ->values();
    
            $desiredCombinations = [];
    
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $desiredCombinations[] = [
                        'color' => $color,
                        'size' => $size,
                    ];
                }
            }
    
            $existingVariants = $product->variants->keyBy(function ($variant) {
                return strtolower($variant->color . '|' . $variant->size);
            });
    
            $keptVariantIds = [];
    
            foreach ($desiredCombinations as $combo) {
                $key = strtolower($combo['color'] . '|' . $combo['size']);
    
                if ($existingVariants->has($key)) {
                    $variant = $existingVariants->get($key);
                    $variantPrice = data_get($validated, 'variant_prices.' . $variant->id);
                    $variant->update([
                        'is_active' => true,
                        'price' => $variantPrice !== null ? $variantPrice : ($variant->price ?? $validated['price']),
                    ]);
                } else {
                    $variant = $product->variants()->create([
                        'sku' => $this->generateProductVariantSku($product, $combo['color'], $combo['size']),
                        'color' => $combo['color'],
                        'size' => $combo['size'],
                        'price' => $validated['price'],
                        'is_active' => true,
                    ]);
                }
    
                $keptVariantIds[] = $variant->id;
            }
    
            $product->variants()
                ->whereNotIn('id', $keptVariantIds)
                ->delete();
    
            $product->printAreas()->sync($validated['print_area_ids'] ?? []);
        });
    
        AdminActivityLog::logActivity(
            auth()->id(),
            'Updated Product',
            'Product',
            $product->id
        );
    
        return redirect()
            ->route('crm.products')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Generate product variant SKU.
     */
    private function generateProductVariantSku(Product $product, string $color, string $size): string
    {
        $brandNames = collect(config('dtfta.brand_names', []));
        $commonColors = collect(config('dtfta.common_colors', []));

        $brandCode = $brandNames->first(function ($item) use ($product) {
            return strtolower((string) ($item['name'] ?? '')) === strtolower((string) $product->brand);
        });

        $colorCode = $commonColors->first(function ($item) use ($color) {
            return strtolower((string) ($item['name'] ?? '')) === strtolower((string) $color);
        });

        $titlePart = strtoupper($product->category);
        $brandPart = strtoupper($brandCode['code']);
        $modelPart = strtoupper($product->model_code);
        $colorPart = strtoupper($colorCode['code']);
        $sizePart = strtoupper($size);

        $baseSku = collect([
            'DTFTA-APP',
            $titlePart ?: 'PROD',
            $brandPart ?: 'GEN',
            $modelPart ?: 'NA',
            $colorPart ?: 'CLR',
            $sizePart ?: 'SIZE',
        ])->implode('-');

        $sku = $baseSku;
        $counter = 1;

        while (
            ProductVariant::where('sku', $sku)->exists()
        ) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }                   

    /**
     * Delete product from CRM page.
     */
    public function destroyProduct(string $productId): RedirectResponse
    {
        $product = Product::with(['variants', 'printAreas'])->findOrFail($productId);
    
        $images = is_array($product->images) ? $product->images : [];
        foreach ($images as $imagePath) {
            Storage::disk('public')->delete((string) $imagePath);
        }
    
        $product->printAreas()->detach();
        $product->delete();
    
        AdminActivityLog::logActivity(
            auth()->id(),
            'Deleted Product',
            'Product',
            $product->id
        );
    
        return redirect()
            ->route('crm.products')
            ->with('success', 'Product deleted successfully.');
    }

    private function normalizeTags(?string $tags): ?array
    {
        if ($tags === null || trim($tags) === '') {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $tags)), fn($tag) => $tag !== ''));
        return empty($parts) ? null : $parts;
    }

    private function storeGalleryImages(Request $request): array
    {
        $paths = [];
        if (!$request->hasFile('gallery_images')) {
            return $paths;
        }

        foreach ((array) $request->file('gallery_images') as $file) {
            if ($file) {
                $paths[] = $file->store('products/gallery', 'public');
            }
        }

        return $paths;
    }

  

    /**
     * Show reports & analytics page.
     */
    public function reports(Request $request): View
    {
        $data = $this->prepareViewData();
        $data['reportsConfig'] = $this->getFeatures()['reports'] ?? [];
        $reportsData = $this->buildReportsData($request);
        $data = array_merge($data, $reportsData);
        return view('crm.reports', $data);
    }
    /**
     * Export current report data as CSV.
     */
    public function exportReportsCsv(Request $request): StreamedResponse
    {
        $reportsData = $this->buildReportsData($request);
        $rows = $reportsData['ordersByStore']->items();
        $filters = $reportsData['filters'];

        $fileName = 'reports_' . $filters['from']->format('Ymd') . '_to_' . $filters['to']->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Store Domain', 'Total Orders', 'Fulfilled', 'Pending', 'Exceptions', 'Revenue']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->shop_domain,
                    (int) $row->total_orders,
                    (int) $row->fulfilled_orders,
                    (int) $row->pending_orders,
                    (int) $row->exception_orders,
                    number_format((float) $row->revenue, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Show settings page.
     */
    public function settings(): View
    {
        $data = $this->prepareViewData();
        $data['settingsConfig'] = $this->getFeatures()['settings'] ?? [];
        return view('crm.settings', $data);
    }

    /**
     * Show notifications page.
     */
    public function notifications(): View
    {
        $data = $this->prepareViewData();
        $data['notificationsConfig'] = $this->getFeatures()['notifications'] ?? [];
        return view('crm.notifications', $data);
    }


    /**
     * Show users management page.
     */
    public function users(): View
    {
        $data = $this->prepareViewData();
        return view('crm.users', $data);
    }

    /**
     * Show store detail page.
     */
    public function storedetails(Request $request, string $storeId): View
    {
        $data = $this->prepareViewData();
        $statusOptions = $this->getStoreStatusOptions();

        $store = Shop::with('partnerProfile')
            ->withCount([
                'orders as total_orders_count',
                'jobs as pending_jobs_count' => function ($jobQuery) {
                    $jobQuery->whereIn('status', ['pending', 'artwork_needed', 'in_production']);
                },
            ])
            ->findOrFail($storeId);

        $ordersQuery = Order::withCount(['orderItems', 'jobs'])
            ->where('shop_id', $store->id)
            ->orderBy('created_at', 'desc');

        $orderStatusFilter = trim((string) $request->query('order_status', ''));
        if ($orderStatusFilter !== '') {
            $ordersQuery->where('fulfillment_status', $orderStatusFilter);
        }

        $orders = $ordersQuery->paginate(10)->appends($request->query());

        $orderStatuses = Order::where('shop_id', $store->id)
            ->whereNotNull('fulfillment_status')
            ->distinct()
            ->pluck('fulfillment_status')
            ->filter()
            ->values();

        $storeOrdersBaseQuery = Order::where('shop_id', $store->id);
        $totalRevenue = (float) ((clone $storeOrdersBaseQuery)->sum('total_price') ?? 0);
        $revenueLast30Days = (float) ((clone $storeOrdersBaseQuery)
            ->whereBetween('orders.created_at', [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay()])
            ->sum('total_price') ?? 0);
        $fulfilledOrders = (clone $storeOrdersBaseQuery)
            ->whereIn('fulfillment_status', ['fulfilled', 'shipped', 'delivered'])
            ->count();
        $avgOrderValue = ($store->total_orders_count ?? 0) > 0
            ? ($totalRevenue / (int) $store->total_orders_count)
            : 0;

        $data['store'] = $store;
        $data['partnerProfile'] = $store->partnerProfile;
        $data['orders'] = $orders;
        $data['orderStatusOptions'] = $orderStatuses;
        $data['orderStatusFilter'] = $orderStatusFilter;
        $data['storeStatusOptions'] = $statusOptions;
        $data['storeMetrics'] = [
            'total_revenue' => $totalRevenue,
            'revenue_last_30_days' => $revenueLast30Days,
            'fulfilled_orders' => $fulfilledOrders,
            'avg_order_value' => $avgOrderValue,
        ];

        return view('crm.store-detail', $data);
    }
    /**
     * Update store status (active, suspended, uninstalled)
     */
    public function updateStoreStatus(Request $request, string $storeId): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,uninstalled',
        ]);

        $store = Shop::findOrFail($storeId);
        $store->status = $validated['status'];
        if ($validated['status'] === 'uninstalled' && empty($store->uninstalled_at)) {
            $store->uninstalled_at = now();
        }
        if ($validated['status'] !== 'uninstalled') {
            $store->uninstalled_at = null;
        }
        $store->save();

        AdminActivityLog::logActivity(
            auth()->id(),
            'Updated Store Status to ' . $validated['status'],
            'Shop',
            $store->id
        );

        return redirect()
            ->route('crm.store-detail', $store->id)
            ->with('success', 'Store status updated successfully.');
    }
    /**
     * Create or update partner profile for a store
     */
    public function upsertPartnerProfile(Request $request, string $storeId): RedirectResponse
    {
        $validated = $request->validate([
            'brand_name' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:40',
            'return_address_street' => 'nullable|string|max:255',
            'return_address_city' => 'nullable|string|max:100',
            'return_address_state' => 'nullable|string|max:100',
            'return_address_zip' => 'nullable|string|max:20',
            'return_address_country' => 'nullable|string|max:100',
        ]);

        $store = Shop::findOrFail($storeId);
        PartnerProfile::updateOrCreate(
            ['shop_id' => $store->id],
            $validated
        );

        AdminActivityLog::logActivity(
            auth()->id(),
            'Updated Partner Profile',
            'Shop',
            $store->id
        );

        return redirect()
            ->route('crm.store-detail', $store->id)
            ->with('success', 'Partner profile saved successfully.');
    }
    /**
     * Show order detail page.
     */
    public function orderdetails(string $orderId): View
    {
        $data = $this->prepareViewData();
        $order = Order::with('shop', 'orderItems', 'shipments')->findOrFail($orderId);
        $data['order'] = $order;
        $latestBillingCharge = BillingCharge::query()
            ->where('order_id', $order->id)
            ->where('charge_type', 'pre_fulfillment')
            ->latest('id')
            ->first();
        $billingChargeStatus = strtolower((string) ($latestBillingCharge->status ?? ''));
        $data['billingChargeStatus'] = $billingChargeStatus !== '' ? $billingChargeStatus : null;
        $data['showRetryBilling'] = in_array($billingChargeStatus, ['pending', 'failed'], true);

        $extractTemplateId = function (OrderItem $item): ?string {
            $properties = is_array($item->properties) ? $item->properties : [];
            $payload = is_array($item->payload) ? $item->payload : [];

            $candidates = [
                $properties['_dtfta_template_id'] ?? null,
                $properties['dtfta_template_id'] ?? null,
                $properties['templateId'] ?? null,
                data_get($payload, 'properties._dtfta_template_id'),
                data_get($payload, 'properties.dtfta_template_id'),
                data_get($payload, 'templateId'),
            ];

            foreach ($candidates as $candidate) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };

        $templateIds = $order->orderItems
            ->map(fn (OrderItem $item) => $extractTemplateId($item))
            ->filter()
            ->unique()
            ->values();

        $customProducts = CustomProduct::with([
            'variants.artworks',
            'artworks',
        ])
            ->where(function ($query) use ($templateIds) {
                $query->whereIn('id', $templateIds->all())
                    ->orWhereIn('product_key', $templateIds->all());
            })
            ->get();

        $customProductsById = $customProducts->keyBy(fn ($cp) => (string) $cp->id);
        $customProductsByKey = $customProducts->keyBy(fn ($cp) => (string) $cp->product_key);

        $data['orderItemCustomDetails'] = $order->orderItems->mapWithKeys(function (OrderItem $item) use ($extractTemplateId, $customProductsById, $customProductsByKey) {
            $properties = is_array($item->properties) ? $item->properties : [];
            $templateId = $extractTemplateId($item);
            $customProduct = $templateId ? ($customProductsById->get($templateId) ?? $customProductsByKey->get($templateId)) : null;

            $variantIdCandidate = trim((string) ($properties['_dtfta_variant_id'] ?? $properties['dtfta_variant_id'] ?? $properties['custom_product_variant_id'] ?? ''));
            $customVariant = null;

            if ($customProduct) {
                $variants = $customProduct->variants ?? collect();

                if ($variantIdCandidate !== '') {
                    $customVariant = $variants->first(function ($variant) use ($variantIdCandidate) {
                        return (string) $variant->id === $variantIdCandidate
                            || (string) $variant->shopify_variant_id === $variantIdCandidate
                            || (string) $variant->product_variant_id === $variantIdCandidate;
                    });
                }

                if (!$customVariant && $item->sku) {
                    $customVariant = $variants->first(fn ($variant) => strcasecmp((string) $variant->sku, (string) $item->sku) === 0);
                }
            }

            $artworks = $customVariant?->artworks
                ?? $customProduct?->artworks
                ?? collect();

            return [
                $item->id => [
                    'template_id' => $templateId,
                    'custom_product_id' => $customProduct?->id,
                    'product_variant_id' => $customVariant?->product_variant_id,
                    'custom_product_variant_id' => $customVariant?->id,
                    'artwork_urls' => $artworks
                        ->map(function ($artwork) {
                            $meta = is_array($artwork->meta ?? null) ? $artwork->meta : [];
                            return trim((string) (
                                $meta['custom_artwork_url']
                                ?? $meta['custom_artwork_source']
                                ?? ''
                            ));
                        })
                        ->filter()
                        ->values()
                        ->all(),
                ],
            ];
        })->all();

        $data['jobs'] = Job::where('order_id', $orderId)
            ->with('shop')
            ->get();

        $jobIdsForOrder = $data['jobs']->pluck('id')->filter()->values()->all();
        $shipmentIdsForOrder = Shipment::where('order_id', $orderId)->pluck('id')->all();

        $data['activityLog'] = AdminActivityLog::query()
            ->where(function ($query) use ($orderId, $jobIdsForOrder, $shipmentIdsForOrder) {
                $query->where(function ($orderQuery) use ($orderId) {
                    $orderQuery->where('model_type', 'Order')
                        ->where('model_id', $orderId);
                });

                if (!empty($jobIdsForOrder)) {
                    $query->orWhere(function ($jobQuery) use ($jobIdsForOrder) {
                        $jobQuery->where('model_type', 'Job')
                            ->whereIn('model_id', $jobIdsForOrder);
                    });
                }

                if (!empty($shipmentIdsForOrder)) {
                    $query->orWhere(function ($shipmentQuery) use ($shipmentIdsForOrder) {
                        $shipmentQuery->where('model_type', 'Shipment')
                            ->whereIn('model_id', $shipmentIdsForOrder);
                    });
                }
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'activity_page')
            ->appends(request()->query());

        return view('crm.order-detail', $data);
    }

    /**
     * API: Retry billing for an order
     */
    public function retryBilling(Request $request, BillingService $billingService): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
        ]);

        $order = Order::with('shop')->findOrFail((int) $validated['order_id']);
        $shop = $order->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found for this order.',
            ], 422);
        }

        $latestCharge = BillingCharge::query()
            ->where('order_id', $order->id)
            ->where('charge_type', 'pre_fulfillment')
            ->latest('id')
            ->first();

        if ($latestCharge && $latestCharge->status === 'accepted') {
            return response()->json([
                'success' => true,
                'message' => 'Billing is already paid for this order.',
                'data' => [
                    'billing_status' => 'accepted',
                    'show_retry_button' => false,
                ],
            ]);
        }

        $result = $billingService->chargePreFulfillment($shop, $order);
        $latestStatus = strtolower((string) data_get($result, 'data.billing_charge.status', ''));
        $isAccepted = ($result['success'] ?? false) === true || $latestStatus === 'accepted';

        return response()->json([
            'success' => $isAccepted,
            'message' => $result['message'] ?? ($isAccepted ? 'Billing retry succeeded.' : 'Billing retry failed.'),
            'data' => [
                'billing_status' => $latestStatus !== '' ? $latestStatus : ($isAccepted ? 'accepted' : 'failed'),
                'show_retry_button' => !$isAccepted,
            ],
            'errors' => $result['errors'] ?? [],
        ], $isAccepted ? 200 : (($result['status'] ?? 422)));
    }

    /**
     * API: Update status for job or order
     */
    public function updateStatus(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('type');
        $status = $request->input('status');
    
        $allowedStatuses = [
            'pending',
            'artwork_needed',
            'in_production',
            'shipped',
            'cancelled',
            'failed',
            'exception',
        ];
    
        if (!in_array($status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status.',
            ], 422);
        }
    
        try {
            if ($type === 'job') {
                $job = Job::findOrFail($id);
                $job->update([
                    'status' => $status,
                ]);
    
                AdminActivityLog::logActivity(
                    auth()->id(),
                    'Updated Job Status to ' . $status,
                    'Job',
                    $job->id
                );
    
                return response()->json([
                    'success' => true,
                    'message' => 'Job status updated successfully',
                    'status' => $status,
                    'job_id' => $job->id,
                ]);
            }
    
            if ($type === 'order') {
                $order = Order::findOrFail($id);
                $order->update([
                    'fulfillment_status' => $status,
                ]);
    
                AdminActivityLog::logActivity(
                    auth()->id(),
                    'Updated Order Status to ' . $status,
                    'Order',
                    $order->id
                );
    
                return response()->json([
                    'success' => true,
                    'message' => 'Order status updated successfully',
                    'status' => $status,
                    'order_id' => $order->id,
                ]);
            }
    
            return response()->json([
                'success' => false,
                'message' => 'Invalid type.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * API: Get activity log for a job or order
     */
    public function getActivityLog(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('type');
        $perPage = max(1, min((int) $request->input('per_page', 10), 50));
        $page = max(1, (int) $request->input('page', 1));

        try {
            if ($type === 'job') {
                $activities = AdminActivityLog::query()
                    ->where('model_type', 'Job')
                    ->where('model_id', $id);
            } else {
                $jobIds = Job::where('order_id', $id)->pluck('id')->all();
                $shipmentIds = Shipment::where('order_id', $id)->pluck('id')->all();
                $activities = AdminActivityLog::query()
                    ->where(function ($query) use ($id, $jobIds, $shipmentIds) {
                        $query->where(function ($orderQuery) use ($id) {
                            $orderQuery->where('model_type', 'Order')
                                ->where('model_id', $id);
                        });

                    if (!empty($jobIds)) {
                            $query->orWhere(function ($jobQuery) use ($jobIds) {
                                $jobQuery->where('model_type', 'Job')
                                    ->whereIn('model_id', $jobIds);
                            });
                        }

                        if (!empty($shipmentIds)) {
                            $query->orWhere(function ($shipmentQuery) use ($shipmentIds) {
                                $shipmentQuery->where('model_type', 'Shipment')
                                    ->whereIn('model_id', $shipmentIds);
                            });
                        }
                    });
            }

            $paginated = $activities
                ->orderBy('created_at', 'desc')
                ->with('user')
                ->paginate($perPage, ['*'], 'page', $page);

            $result = collect($paginated->items())->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'model_type' => $activity->model_type,
                    'created_at' => $activity->created_at,
                    'user' => $activity->user ? ['id' => $activity->user->id, 'name' => $activity->user->name] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'activities' => $result,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * API: Return current order status
     */
    public function getOrderStatus($orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            return response()->json(['success' => true, 'status' => $order->fulfillment_status]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    /**
     * API: Get real-time notifications for the logged-in user
     */
    public function getRealtimeNotifications(): JsonResponse
    {
        if (!$this->resolveApiUser()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $this->buildHeaderNotifications();
        return response()->json([
            'success' => true,
            'count' => count($payload['notifications']),
            'unread_count' => $payload['unread_count'],
            'notifications' => $payload['notifications'],
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
    /**
     * API: Get paginated notifications with filtering options
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = $this->resolveApiUser();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $type = strtolower((string) $request->query('type', ''));
        $readFilter = strtolower((string) $request->query('read', 'all'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $poolSize = max(120, $page * $perPage * 3);
        $readAt = $user->notification_last_read_at;

        $items = $this->buildNotificationPool($poolSize)
            ->map(function ($item) use ($readAt) {
                $createdAt = $item['created_at'];
                $item['is_read'] = $readAt ? $createdAt->lte($readAt) : false;
                $item['time_ago'] = $createdAt ? $createdAt->diffForHumans() : '';
                $item['created_at'] = optional($createdAt)->toDateTimeString();
                return $item;
            });

        if (in_array($type, ['order', 'job', 'shipment'], true)) {
            $items = $items->where('entity_type', $type);
        }

        if ($readFilter === 'unread') {
            $items = $items->where('is_read', false);
        } elseif ($readFilter === 'read') {
            $items = $items->where('is_read', true);
        }

        $items = $items->values();
        $total = $items->count();
        $pagedItems = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $pagedItems,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'unread_count' => $this->computeUnreadCount($readAt),
        ]);
    }
    /**
     * API: Mark notifications as read based on provided IDs or mark all as read
     */
    public function markNotificationsRead(Request $request): JsonResponse
    {
        $user = $this->resolveApiUser();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $notificationIds = $request->input('notification_ids', []);
        if (!is_array($notificationIds) || empty($notificationIds)) {
            $user->notification_last_read_at = now();
            $user->save();
            return response()->json(['success' => true, 'unread_count' => 0]);
        }

        $latestTimestamp = null;
        foreach ($notificationIds as $notificationId) {
            if (!is_string($notificationId) || !str_contains($notificationId, '_')) {
                continue;
            }
            [$type, $entityId] = explode('_', $notificationId, 2);
            if (!is_numeric($entityId)) {
                continue;
            }

            $createdAt = null;
            if ($type === 'order') {
                $createdAt = Order::whereKey((int) $entityId)->value('created_at');
            } elseif ($type === 'job') {
                $createdAt = Job::whereKey((int) $entityId)->value('created_at');
            } elseif ($type === 'shipment') {
                $createdAt = Shipment::whereKey((int) $entityId)->value('created_at');
            }

            if ($createdAt && (!$latestTimestamp || $createdAt > $latestTimestamp)) {
                $latestTimestamp = $createdAt;
            }
        }

        if ($latestTimestamp) {
            $current = $user->notification_last_read_at;
            $user->notification_last_read_at = $current && $current > $latestTimestamp
                ? $current
                : $latestTimestamp;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $this->computeUnreadCount($user->notification_last_read_at),
        ]);
    }
}
