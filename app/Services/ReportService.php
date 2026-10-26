<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Six read-only reports, all derived from v_orders / inventory / damage_logs.
 * Nothing here writes; every figure traces back to the same computed view the
 * integrity checks use, so reports agree with v_orders to the peso.
 *
 * Each report is normalised into the same shape so the SPA, the CSV writer and
 * the PDF view can all render it without knowing which report it is:
 *
 *   title, subtitle, metrics[], columns[], rows[], totals|null
 */
class ReportService
{
    public const BASES = ['ordered', 'delivered'];

    public const REPORTS = ['summary', 'top-varieties', 'variety', 'by-day', 'inventory', 'damage', 'credit'];

    public function build(string $report, array $filters = []): array
    {
        $method = str_replace('-', '_', $report);

        if (! in_array($report, self::REPORTS, true) || ! method_exists($this, $method)) {
            throw new InvalidArgumentException("Unknown report: {$report}");
        }

        return $this->{$method}($filters);
    }

    /**
     * @return array{0:string,1:Carbon,2:Carbon,3:string}
     */
    private function window(array $filters): array
    {
        $basis = in_array($filters['basis'] ?? null, self::BASES, true) ? $filters['basis'] : 'ordered';

        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth()->startOfDay();
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $subtitle = $from->format('d M Y').' – '.$to->format('d M Y');
        if ($basis === 'ordered') {
            $subtitle .= ' · as ordered';
        } else {
            $subtitle .= ' · as delivered';
        }

        return [$basis, $from, $to, $subtitle];
    }

    private function summary(array $filters): array
    {
        [$basis, $from, $to, $subtitle] = $this->window($filters);

        $scope = $basis === 'delivered'
            ? "status = 'Delivered' AND delivered_at BETWEEN ? AND ?"
            : "status <> 'Cancelled' AND created_at BETWEEN ? AND ?";

        $rows = DB::select(
            "SELECT status,
                    COUNT(*)                              AS orders,
                    COALESCE(SUM(total_sacks), 0)         AS sacks,
                    COALESCE(SUM(amount_due), 0)          AS revenue,
                    COALESCE(SUM(amount_paid), 0)         AS collected,
                    COALESCE(SUM(GREATEST(balance, 0)), 0) AS outstanding
               FROM v_orders
              WHERE {$scope}
              GROUP BY status
              ORDER BY FIELD(status, 'Pending','Confirmed','Ready for Pickup','Out for Delivery','Delivered')",
            [$from, $to]
        );

        $cancelled = $basis === 'delivered' ? 0 : (int) DB::table('orders')
            ->where('status', 'Cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $metricRows = array_map(fn ($r) => $this->numericRow($r, ['orders', 'sacks', 'revenue', 'collected', 'outstanding']), $rows);

        $totals = [
            'orders' => array_sum(array_column($metricRows, 'orders')),
            'sacks' => array_sum(array_column($metricRows, 'sacks')),
            'revenue' => array_sum(array_column($metricRows, 'revenue')),
            'collected' => array_sum(array_column($metricRows, 'collected')),
            'outstanding' => array_sum(array_column($metricRows, 'outstanding')),
        ];

        return [
            'key' => 'summary',
            'title' => 'Sales summary',
            'subtitle' => $subtitle,
            'metrics' => [
                $this->metric('Orders', $totals['orders'], 'int'),
                $this->metric('Sacks', $totals['sacks'], 'int'),
                $this->metric('Revenue', $totals['revenue'], 'money'),
                $this->metric('Collected', $totals['collected'], 'money'),
                $this->metric('Outstanding', $totals['outstanding'], 'money', $totals['outstanding'] > 0 ? 'warning' : 'muted'),
                $this->metric('Cancelled', $cancelled, 'int', 'muted'),
            ],
            'columns' => [
                $this->column('status', 'Status'),
                $this->column('orders', 'Orders', 'right', 'int'),
                $this->column('sacks', 'Sacks', 'right', 'int'),
                $this->column('revenue', 'Revenue', 'right', 'money'),
                $this->column('collected', 'Collected', 'right', 'money'),
                $this->column('outstanding', 'Outstanding', 'right', 'money'),
            ],
            'rows' => $metricRows,
            'totals' => $totals,
        ];
    }

    private function top_varieties(array $filters): array
    {
        [$basis, $from, $to, $subtitle] = $this->window($filters);

        $scope = $basis === 'delivered'
            ? "o.status = 'Delivered' AND o.delivered_at BETWEEN ? AND ?"
            : "o.status <> 'Cancelled' AND o.created_at BETWEEN ? AND ?";

        $bindings = [$from, $to];
        $category = $filters['category'] ?? null;

        if (! empty($category)) {
            $scope .= ' AND i.category = ?';
            $bindings[] = $category;
            $subtitle .= ' · '.$category;
        }

        $raw = DB::select(
            "SELECT i.inventory_id,
                    i.name,
                    i.category,
                    SUM(oi.sacks)                                          AS sacks,
                    SUM(oi.sacks * COALESCE(oi.final_unit_price, oi.unit_price)) AS revenue,
                    COUNT(DISTINCT o.order_id)                             AS orders
               FROM order_items oi
               JOIN orders o    ON o.order_id = oi.order_id
               JOIN inventory i ON i.inventory_id = oi.inventory_id
              WHERE {$scope}
              GROUP BY i.inventory_id, i.name, i.category
              ORDER BY sacks DESC, revenue DESC
              LIMIT 100",
            $bindings
        );

        $rows = array_map(fn ($r) => $this->numericRow($r, ['sacks', 'revenue', 'orders']), $raw);

        $totals = [
            'sacks' => array_sum(array_column($rows, 'sacks')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
        ];

        return [
            'key' => 'top-varieties',
            'title' => 'Top varieties',
            'subtitle' => $subtitle,
            'metrics' => [
                $this->metric('Varieties sold', count($rows), 'int'),
                $this->metric('Sacks sold', $totals['sacks'], 'int'),
                $this->metric('Revenue', $totals['revenue'], 'money'),
                $this->metric('Best seller', $rows[0]['name'] ?? '—', 'text'),
            ],
            'columns' => [
                $this->column('name', 'Variety'),
                $this->column('category', 'Category'),
                $this->column('sacks', 'Sacks', 'right', 'int'),
                $this->column('orders', 'Orders', 'right', 'int'),
                $this->column('revenue', 'Revenue', 'right', 'money'),
            ],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Sales for one chosen variety, broken down by day. This is the drill-down
     * behind the "By variety" tab so a single variety's numbers can be read
     * without scanning the all-varieties table.
     */
    private function variety(array $filters): array
    {
        [$basis, $from, $to, $subtitle] = $this->window($filters);

        $variety = DB::table('inventory')
            ->select('inventory_id', 'name', 'category', 'is_active')
            ->where('inventory_id', (int) ($filters['inventory_id'] ?? 0))
            ->first();

        $columns = [
            $this->column('day', 'Date'),
            $this->column('orders', 'Orders', 'right', 'int'),
            $this->column('sacks', 'Sacks', 'right', 'int'),
            $this->column('revenue', 'Revenue', 'right', 'money'),
        ];

        if ($variety === null) {
            return [
                'key' => 'variety',
                'title' => 'Sales by variety',
                'subtitle' => 'Choose a variety',
                'metrics' => [],
                'columns' => $columns,
                'rows' => [],
                'totals' => null,
                'empty' => 'Choose a variety to see its sales.',
            ];
        }

        $label = $variety->name.($variety->is_active ? '' : ' (archived)');
        $subtitle .= ' · '.$label;

        $dayColumn = $basis === 'delivered' ? 'o.delivered_at' : 'o.created_at';
        $scope = $basis === 'delivered'
            ? "o.status = 'Delivered' AND o.delivered_at BETWEEN ? AND ?"
            : "o.status <> 'Cancelled' AND o.created_at BETWEEN ? AND ?";

        $raw = DB::select(
            "SELECT DATE({$dayColumn}) AS day,
                    COUNT(DISTINCT o.order_id) AS orders,
                    COALESCE(SUM(oi.sacks), 0) AS sacks,
                    COALESCE(SUM(oi.sacks * COALESCE(oi.final_unit_price, oi.unit_price)), 0) AS revenue
               FROM order_items oi
               JOIN orders o ON o.order_id = oi.order_id
              WHERE {$scope}
                AND oi.inventory_id = ?
              GROUP BY DATE({$dayColumn})
              ORDER BY day",
            [$from, $to, $variety->inventory_id]
        );

        $rows = array_map(fn ($r) => $this->numericRow($r, ['orders', 'sacks', 'revenue']), $raw);

        $totals = [
            'orders' => array_sum(array_column($rows, 'orders')),
            'sacks' => array_sum(array_column($rows, 'sacks')),
        ];
        $totals['revenue'] = array_sum(array_column($rows, 'revenue'));

        return [
            'key' => 'variety',
            'title' => 'Sales — '.$variety->name,
            'subtitle' => $subtitle,
            'metrics' => [
                $this->metric('Sacks sold', $totals['sacks'], 'int'),
                $this->metric('Revenue', $totals['revenue'], 'money'),
                $this->metric('Orders', $totals['orders'], 'int'),
                $this->metric('Avg / sack', $totals['sacks'] > 0 ? $totals['revenue'] / $totals['sacks'] : 0, 'money'),
            ],
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'empty' => 'No sales for '.$variety->name.' in this period.',
        ];
    }

    private function by_day(array $filters): array
    {
        [$basis, $from, $to, $subtitle] = $this->window($filters);

        $column = $basis === 'delivered' ? 'delivered_at' : 'created_at';
        $scope = $basis === 'delivered'
            ? "status = 'Delivered' AND delivered_at BETWEEN ? AND ?"
            : "status <> 'Cancelled' AND created_at BETWEEN ? AND ?";

        $raw = DB::select(
            "SELECT DATE({$column})                  AS day,
                    COUNT(*)                         AS orders,
                    COALESCE(SUM(total_sacks), 0)    AS sacks,
                    COALESCE(SUM(amount_due), 0)     AS revenue,
                    COALESCE(SUM(amount_paid), 0)    AS collected
               FROM v_orders
              WHERE {$scope}
              GROUP BY DATE({$column})
              ORDER BY day",
            [$from, $to]
        );

        $rows = array_map(fn ($r) => $this->numericRow($r, ['orders', 'sacks', 'revenue', 'collected']), $raw);

        $totals = [
            'orders' => array_sum(array_column($rows, 'orders')),
            'sacks' => array_sum(array_column($rows, 'sacks')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'collected' => array_sum(array_column($rows, 'collected')),
        ];

        return [
            'key' => 'by-day',
            'title' => 'Sales by day',
            'subtitle' => $subtitle,
            'metrics' => [
                $this->metric('Days', count($rows), 'int'),
                $this->metric('Orders', $totals['orders'], 'int'),
                $this->metric('Sacks', $totals['sacks'], 'int'),
                $this->metric('Revenue', $totals['revenue'], 'money'),
            ],
            'columns' => [
                $this->column('day', 'Date'),
                $this->column('orders', 'Orders', 'right', 'int'),
                $this->column('sacks', 'Sacks', 'right', 'int'),
                $this->column('revenue', 'Revenue', 'right', 'money'),
                $this->column('collected', 'Collected', 'right', 'money'),
            ],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    private function inventory(array $filters): array
    {
        $includeArchived = (bool) ($filters['include_archived'] ?? false);

        $query = DB::table('inventory')
            ->selectRaw('inventory_id, name, category, stock, low_stock_threshold, unit_price, wholesale_price, is_active')
            ->selectRaw('stock * COALESCE(NULLIF(wholesale_price, 0), unit_price) AS stock_value');

        if (! $includeArchived) {
            $query->where('is_active', true);
        }

        $rows = $query->orderByDesc('is_active')->orderBy('name')->get()->map(function ($r) {
            $r = (array) $r;
            $r['stock'] = (int) $r['stock'];
            $r['low_stock_threshold'] = (int) $r['low_stock_threshold'];
            $r['unit_price'] = (float) $r['unit_price'];
            $r['wholesale_price'] = (float) $r['wholesale_price'];
            $r['stock_value'] = (float) $r['stock_value'];
            $r['is_active'] = (bool) $r['is_active'];

            return $r;
        })->all();

        $totalSacks = array_sum(array_column($rows, 'stock'));
        $totalValue = array_sum(array_column($rows, 'stock_value'));
        $lowCount = count(array_filter($rows, fn ($r) => $r['is_active'] && $r['stock'] <= $r['low_stock_threshold']));

        return [
            'key' => 'inventory',
            'title' => 'Inventory valuation',
            'subtitle' => 'Current stock on hand',
            'metrics' => [
                $this->metric('Varieties', count($rows), 'int'),
                $this->metric('Sacks on hand', $totalSacks, 'int'),
                $this->metric('Stock value', $totalValue, 'money'),
                $this->metric('Low stock', $lowCount, 'int', $lowCount > 0 ? 'warning' : 'muted'),
            ],
            'columns' => [
                $this->column('name', 'Variety'),
                $this->column('category', 'Category'),
                $this->column('stock', 'Stock', 'right', 'int'),
                $this->column('unit_price', 'Retail', 'right', 'money'),
                $this->column('wholesale_price', 'Wholesale', 'right', 'money'),
                $this->column('stock_value', 'Value', 'right', 'money'),
            ],
            'rows' => $rows,
            'totals' => [
                'stock' => $totalSacks,
                'stock_value' => $totalValue,
            ],
        ];
    }

    private function damage(array $filters): array
    {
        [$basis, $from, $to, $subtitle] = $this->window($filters);

        $raw = DB::select(
            "SELECT dl.damage_code,
                    dl.date_logged,
                    dl.variety_name AS name,
                    dl.sacks,
                    dl.damage_type,
                    dl.notes,
                    a.admin_name AS logged_by_name
               FROM damage_logs dl
               LEFT JOIN admins a ON a.admin_id = dl.logged_by
              WHERE dl.date_logged BETWEEN ? AND ?
              ORDER BY dl.date_logged DESC, dl.damage_log_id DESC",
            [$from->toDateString(), $to->toDateString()]
        );

        $rows = array_map(fn ($r) => $this->numericRow($r, ['sacks']), $raw);
        $totalSacks = array_sum(array_column($rows, 'sacks'));

        return [
            'key' => 'damage',
            'title' => 'Damage & spoilage',
            'subtitle' => $from->format('d M Y').' – '.$to->format('d M Y'),
            'metrics' => [
                $this->metric('Incidents', count($rows), 'int'),
                $this->metric('Sacks lost', $totalSacks, 'int', $totalSacks > 0 ? 'warning' : 'muted'),
                $this->metric('Varieties affected', count(array_unique(array_column($rows, 'name'))), 'int'),
            ],
            'columns' => [
                $this->column('damage_code', 'Code'),
                $this->column('date_logged', 'Date'),
                $this->column('name', 'Variety'),
                $this->column('sacks', 'Sacks', 'right', 'int'),
                $this->column('damage_type', 'Type'),
                $this->column('logged_by_name', 'Logged by'),
            ],
            'rows' => $rows,
            'totals' => ['sacks' => $totalSacks],
        ];
    }

    private function credit(array $filters): array
    {
        $query = DB::table('v_orders')
            ->selectRaw('customer_id, customer_name, phone,
                         COUNT(*) AS open_orders,
                         COALESCE(SUM(amount_due), 0) AS due,
                         COALESCE(SUM(amount_paid), 0) AS paid,
                         COALESCE(SUM(GREATEST(balance, 0)), 0) AS outstanding,
                         MAX(created_at) AS last_order')
            ->where('status', '<>', 'Cancelled')
            ->whereRaw('balance > 0.005')
            ->groupBy('customer_id', 'customer_name', 'phone')
            ->orderByDesc('outstanding');

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        $rows = $query->get()->map(function ($r) {
            $r = (array) $r;
            $r['open_orders'] = (int) $r['open_orders'];
            $r['due'] = (float) $r['due'];
            $r['paid'] = (float) $r['paid'];
            $r['outstanding'] = (float) $r['outstanding'];

            return $r;
        })->all();

        $totalOutstanding = array_sum(array_column($rows, 'outstanding'));
        $openOrders = array_sum(array_column($rows, 'open_orders'));

        return [
            'key' => 'credit',
            'title' => 'Credit / accounts receivable',
            'subtitle' => 'Unpaid and partly-paid orders',
            'metrics' => [
                $this->metric('Customers owing', count($rows), 'int'),
                $this->metric('Open orders', $openOrders, 'int'),
                $this->metric('Total outstanding', $totalOutstanding, 'money', $totalOutstanding > 0 ? 'warning' : 'muted'),
            ],
            'columns' => [
                $this->column('customer_name', 'Customer'),
                $this->column('phone', 'Phone'),
                $this->column('open_orders', 'Open orders', 'right', 'int'),
                $this->column('due', 'Billed', 'right', 'money'),
                $this->column('paid', 'Paid', 'right', 'money'),
                $this->column('outstanding', 'Balance', 'right', 'money'),
                $this->column('last_order', 'Last order'),
            ],
            'rows' => $rows,
            'totals' => ['outstanding' => $totalOutstanding],
        ];
    }

    private function numericRow(object $row, array $floatKeys): array
    {
        $data = (array) $row;
        foreach ($floatKeys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = (float) $data[$key];
            }
        }

        return $data;
    }

    private function metric(string $label, $value, string $format, string $tone = 'default'): array
    {
        return ['label' => $label, 'value' => $value, 'format' => $format, 'tone' => $tone];
    }

    private function column(string $key, string $label, string $align = 'left', string $format = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'align' => $align, 'format' => $format];
    }
}
