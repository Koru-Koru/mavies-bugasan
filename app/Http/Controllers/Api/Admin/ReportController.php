<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\InventoryCategory;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function show(Request $request, string $report): JsonResponse
    {
        return response()->json(['report' => $this->reports->build($report, $this->filters($request))]);
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $data = $request->validate([
            'report' => ['required', Rule::in(ReportService::REPORTS)],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ]);

        $report = $this->reports->build($data['report'], $this->filters($request));
        $stamp = now()->format('Ymd-Hi');
        $base = $report['key'].'-'.$stamp;

        if ($data['format'] === 'csv') {
            return $this->csv($report, $base.'.csv');
        }

        return Pdf::loadView('reports.document', ['report' => $report, 'shop' => config('app.name')])
            ->setPaper('a4', 'landscape')
            ->download($base.'.pdf');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'basis' => ['nullable', Rule::in(ReportService::BASES)],
            'include_archived' => ['nullable', 'boolean'],
            'inventory_id' => ['nullable', 'integer', Rule::exists('inventory', 'inventory_id')],
            'category' => ['nullable', Rule::in(InventoryCategory::ALL)],
        ]);
    }

    private function csv(array $report, string $filename): StreamedResponse
    {
        $columns = $report['columns'];
        $rows = $report['rows'];
        $totals = $report['totals'] ?? null;

        return response()->streamDownload(function () use ($report, $columns, $rows, $totals) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the peso sign correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$report['title']]);
            fputcsv($out, [$report['subtitle']]);
            fputcsv($out, []);

            fputcsv($out, array_column($columns, 'label'));

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = $this->cell($row[$col['key']] ?? null, $col['format']);
                }
                fputcsv($out, $line);
            }

            if ($totals) {
                $line = [];
                foreach ($columns as $i => $col) {
                    if ($i === 0) {
                        $line[] = 'TOTAL';
                    } elseif (array_key_exists($col['key'], $totals)) {
                        $line[] = $this->cell($totals[$col['key']], $col['format']);
                    } else {
                        $line[] = '';
                    }
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cell($value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($format) {
            'money' => number_format((float) $value, 2, '.', ''),
            'int' => (string) (int) $value,
            default => (string) $value,
        };
    }
}
