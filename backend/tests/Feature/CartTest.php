<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 10): Product
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse',
            'price' => 19.99,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    public function test_a_guest_cannot_access_the_cart(): void
    {
        $this->getJson('/api/cart')->assertStatus(401);
    }

    public function test_a_user_can_add_a_product_to_their_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $response = $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_more_than_available_stock_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = $this->product(stock: 3);

        $response = $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_a_user_can_update_the_quantity_of_a_cart_item(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $cartItem = $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->putJson("/api/cart/{$cartItem->id}", ['quantity' => 4]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'quantity' => 4]);
    }

    public function test_a_user_cannot_modify_another_users_cart_item(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = $this->product();
        $cartItem = $owner->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($intruder)->putJson("/api/cart/{$cartItem->id}", ['quantity' => 4]);

        $response->assertStatus(404);
    }

    public function test_a_user_can_remove_a_cart_item(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $cartItem = $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->deleteJson("/api/cart/{$cartItem->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }
}
