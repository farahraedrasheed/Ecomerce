<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
    }

    public function test_anyone_can_browse_active_products(): void
    {
        $category = $this->category();
        Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse',
            'price' => 19.99,
            'stock' => 10,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Discontinued Gadget',
            'slug' => 'discontinued-gadget',
            'price' => 9.99,
            'stock' => 0,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_products_can_be_searched_by_name(): void
    {
        $category = $this->category();
        Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse',
            'price' => 19.99,
            'stock' => 10,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Mechanical Keyboard',
            'slug' => 'mechanical-keyboard',
            'price' => 49.99,
            'stock' => 5,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products?search=mouse');

        $response->assertStatus(200)->assertJsonCount(1)->assertJsonPath('0.name', 'Wireless Mouse');
    }

    public function test_a_customer_can_list_a_product_they_own(): void
    {
        $category = $this->category();
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'New Product',
            'price' => 10,
            'stock' => 5,
        ]);

        $response->assertStatus(201)->assertJsonPath('name', 'New Product');
        $this->assertDatabaseHas('products', ['name' => 'New Product', 'user_id' => $customer->id]);
    }

    public function test_a_guest_cannot_create_a_product(): void
    {
        $category = $this->category();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'New Product',
            'price' => 10,
            'stock' => 5,
        ]);

        $response->assertStatus(401);
    }

    public function test_an_admin_can_create_a_product(): void
    {
        $category = $this->category();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'New Product',
            'price' => 10,
            'stock' => 5,
        ]);

        $response->assertStatus(201)->assertJsonPath('name', 'New Product');
        $this->assertDatabaseHas('products', ['name' => 'New Product']);
    }

    public function test_creating_a_product_validates_required_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/products', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['category_id', 'name', 'price', 'stock']);
    }

    public function test_a_customer_can_edit_their_own_product(): void
    {
        $category = $this->category();
        $customer = User::factory()->create(['role' => 'customer']);
        $product = Product::create([
            'category_id' => $category->id,
            'user_id' => $customer->id,
            'name' => 'My Product',
            'slug' => 'my-product',
            'price' => 15,
            'stock' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)->putJson("/api/products/{$product->id}", ['price' => 20]);

        $response->assertStatus(200)->assertJsonPath('price', '20.00');
    }

    public function test_a_customer_cannot_edit_another_users_product(): void
    {
        $category = $this->category();
        $owner = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $product = Product::create([
            'category_id' => $category->id,
            'user_id' => $owner->id,
            'name' => 'Owner Product',
            'slug' => 'owner-product',
            'price' => 15,
            'stock' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherCustomer)->putJson("/api/products/{$product->id}", ['price' => 999]);

        $response->assertStatus(403);
    }

    public function test_a_customer_cannot_delete_another_users_product(): void
    {
        $category = $this->category();
        $owner = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $product = Product::create([
            'category_id' => $category->id,
            'user_id' => $owner->id,
            'name' => 'Owner Product',
            'slug' => 'owner-product',
            'price' => 15,
            'stock' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherCustomer)->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_an_admin_can_edit_any_product(): void
    {
        $category = $this->category();
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'category_id' => $category->id,
            'user_id' => $owner->id,
            'name' => 'Owner Product',
            'slug' => 'owner-product',
            'price' => 15,
            'stock' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/products/{$product->id}", ['price' => 25]);

        $response->assertStatus(200)->assertJsonPath('price', '25.00');
    }

    public function test_a_customer_can_see_their_own_listings_via_my_products(): void
    {
        $category = $this->category();
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);

        Product::create([
            'category_id' => $category->id,
            'user_id' => $customer->id,
            'name' => 'Mine',
            'slug' => 'mine',
            'price' => 10,
            'stock' => 1,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'user_id' => $otherCustomer->id,
            'name' => 'Not Mine',
            'slug' => 'not-mine',
            'price' => 10,
            'stock' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)->getJson('/api/my-products');

        $response->assertStatus(200)->assertJsonCount(1)->assertJsonPath('0.name', 'Mine');
    }
}
