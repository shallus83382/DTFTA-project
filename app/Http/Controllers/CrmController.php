<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Order;
use App\Models\Job;
use App\Models\Shop;
use App\Models\Shipment;
use App\Models\FulfillmentService;
use App\Models\PartnerProfile;
use App\Models\AdminActivityLog;
use Carbon\Carbon;

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
    private function prepareViewData(): array
    {
        return [
            'config' => $this->getConfig(),
            'branding' => $this->getBranding(),
            'theme' => $this->getTheme(),
            'features' => $this->getFeatures(),
            'navigation' => $this->getNavigation(),
            'statuses' => $this->getStatuses(),
        ];
    }

    /**
     * Show the dashboard.
     */
    public function dashboard(): View
    {
        $data = $this->prepareViewData();
        $data['dashboardConfig'] = $this->getFeatures()['dashboard'] ?? [];
        
        // Get dashboard statistics
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
            'exceptions' => Job::where(function($query) {
                $query->where('status', 'failed')
                    ->orWhere('status', 'exception')
                    ->orWhere('status', 'cancelled');
            })->count(),
        ];
        
        // Get recent activity
        $data['recentActivity'] = AdminActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(5, ['*'], 'activity_page');
        
        // Get orders per day for chart (last 7 days)
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
        
        // Get orders by shop
        $data['ordersByShop'] = Order::selectRaw('shops.shop_domain, count(orders.id) as count')
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->groupBy('shops.id', 'shops.shop_domain')
            ->limit(5)
            ->get();
        
        // Get job types distribution
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
        if (auth('sanctum')->check()) {
            return redirect('/crm/dashboard');
        }
        return view('login');
    }

    /**
     * Show orders/jobs list.
     */
    public function orders(): View
    {
        $data = $this->prepareViewData();
        $data['ordersConfig'] = $this->getFeatures()['orders'] ?? [];
        $data['orderStatuses'] = $this->getStatuses()['order_statuses'] ?? [];
        
        // Get jobs grouped by status for kanban board
        // Status flow: PENDING → ARTWORK NEEDED → IN PRODUCTION → SHIPPED → CANCELLED/EXCEPTION
        $statuses = ['pending', 'artwork_needed', 'in_production', 'shipped', 'cancelled'];
        $jobsByStatus = [];
        
        foreach ($statuses as $status) {
            if ($status === 'cancelled') {
                // For cancelled, get both failed and exception statuses
                $jobsByStatus[$status] = Job::where(function($query) {
                    $query->where('status', 'failed')
                        ->orWhere('status', 'exception')
                        ->orWhere('status', 'cancelled');
                })->with('shop', 'order')
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                $jobsByStatus[$status] = Job::where('status', $status)
                    ->with('shop', 'order')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }
        $data['jobsByStatus'] = $jobsByStatus;
        
        // Get all jobs with relationships for table view
        $data['allJobs'] = Job::with('shop', 'order')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        // Get unique stores for filter dropdown
        $data['stores'] = Shop::pluck('shop_domain');
        
        // Get unique product types (job types)
        $data['productTypes'] = Job::distinct('job_type')->pluck('job_type');
        
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
        
        // Get job with relationships
        $job = Job::with('shop', 'order', 'order.orderItems')->findOrFail($jobId);
        $data['job'] = $job;
        
        // Get the associated order
        $order = $job->order;
        $data['order'] = $order;
        
        // Get all order items
        $data['orderItems'] = $order->orderItems;
        
        // Get activity log for this job
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
    public function stores(): View
    {
        $data = $this->prepareViewData();
        $data['storesConfig'] = $this->getFeatures()['stores'] ?? [];
        return view('crm.stores', $data);
    }

    /**
     * Show reports & analytics page.
     */
    public function reports(): View
    {
        $data = $this->prepareViewData();
        $data['reportsConfig'] = $this->getFeatures()['reports'] ?? [];
        return view('crm.reports', $data);
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
     * Show store detail page.
     */
    public function storedetails(): View
    {
        $data = $this->prepareViewData();
        return view('crm.store-detail', $data);
    }
    /**
     * Show order detail page.
     */
    public function orderdetails(string $orderId): View
    {
        $data = $this->prepareViewData();
        
        // Get order with relationships
        $order = Order::with('shop', 'orderItems', 'shipments')->findOrFail($orderId);
        $data['order'] = $order;
        
        // Get jobs associated with this order
        $data['jobs'] = Job::where('order_id', $orderId)
            ->with('shop')
            ->get();
        
        // Get activity log for this order
        $data['activityLog'] = AdminActivityLog::where('model_type', 'Order')
            ->where('model_id', $orderId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return view('crm.order-detail', $data);
    }

    /**
     * API: Update status for job or order
     */
    public function updateStatus(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('type');
        $status = $request->input('status');

        try {
            if ($type === 'job') {
                $job = Job::findOrFail($id);
                $job->update(['status' => $status]);

                // Also update parent order fulfillment_status if present
                if ($job->order_id) {
                    $order = Order::find($job->order_id);
                    if ($order) {
                        $order->update(['fulfillment_status' => $status]);
                    }
                }

                // Log activity
                AdminActivityLog::logActivity(
                    auth()->id(),
                    'Updated Status to ' . $status,
                    'Job',
                    $id
                );

                return response()->json(['success' => true, 'message' => 'Job status updated successfully', 'status' => $status]);
            }

            // order
            $order = Order::findOrFail($id);
            $order->update(['fulfillment_status' => $status]);

            // Update all jobs linked to this order to keep UI consistent
            Job::where('order_id', $order->id)->update(['status' => $status]);

            // Log activity for order
            AdminActivityLog::logActivity(
                auth()->id(),
                'Updated Status to ' . $status,
                'Order',
                $id
            );

            return response()->json(['success' => true, 'message' => 'Order status updated successfully', 'status' => $status]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * API: Get activity log for a job or order
     */
    public function getActivityLog(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('type');

        try {
            $activities = AdminActivityLog::where('model_id', $id);

            if ($type === 'job') {
                $activities = $activities->where('model_type', 'Job');
            } else {
                $activities = $activities->where('model_type', 'Order');
            }

            $activities = $activities->orderBy('created_at', 'desc')->with('user')->get();

            $result = $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'model_type' => $activity->model_type,
                    'created_at' => $activity->created_at,
                    'user' => $activity->user ? ['id' => $activity->user->id, 'name' => $activity->user->name] : null,
                ];
            });

            return response()->json(['success' => true, 'activities' => $result]);
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
}
