<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Product;
use App\Models\Category;
use App\Models\Preorder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. ROLES
        $adminRole = Role::create(['name' => 'ADMIN', 'description' => 'System Administrator']);
        $clientRole = Role::create(['name' => 'CLIENT', 'description' => 'Customer/Client']);
        $deliveryRole = Role::create(['name' => 'DELIVERY', 'description' => 'Delivery Rider/Courier']);

        // 2. USERS
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'John Client',
            'email' => 'client@test.com',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
            'loyalty_points' => 150,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Speedy Rider LBC',
            'email' => 'rider@delivery.com',
            'password' => Hash::make('password'),
            'role_id' => $deliveryRole->id,
            'email_verified_at' => now(),
        ]);

        // 3. CATEGORIES
        $catBrand1 = Category::create(['name' => 'Mini GT', 'type' => 'brand', 'slug' => 'brand-mini-gt']);
        $catBrand2 = Category::create(['name' => 'Hot Wheels', 'type' => 'brand', 'slug' => 'brand-hot-wheels']);
        $catBrand3 = Category::create(['name' => 'Tarmac Works', 'type' => 'brand', 'slug' => 'brand-tarmac-works']);
        
        $catScale1 = Category::create(['name' => '1:64', 'type' => 'scale', 'slug' => 'scale-1-64']);
        $catScale2 = Category::create(['name' => '1:43', 'type' => 'scale', 'slug' => 'scale-1-43']);

        // 4. PRODUCTS (Diecast Focus)
        $p1 = Product::create([
            'category_id' => $catBrand1->id,
            'name' => 'Mini GT Nissan Skyline GT-R R34 V-Spec II',
            'description' => 'Premium 1:64 scale diecast with rubber tires and highly detailed interior.',
            'brand' => 'Mini GT',
            'scale' => '1:64',
            'series' => 'JDM Classics',
            'price' => 750.00,
            'stock' => 20,
            'is_limited_edition' => false,
            'has_opening_parts' => false,
            'tire_type' => 'Rubber',
            'is_preorder' => false,
            'image_url' => 'http://localhost:8000/images/mini_gt_skyline.png',
        ]);

        $p2 = Product::create([
            'category_id' => $catBrand2->id,
            'name' => 'Hot Wheels RLC Exclusive 1969 Chevy Camaro SS',
            'description' => 'Red Line Club exclusive. Spectraflame paint, opening hood.',
            'brand' => 'Hot Wheels',
            'scale' => '1:64',
            'series' => 'RLC',
            'price' => 4500.00,
            'stock' => 5,
            'is_limited_edition' => true,
            'has_opening_parts' => true,
            'tire_type' => 'Real Riders (Rubber)',
            'is_preorder' => false,
            'image_url' => 'http://localhost:8000/images/hotwheels_camaro.png',
        ]);

        $p3 = Product::create([
            'category_id' => $catBrand3->id,
            'name' => 'Tarmac Works Pagani Zonda R - Carbon Fiber',
            'description' => 'Exquisite carbon fiber replica. Pre-order item.',
            'brand' => 'Tarmac Works',
            'scale' => '1:43',
            'series' => 'Global64',
            'price' => 2500.00,
            'stock' => 0,
            'is_limited_edition' => true,
            'has_opening_parts' => false,
            'tire_type' => 'Rubber',
            'is_preorder' => true,
            'image_url' => 'http://localhost:8000/images/zonda_carbon.png',
        ]);

        $p4 = Product::create([
            'category_id' => $catBrand1->id,
            'name' => 'Mini GT Porsche 911 GT3 RS (992) - Shark Blue',
            'description' => 'Stunning Shark Blue GT3 RS. Expected arrival next month.',
            'brand' => 'Mini GT',
            'scale' => '1:64',
            'price' => 850.00,
            'stock' => 0,
            'is_limited_edition' => false,
            'is_preorder' => true,
            'image_url' => 'http://localhost:8000/images/mini_gt_skyline.png', // Reuse for now
        ]);

        // 5. PREORDERS Linkage
        Preorder::create([
            'product_id' => $p3->id,
            'release_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
            'downpayment_amount' => 500.00,
            'is_active' => true,
        ]);

        Preorder::create([
            'product_id' => $p4->id,
            'release_date' => Carbon::now()->addWeeks(3)->format('Y-m-d'),
            'downpayment_amount' => 200.00,
            'is_active' => true,
        ]);
        
        echo "Database Seeder: Dummy Data Populated successfully.\n";
    }
}
