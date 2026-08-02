<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_categories(): void
    {
        Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_a_customer_cannot_create_a_category(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->postJson('/api/categories', ['name' => 'Books']);

        $response->assertStatus(403);
    }

    public function test_an_admin_can_create_a_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/categories', ['name' => 'Books']);

        $response->assertStatus(201)->assertJsonPath('slug', 'books');
    }

    public function test_category_names_must_be_unique(): void
    {
        Category::create(['name' => 'Books', 'slug' => 'books']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/categories', ['name' => 'Books']);

        $response->assertStatus(422);
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse',
            'price' => 20,
            'stock' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $response = $this->actingAs($admin)->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
