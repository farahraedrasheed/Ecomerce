<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 10): Product
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse',
            'price' => 20,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    private function validCardPayload(): array
    {
        return [
            'card_name' => 'Jane Doe',
            'card_number' => '4242 4242 4242 4242',
            'card_expiry' => '12/30',
            'card_cvv' => '123',
        ];
    }

    public function test_checking_out_creates_an_order_and_clears_the_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->product(stock: 10);
        $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 3]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'shipping_address' => '123 Main St',
            ...$this->validCardPayload(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('total_amount', '60.00')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('card_last_four', '4242');

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);
    }

    public function test_checking_out_with_an_empty_cart_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'shipping_address' => '123 Main St',
            ...$this->validCardPayload(),
        ]);

        $response->assertStatus(422);
    }

    public function test_checking_out_with_an_invalid_card_number_fails(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'shipping_address' => '123 Main St',
            ...$this->validCardPayload(),
            'card_number' => '1234 5678 9012 3456',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checking_out_with_an_expired_card_fails(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'shipping_address' => '123 Main St',
            ...$this->validCardPayload(),
            'card_expiry' => '01/20',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checking_out_with_a_declined_test_card_fails_and_does_not_charge_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->product(stock: 10);
        $user->cartItems()->create(['product_id' => $product->id, 'quantity' => 2]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'shipping_address' => '123 Main St',
            ...$this->validCardPayload(),
            'card_number' => '4000 0000 0000 0002',
        ]);

        $response->assertStatus(402);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 10]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_a_customer_only_sees_their_own_orders(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create(['role' => 'customer']);
        $product = $this->product();

        $otherUser->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($otherUser)->postJson('/api/orders', ['shipping_address' => 'Other address', ...$this->validCardPayload()]);

        $response = $this->actingAs($customer)->getJson('/api/orders');

        $response->assertStatus(200)->assertJsonCount(0);
    }

    public function test_an_admin_sees_all_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product();

        $customer->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($customer)->postJson('/api/orders', ['shipping_address' => 'Some address', ...$this->validCardPayload()]);

        $response = $this->actingAs($admin)->getJson('/api/orders');

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_a_customer_cannot_update_order_status(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product();
        $customer->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);
        $order = $this->actingAs($customer)->postJson('/api/orders', ['shipping_address' => 'Addr', ...$this->validCardPayload()])->json();

        $response = $this->actingAs($customer)->putJson("/api/orders/{$order['id']}/status", ['status' => 'shipped']);

        $response->assertStatus(403);
    }

    public function test_an_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->product();
        $customer->cartItems()->create(['product_id' => $product->id, 'quantity' => 1]);
        $order = $this->actingAs($customer)->postJson('/api/orders', ['shipping_address' => 'Addr', ...$this->validCardPayload()])->json();

        $response = $this->actingAs($admin)->putJson("/api/orders/{$order['id']}/status", ['status' => 'shipped']);

        $response->assertStatus(200)->assertJsonPath('status', 'shipped');
    }
}
