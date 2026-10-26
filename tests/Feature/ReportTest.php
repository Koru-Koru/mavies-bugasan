<?php

namespace Tests\Feature;

use App\Domain\FulfillmentMethod;
use App\Domain\PaymentMethod;
use App\Models\Admin;
use App\Models\Customer;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): Admin
    {
        return Admin::create([
            'admin_name' => 'Owner',
            'email' => 'owner@maviesbugasan.ph',
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    private function customer(string $email = 'ana@example.com', string $phone = '09171234567'): Customer
    {
        return Customer::create([
            'first_name' => 'Ana', 'last_name' => 'Cruz',
            'phone' => $phone, 'email' => $email,
            'password_hash' => Hash::make('secret123'),
        ]);
    }

    private function item(Admin $admin, string $name, float $price, float $wholesale, int $stock = 100)
    {
        return app(InventoryService::class)->create([
            'name' => $name, 'category' => 'Local',
            'unit_price' => $price, 'wholesale_price' => $wholesale, 'initial_stock' => $stock,
        ], $admin);
    }

    private function metric(array $report, string $label)
    {
        foreach ($report['metrics'] as $m) {
            if ($m['label'] === $label) {
                return $m['value'];
            }
        }

        return null;
    }

    public function test_summary_agrees_with_v_orders(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $item = $this->item($admin, 'Jasmine 50kg', 1000.00, 900.00);
        $ana = $this->customer();

        app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 5]], FulfillmentMethod::PICKUP);
        $delivered = app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 3]], FulfillmentMethod::PICKUP);
        app(OrderService::class)->changeStatus($delivered, 'Confirmed', $admin);
        app(OrderService::class)->changeStatus($delivered->refresh(), 'Out for Delivery', $admin);
        app(OrderService::class)->changeStatus($delivered->refresh(), 'Delivered', $admin);

        $ordered = $this->getJson('/api/admin/reports/summary?basis=ordered')->assertOk()->json('report');
        $this->assertSame(
            (float) DB::table('v_orders')->where('status', '<>', 'Cancelled')->sum('amount_due'),
            (float) $this->metric($ordered, 'Revenue'),
        );
        $this->assertSame(2, $this->metric($ordered, 'Orders'));
        $this->assertSame(8, $this->metric($ordered, 'Sacks'));

        $deliveredReport = $this->getJson('/api/admin/reports/summary?basis=delivered')->assertOk()->json('report');
        $this->assertSame(
            (float) DB::table('v_orders')->where('status', 'Delivered')->sum('amount_due'),
            (float) $this->metric($deliveredReport, 'Revenue'),
        );
        $this->assertSame(3, $this->metric($deliveredReport, 'Sacks'));
    }

    public function test_top_varieties_ranks_by_sacks(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $big = $this->item($admin, 'Sinandomeng 50kg', 1200.00, 1150.00);
        $small = $this->item($admin, 'Dinorado 25kg', 600.00, 560.00);
        $ana = $this->customer();

        app(OrderService::class)->create($ana, [
            ['inventory_id' => $big->inventory_id, 'sacks' => 6],
            ['inventory_id' => $small->inventory_id, 'sacks' => 2],
        ], FulfillmentMethod::PICKUP);

        $report = $this->getJson('/api/admin/reports/top-varieties')->assertOk()->json('report');

        $this->assertSame('Sinandomeng 50kg', $report['rows'][0]['name']);
        $this->assertEqualsWithDelta(6.0, (float) $report['rows'][0]['sacks'], 0.001);
        $this->assertSame(8, $this->metric($report, 'Sacks sold'));
    }

    public function test_by_day_buckets_todays_orders(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $item = $this->item($admin, 'Boy Kanin 50kg', 1180.00, 1180.00);
        $ana = $this->customer();

        app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 2]], FulfillmentMethod::PICKUP);
        app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 1]], FulfillmentMethod::PICKUP);

        $report = $this->getJson('/api/admin/reports/by-day')->assertOk()->json('report');

        $this->assertCount(1, $report['rows']);
        $this->assertSame(now()->toDateString(), $report['rows'][0]['day']);
        $this->assertSame(2, $report['rows'][0]['orders']);
        $this->assertSame(3, $this->metric($report, 'Sacks'));
    }

    public function test_inventory_valuation_uses_wholesale_fallback(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $item = $this->item($admin, 'MCL Denorado 50kg', 1200.00, 900.00, 40);

        $report = $this->getJson('/api/admin/reports/inventory')->assertOk()->json('report');

        $this->assertSame(40, $this->metric($report, 'Sacks on hand'));
        $this->assertSame(36000.0, (float) $this->metric($report, 'Stock value'));
    }

    public function test_damage_report_totals_sacks(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $item = $this->item($admin, 'Sakura 25kg', 700.00, 650.00);

        app(InventoryService::class)->logDamage($item->inventory_id, 3, 'Pest Infestation', now()->toDateString(), null, $admin);

        $report = $this->getJson('/api/admin/reports/damage')->assertOk()->json('report');

        $this->assertSame(3, $this->metric($report, 'Sacks lost'));
        $this->assertSame(1, $this->metric($report, 'Incidents'));
    }

    public function test_credit_report_lists_only_orders_with_a_balance(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $item = $this->item($admin, 'Royal Sheep 50kg', 1300.00, 1200.00);
        $ana = $this->customer();

        $order = app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 3]], FulfillmentMethod::PICKUP);

        $report = $this->getJson('/api/admin/reports/credit')->assertOk()->json('report');
        $this->assertSame(3900.0, (float) $this->metric($report, 'Total outstanding'));
        $this->assertCount(1, $report['rows']);

        // Pay it off; it should drop off the receivables list.
        app(PaymentService::class)->record(
            [['order_id' => $order->order_id, 'amount' => 3900.00]],
            3900.00, 'Cash', $admin->admin_id, null, null, null, $admin,
        );

        $after = $this->getJson('/api/admin/reports/credit')->assertOk()->json('report');
        $this->assertCount(0, $after['rows']);
        $this->assertSame(0.0, (float) $this->metric($after, 'Total outstanding'));
    }

    public function test_csv_export_streams_a_download(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');
        $item = $this->item($admin, 'Coco Japonica 25kg', 850.00, 800.00);
        $ana = $this->customer();
        app(OrderService::class)->create($ana, [['inventory_id' => $item->inventory_id, 'sacks' => 1]], FulfillmentMethod::PICKUP);

        $response = $this->get('/api/admin/reports/export?report=summary&format=csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Sales summary', $csv);
        $this->assertStringContainsString('TOTAL', $csv);
    }

    public function test_pdf_export_returns_a_pdf(): void
    {
        $this->actingAs($this->owner(), 'admin');

        $response = $this->get('/api/admin/reports/export?report=inventory&format=pdf');
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Every report must export in both formats. The row shape has to be an
     * array for both writers: inventory and credit used to hand back stdClass
     * and only blew up once a row actually existed, so the old tests above
     * passed on an empty table and never rendered a cell.
     */
    public function test_every_report_exports_as_csv_with_rows_present(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');

        $item = $this->item($admin, 'Coco Japonica 25kg', 850.00, 800.00, 5);
        $ana = $this->customer();

        $order = app(OrderService::class)->create(
            $ana, [['inventory_id' => $item->inventory_id, 'sacks' => 2]], FulfillmentMethod::PICKUP
        );
        app(PaymentService::class)->record(
            [['order_id' => $order->order_id, 'amount' => 500.00]],
            500.00,
            PaymentMethod::CASH,
            $admin->admin_id,
            actor: $admin,
        );

        foreach (ReportService::REPORTS as $key) {
            $response = $this->get("/api/admin/reports/export?report=$key&format=csv");
            $this->assertSame(200, $response->getStatusCode(), "csv key=$key");
            $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

            $csv = $response->streamedContent();
            $this->assertNotEmpty($csv, "empty csv for $key");
            $this->assertStringNotContainsString(
                'stdClass',
                $csv,
                "csv for $key leaked a serialised object"
            );
        }
    }

    public function test_every_report_exports_as_pdf_with_rows_present(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');

        $item = $this->item($admin, 'Coco Japonica 25kg', 850.00, 800.00, 5);
        $ana = $this->customer();

        $order = app(OrderService::class)->create(
            $ana, [['inventory_id' => $item->inventory_id, 'sacks' => 2]], FulfillmentMethod::PICKUP
        );
        app(PaymentService::class)->record(
            [['order_id' => $order->order_id, 'amount' => 500.00]],
            500.00,
            PaymentMethod::CASH,
            $admin->admin_id,
            actor: $admin,
        );

        foreach (ReportService::REPORTS as $key) {
            $response = $this->get("/api/admin/reports/export?report=$key&format=pdf");
            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
            $this->assertStringStartsWith(
                '%PDF-',
                $response->getContent(),
                "pdf for $key was not a pdf"
            );
        }
    }

    /**
     * The inventory and credit reports are the two that build rows from a
     * query builder rather than the shared numericRow() helper, so they get
     * their own guard against regressing back to object rows.
     */
    public function test_inventory_and_credit_rows_are_arrays(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin, 'admin');

        $item = $this->item($admin, 'Coco Japonica 25kg', 850.00, 800.00, 5);
        $ana = $this->customer();

        $order = app(OrderService::class)->create(
            $ana, [['inventory_id' => $item->inventory_id, 'sacks' => 2]], FulfillmentMethod::PICKUP
        );
        app(PaymentService::class)->record(
            [['order_id' => $order->order_id, 'amount' => 500.00]],
            500.00,
            PaymentMethod::CASH,
            $admin->admin_id,
            actor: $admin,
        );

        foreach (['inventory', 'credit'] as $key) {
            $report = $this->getJson("/api/admin/reports/$key")->assertOk()->json('report');

            $this->assertNotEmpty($report['rows'], "$key had no rows to check");
            foreach ($report['rows'] as $row) {
                $this->assertIsArray($row, "$key row is not an array");
            }
        }
    }

    public function test_unknown_report_is_not_routable(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $this->getJson('/api/admin/reports/does-not-exist')->assertStatus(404);
    }
}
