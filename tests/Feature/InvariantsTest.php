<?php

namespace Tests\Feature;

use App\Domain\DamageType;
use App\Domain\FulfillmentMethod;
use App\Domain\PaymentMethod;
use App\Exceptions\Domain\AllocationMismatchException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Admin;
use App\Services\AuthService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * The 13 invariants from INVARIANTS.md, each as a named test (INVARIANTS.md:
 * "An invariant is a test."). Deliberately break every rule; confirm a named
 * test catches it.
 */
class InvariantsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private AuthService $auth;
    private InventoryService $inventory;
    private OrderService $orders;
    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = app(AuthService::class);
        $this->inventory = app(InventoryService::class);
        $this->orders = app(OrderService::class);
        $this->payments = app(PaymentService::class);

        $this->admin = Admin::create([
            'admin_name' => 'Owner',
            'email' => 'owner@maviesbugasan.ph',
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    private function variety(string $name = 'Sinandomeng 50kg', float $price = 1000.00, int $stock = 50)
    {
        return $this->inventory->create([
            'name' => $name,
            'category' => 'Local',
            'unit_price' => $price,
            'wholesale_price' => $price,
            'low_stock_threshold' => 10,
            'initial_stock' => $stock,
        ], $this->admin);
    }

    private function walkIn(string $phone = '09170000001')
    {
        return $this->auth->createWalkIn(['first_name' => 'Ana', 'last_name' => 'Cruz', 'phone' => $phone], $this->admin);
    }

    /** @return list<string> */
    private function protectedAdminEndpoints(): array
    {
        return [
            '/api/admin/dashboard',
            '/api/admin/orders',
            '/api/admin/orders/1',
            '/api/admin/inventory',
            '/api/admin/inventory/1/movements',
            '/api/admin/customers',
            '/api/admin/customers/1',
            '/api/admin/payments',
            '/api/admin/damages',
            '/api/admin/settings',
            '/api/admin/staff',
            '/api/admin/activity-log',
        ];
    }

    public function test_1_default_deny_every_protected_endpoint(): void
    {
        foreach ($this->protectedAdminEndpoints() as $uri) {
            $this->getJson($uri)->assertStatus(401, "GET {$uri} must be denied without a session");
        }

        $this->getJson('/api/customer/orders')->assertStatus(401);
        $this->getJson('/api/customer/me')->assertStatus(401);
    }

    public function test_1_default_deny_a_customer_session_is_not_an_admin_session(): void
    {
        $this->actingAs($this->walkIn(), 'customer');

        foreach ($this->protectedAdminEndpoints() as $uri) {
            $this->getJson($uri)->assertStatus(401, "GET {$uri} must be denied to a customer session");
        }
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_2_every_query_is_parameterised(): void
    {
        $violations = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $contents = file_get_contents($file);

            if (preg_match_all('/\b(?:DB::raw|whereRaw|selectRaw|orderByRaw|havingRaw)\s*\([^\n]*/', $contents, $matches)) {
                foreach ($matches[0] as $call) {
                    if (! preg_match('/^[^(]*\(\s*.?/', $call, $first)) {
                        continue;
                    }

                    $quote = substr($call, strpos($call, '(') + 1, 1);
                    if (! in_array($quote, ['"', "'"], true)) {
                        continue;
                    }

                    $arg = substr($call, strpos($call, '(') + 2);
                    $arg = substr($arg, 0, strpos($arg, $quote));

                    if (preg_match('/\$[A-Za-z_]/', $arg) || preg_match('/\.\s*\$/', $call)) {
                        $violations[] = $file.': '.trim($call);
                    }
                }
            }
        }

        $this->assertSame([], $violations, 'Raw SQL with interpolated data found: '.implode("\n", $violations));
    }

    public function test_3_no_exception_message_ever_reaches_the_browser(): void
    {
        $handler = app(ExceptionHandler::class);
        $request = Request::create('/api/admin/orders', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $bomb = new RuntimeException('SQLSTATE[HY000] connect denied for user root@127.0.0.1 DSN mysql:mavies; query: select * from customers;');

        $response = $handler->render($request, $bomb);

        $this->assertSame(500, $response->getStatusCode());
        $body = $response->getContent();

        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('customers', $body);
        $this->assertStringNotContainsString('DSN', $body);
        $this->assertStringNotContainsString('root@', $body);
        $this->assertStringContainsString('reference', $body);

        // Framework HTTP errors (404/403/419) over JSON must also never leak
        // exception/trace data.
        $http = new \Symfony\Component\HttpKernel\Exception\HttpException(404, 'The route could not be found.');
        $response = $handler->render($request, $http);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('trace', $response->getContent());
        $this->assertStringNotContainsString('vendor', $response->getContent());
    }

    public function test_4_every_password_is_a_bcrypt_hash_and_nothing_seeds_an_admin(): void
    {
        // No seeder writes an admin row — it must come from mavies:make-owner.
        foreach (glob(database_path('seeders/*.php')) as $file) {
            $this->assertStringNotContainsString('Admin::create', file_get_contents($file), $file);
            $this->assertStringNotContainsString('new Admin', file_get_contents($file), $file);
        }

        $customer = $this->auth->register('Ana', 'Cruz', '09171234567', 'ana@example.com', 'secret123', [], 'New', true);

        foreach ([$customer->password_hash, $this->admin->password_hash] as $hash) {
            $this->assertStringStartsWith('$2y$', $hash);
            $this->assertSame(60, strlen($hash));
        }

        // Integration with integrity check #13.
        $this->seed();
        Artisan::call('mavies:integrity-check');
        $this->assertStringContainsString('PASS  13. no password stored', Artisan::output());
    }

    public function test_5_money_is_recorded_never_inferred(): void
    {
        $variety = $this->variety('Jasponica 50kg', 2000.00, 50);
        $order = $this->orders->create(
            $this->walkIn(),
            [['inventory_id' => $variety->inventory_id, 'sacks' => 10]],
            FulfillmentMethod::PICKUP,
            [],
            $this->admin,
        );

        $this->payments->record(
            [['order_id' => $order->order_id, 'amount' => 5000.00]],
            5000.00,
            PaymentMethod::CASH,
            $this->admin->admin_id,
            null, null, null, $this->admin,
        );

        $balance = (float) DB::table('v_orders')->where('order_id', $order->order_id)->value('balance');
        $this->assertSame(15000.0, $balance, 'The 5000 payment must leave exactly 15000 of a 20000 order.');
    }

    public function test_6_derive_ids_from_the_insert_not_max_plus_one(): void
    {
        $variety = $this->variety();
        $order = $this->orders->create(
            $this->walkIn(),
            [['inventory_id' => $variety->inventory_id, 'sacks' => 1]],
            FulfillmentMethod::PICKUP,
            [],
            $this->admin,
        );

        $this->assertSame('ORD-'.str_pad((string) $order->order_id, 6, '0', STR_PAD_LEFT), $order->order_code);

        $damage = $this->inventory->logDamage(
            $variety->inventory_id, 2, DamageType::WATER, now()->toDateString(), 'Test', $this->admin,
        );
        $this->assertSame('DMG-'.str_pad((string) $damage->damage_log_id, 6, '0', STR_PAD_LEFT), $damage->damage_code);
    }

    public function test_7_stock_changes_only_inside_a_transaction_with_a_ledger_row(): void
    {
        $variety = $this->variety('Local C-1', 1000.00, 10);
        $before = (int) DB::table('stock_movements')->where('inventory_id', $variety->inventory_id)->sum('qty');

        try {
            $this->orders->create(
                $this->walkIn(),
                [['inventory_id' => $variety->inventory_id, 'sacks' => 11]],
                FulfillmentMethod::PICKUP,
                [],
                $this->admin,
            );
            $this->fail('Ordering 11 of 10 sacks must be refused.');
        } catch (InsufficientStockException $e) {
            // expected
        }

        $this->assertSame(10, $this->inventory->available($variety->inventory_id));
        $after = (int) DB::table('stock_movements')->where('inventory_id', $variety->inventory_id)->sum('qty');
        $this->assertSame($before, $after, 'A refused order must not touch the ledger.');
    }

    public function test_8_never_hard_delete_money_or_history_records(): void
    {
        $variety = $this->variety('Dinorado 25kg', 1200.00, 20);
        $customer = $this->walkIn();
        $this->orders->create(
            $customer,
            [['inventory_id' => $variety->inventory_id, 'sacks' => 2]],
            FulfillmentMethod::PICKUP,
            [],
            $this->admin,
        );

        foreach (['inventory' => 'inventory_id', 'customers' => 'customer_id'] as $table => $column) {
            $id = $table === 'inventory' ? $variety->inventory_id : $customer->customer_id;

            try {
                DB::table($table)->delete($id);
                $this->fail("Hard-delete on {$table} must be refused by the FK.");
            } catch (QueryException $e) {
                // FK RESTRICT
            }

            $this->assertDatabaseHas($table, [$column => $id]);
        }

        // No controller offers a destructive DELETE either.
        $violations = [];
        foreach ($this->phpFiles(app_path('Http/Controllers')) as $file) {
            if (str_contains(file_get_contents($file), '->delete(')) {
                $violations[] = $file;
            }
        }
        $this->assertSame([], $violations, 'Controllers must never hard-delete.');
    }

    public function test_9_every_list_endpoint_is_paginated(): void
    {
        $this->seed();
        $this->actingAs($this->admin, 'admin');

        foreach (
            [
                '/api/admin/orders',
                '/api/admin/inventory',
                '/api/admin/customers',
                '/api/admin/payments',
                '/api/admin/damages',
                '/api/admin/activity-log',
            ] as $uri
        ) {
            $this->getJson($uri)
                ->assertOk()
                ->assertJsonStructure(['data', 'pagination' => ['total', 'page', 'per_page', 'pages']]);
        }

        // Customer + public lists.
        $this->actingAs($this->walkIn('09170000002'), 'customer')
            ->getJson('/api/customer/orders')
            ->assertOk()
            ->assertJsonStructure(['data', 'pagination' => ['total', 'page', 'per_page', 'pages']]);

        $this->getJson('/api/catalog')
            ->assertOk()
            ->assertJsonStructure(['data', 'pagination' => ['total', 'page', 'per_page', 'pages']]);
    }

    public function test_10_never_interpolate_data_into_html(): void
    {
        $violations = [];

        foreach ($this->phpFiles(resource_path('js')) as $file) {
            $contents = file_get_contents($file);
            foreach (['v-html', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write'] as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = "{$needle} in {$file}";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_11_customer_id_comes_from_the_session_only(): void
    {
        $variety = $this->variety();
        $attacker = $this->auth->register('Ana', 'Cruz', '09171234567', 'ana@example.com', 'secret123', [], 'New', true);
        $victim = $this->auth->register('Bea', 'Diaz', '09181234567', 'bea@example.com', 'secret123', [], 'New', true);

        $this->actingAs($attacker, 'customer');

        $this->postJson('/api/customer/orders', [
            'fulfillment_method' => 'Pickup',
            'customer_id' => $victim->customer_id,
            'lines' => [['inventory_id' => $variety->inventory_id, 'sacks' => 1]],
        ])->assertStatus(201);

        $orderId = DB::table('orders')->first()->order_id;
        $this->assertSame(
            $attacker->customer_id,
            (int) DB::table('orders')->where('order_id', $orderId)->value('customer_id'),
        );
    }

    public function test_12_order_money_is_computed_not_stored(): void
    {
        $variety = $this->variety('Maya 50kg', 1000.00, 50);
        $order = $this->orders->create(
            $this->walkIn(),
            [['inventory_id' => $variety->inventory_id, 'sacks' => 5]],
            FulfillmentMethod::PICKUP,
            [],
            $this->admin,
        );

        $amountDue = fn () => (float) DB::table('v_orders')->where('order_id', $order->order_id)->value('amount_due');

        $this->assertSame(5000.0, $amountDue());

        $itemId = DB::table('order_items')->where('order_id', $order->order_id)->value('order_item_id');
        $this->orders->price($order, [$itemId => 1200.00], null, $this->admin);

        $this->assertSame(6000.0, $amountDue(), 'amount_due must move by exactly the price delta.');
    }

    public function test_13_payment_allocations_sum_to_exactly_the_payment(): void
    {
        $v = $this->variety('C-1 50kg', 1000.00, 100);
        $customer = $this->walkIn();

        $a = $this->orders->create($customer, [['inventory_id' => $v->inventory_id, 'sacks' => 4]], FulfillmentMethod::PICKUP, [], $this->admin);
        $b = $this->orders->create($customer, [['inventory_id' => $v->inventory_id, 'sacks' => 3]], FulfillmentMethod::PICKUP, [], $this->admin);

        $this->payments->record(
            [
                ['order_id' => $a->order_id, 'amount' => 3000.00],
                ['order_id' => $b->order_id, 'amount' => 3000.00],
            ],
            6000.00,
            PaymentMethod::GCASH,
            $this->admin->admin_id,
            'REF-1',
            null, null, $this->admin,
        );

        $this->assertSame(1, DB::table('payments')->count());

        try {
            $this->payments->record(
                [
                    ['order_id' => $a->order_id, 'amount' => 3000.00],
                    ['order_id' => $b->order_id, 'amount' => 3000.00],
                ],
                5000.00,
                PaymentMethod::CASH,
                $this->admin->admin_id,
                'REF-2',
                null, null, $this->admin,
            );
            $this->fail('A split that does not total the payment must be refused.');
        } catch (AllocationMismatchException $e) {
            // expected
        }

        $this->assertSame(1, DB::table('payments')->count(), 'The rejected split must not create a payment.');
    }

    public function test_17_every_non_walk_in_customer_must_have_accepted_the_terms(): void
    {
        DB::table('customers')->insert([
            'first_name' => 'No',
            'last_name' => 'Terms',
            'phone' => '09170000000',
            'address_city' => 'Baybay',
            'is_walk_in' => 0,
            'customer_type' => 'New',
            'terms_accepted_at' => null,
        ]);

        Artisan::call('mavies:integrity-check');

        $this->assertStringContainsString('FAIL  17.', Artisan::output());
    }

    public function test_integrity_check_command_passes_on_a_healthy_database(): void
    {
        $this->seed();

        $exit = Artisan::call('mavies:integrity-check');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('All 17 integrity checks passed.', Artisan::output());
    }
}