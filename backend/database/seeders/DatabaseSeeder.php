<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $categories = [
            'Electronics' => ['Wireless Mouse', 'Mechanical Keyboard', 'USB-C Hub', 'Laptop Stand', 'Webcam'],
            'Clothing' => ['Cotton T-Shirt', 'Denim Jacket', 'Running Shoes', 'Wool Socks', 'Baseball Cap'],
            'Home & Kitchen' => ['Coffee Maker', 'Non-stick Pan', 'Blender', 'Cutting Board', 'Ceramic Mug Set'],
        ];

        foreach ($categories as $categoryName => $products) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
            ]);

            foreach ($products as $productName) {
                Product::create([
                    'category_id' => $category->id,
                    'name' => $productName,
                    'slug' => Str::slug($productName).'-'.Str::random(6),
                    'description' => "A high-quality {$productName} for everyday use.",
                    'price' => fake()->randomFloat(2, 9.99, 199.99),
                    'stock' => fake()->numberBetween(5, 100),
                    'is_active' => true,
                ]);
            }
        }
    }
}
