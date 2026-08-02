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

        // Real photos of each product type, sourced from the corresponding Wikipedia article.
        $productImages = [
            'Wireless Mouse' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/22/3-Tasten-Maus_Microsoft.jpg/330px-3-Tasten-Maus_Microsoft.jpg',
            'Mechanical Keyboard' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/97/Keyboard_Construction.JPG/330px-Keyboard_Construction.JPG',
            'USB-C Hub' => 'https://upload.wikimedia.org/wikipedia/commons/4/46/USB_hub.jpg',
            'Laptop Stand' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/14/Laptop_cooler.JPG/330px-Laptop_cooler.JPG',
            'Webcam' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Logicool_StreamCam_%28cropped%29.jpg/330px-Logicool_StreamCam_%28cropped%29.jpg',
            'Cotton T-Shirt' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/da/Leipzig2012.jpg/330px-Leipzig2012.jpg',
            'Denim Jacket' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/Jacket2-1.jpg/330px-Jacket2-1.jpg',
            'Running Shoes' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Air_Jordan_1_Banned.jpg/330px-Air_Jordan_1_Banned.jpg',
            'Wool Socks' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/42/HandKnittedWhiteLaceSock.jpg/330px-HandKnittedWhiteLaceSock.jpg',
            'Baseball Cap' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Basecap_New_York_Yankees.jpg/330px-Basecap_New_York_Yankees.jpg',
            'Coffee Maker' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cd/Moka_Express_sideview.png/330px-Moka_Express_sideview.png',
            'Non-stick Pan' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Pfanne_%28Edelstahl%29.jpg/330px-Pfanne_%28Edelstahl%29.jpg',
            'Blender' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Vitamix_Blender.jpg/330px-Vitamix_Blender.jpg',
            'Cutting Board' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/74/Chopping_Board.jpg/330px-Chopping_Board.jpg',
            'Ceramic Mug Set' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b8/Mug_of_Tea.JPG/330px-Mug_of_Tea.JPG',
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
                    'image_url' => $productImages[$productName] ?? null,
                    'price' => fake()->randomFloat(2, 9.99, 199.99),
                    'stock' => fake()->numberBetween(5, 100),
                    'is_active' => true,
                ]);
            }
        }
    }
}
