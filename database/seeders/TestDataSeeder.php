<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Shop;
use App\Models\PartnerProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Job;
use App\Models\Shipment;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create shops with partner profiles
        $shops = [
            [
                'shop_domain' => 'fashionstore-a.myshopify.com',
                'shopify_api_version' => '2025-10',
                'status' => 'active',
                'partner' => [
                    'brand_name' => 'Fashion Store A',
                    'return_address_street' => '123 Fashion Ave',
                    'return_address_city' => 'New York',
                    'return_address_state' => 'NY',
                    'return_address_zip' => '10001',
                    'support_email' => 'contact@fashionstore.com',
                    'support_phone' => '+1-212-555-0100',
                ]
            ],
            [
                'shop_domain' => 'printingco-b.myshopify.com',
                'shopify_api_version' => '2025-10',
                'status' => 'active',
                'partner' => [
                    'brand_name' => 'Printing Co B',
                    'return_address_street' => '456 Print Street',
                    'return_address_city' => 'Los Angeles',
                    'return_address_state' => 'CA',
                    'return_address_zip' => '90028',
                    'support_email' => 'contact@printingco.com',
                    'support_phone' => '+1-213-555-0200',
                ]
            ],
            [
                'shop_domain' => 'customshop-c.myshopify.com',
                'shopify_api_version' => '2025-10',
                'status' => 'active',
                'partner' => [
                    'brand_name' => 'Custom Shop C',
                    'return_address_street' => '789 Custom Lane',
                    'return_address_city' => 'Chicago',
                    'return_address_state' => 'IL',
                    'return_address_zip' => '60601',
                    'support_email' => 'contact@customshop.com',
                    'support_phone' => '+1-312-555-0300',
                ]
            ],
            [
                'shop_domain' => 'merchstore-d.myshopify.com',
                'shopify_api_version' => '2025-10',
                'status' => 'suspended',
                'partner' => [
                    'brand_name' => 'Merch Store D',
                    'return_address_street' => '321 Merch Way',
                    'return_address_city' => 'Houston',
                    'return_address_state' => 'TX',
                    'return_address_zip' => '77001',
                    'support_email' => 'contact@merchstore.com',
                    'support_phone' => '+1-713-555-0400',
                ]
            ],
        ];

        foreach ($shops as $shopData) {
            $partnerData = $shopData['partner'];
            unset($shopData['partner']);
            
            $shop = Shop::create($shopData);
            PartnerProfile::create(array_merge($partnerData, ['shop_id' => $shop->id]));
        }

        // Create sample orders
        $fulfillmentStatuses = ['fulfilled', 'partial', 'unfulilled'];
        $financialStatuses = ['paid', 'authorized', 'pending', 'refunded'];
        $itemStatuses = ['pending', 'printing', 'shipped', 'cancelled'];

        for ($i = 1; $i <= 15; $i++) {
            $shop = Shop::inRandomOrder()->first();
            
            $order = Order::create([
                'shop_id' => $shop->id,
                'shopify_order_id' => '#' . (1000 + $i),
                'order_number' => 'ORD-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'customer_name' => 'Customer ' . $i,
                'customer_email' => 'customer' . $i . '@example.com',
                'total_price' => rand(50, 500),
                'currency' => 'USD',
                'fulfillment_status' => $fulfillmentStatuses[array_rand($fulfillmentStatuses)],
                'financial_status' => $financialStatuses[array_rand($financialStatuses)],
                'payload' => [
                    'notes' => 'Sample order for testing',
                    'product_type' => ['Apparel', 'DTF'][rand(0, 1)],
                    'garment_type' => ['T-Shirt', 'Hoodie', 'Polo', 'Cap', 'Jacket', 'Sweatshirt'][rand(0, 5)],
                    'quantity' => rand(1, 10),
                ]
            ]);

            // Add order items
            for ($j = 0; $j < rand(1, 3); $j++) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'sku' => 'SKU-' . str_pad($order->id, 3, '0', STR_PAD_LEFT) . '-' . $j,
                    'dtfta_type' => ['APPAREL_POD', 'DTF', 'PRINT'][rand(0, 2)],
                    'quantity' => rand(1, 5),
                    'status' => $itemStatuses[array_rand($itemStatuses)],
                ]);
            }

            // Create job for order
            $job = Job::create([
                'order_id' => $order->id,
                'shop_id' => $shop->id,
                'job_type' => 'fulfillment',
                'status' => 'pending',
                'payload' => [
                    'artwork_required' => rand(0, 1) ? true : false,
                    'notes' => 'Processing job for order ' . $order->order_number,
                ]
            ]);

            // Create shipment if order is fulfilled
            if ($order->fulfillment_status === 'fulfilled') {
                // Create a dummy job queue record for the shipment
                $queueJob = DB::table('jobs')->insertGetId([
                    'queue' => 'default',
                    'payload' => json_encode(['data' => 'shipment-' . $order->id]),
                    'attempts' => 1,
                    'available_at' => time(),
                    'created_at' => time(),
                ]);
                
                Shipment::create([
                    'order_id' => $order->id,
                    'shop_id' => $shop->id,
                    'job_id' => $queueJob,
                    'carrier' => ['USPS', 'UPS', 'FedEx'][rand(0, 2)],
                    'tracking_number' => strtoupper(substr(md5($order->id . time()), 0, 16)),
                    'tracking_company' => ['USPS', 'UPS', 'FedEx'][rand(0, 2)],
                    'status' => 'shipped',
                    'shipped_at' => now()->subDays(rand(1, 20)),
                    'payload' => [
                        'service_type' => ['Priority Mail', 'Ground', 'Express'][rand(0, 2)],
                    ]
                ]);
            }
        }

        $this->command->info('✅ Test data seeded successfully!');
        $this->command->info('   • 4 Shops created');
        $this->command->info('   • 15 Orders created');
        $this->command->info('   • Multiple order items and shipments created');
    }
}
