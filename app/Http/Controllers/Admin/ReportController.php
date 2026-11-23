<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoPeriod;
use App\Services\PeriodReport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports: a recap row per PO period (sales, HPP, profit) with totals,
 * one period in detail, and a CSV of the recap for Excel.
 */
class ReportController extends Controller
{
    public function __construct(private PeriodReport $report)
    {
    }

    public function index(): View
    {
        $rows = $this->rows();

        return view('admin.reports.index', [
            'rows' => $rows,
            'total' => $this->sum($rows),
        ]);
    }

    public function show(PoPeriod $period): View
    {
        return view('admin.reports.show', ['period' => $period] + $this->report->build($period));
    }

    public function csv(): StreamedResponse
    {
        $rows = $this->rows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8; ";" is the list separator Excel uses in Indonesian.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Periode', 'Mulai', 'Tutup', 'Status', 'Pesanan', 'Dibatalkan', 'Botol', 'Penjualan', 'Sudah dibayar',
                'Belum dibayar', 'HPP (COGS)', 'Untung kotor', 'Margin %', 'Belanja bahan', 'Biaya lain', 'Untung bersih', 'Menu terlaris',
            ], ';');
            foreach ($rows as $r) {
                $p = $r['profit'];
                fputcsv($out, [
                    $r['period']->name,
                    $r['period']->opens_at?->format('Y-m-d H:i'),
                    $r['period']->closes_at?->format('Y-m-d H:i'),
                    $r['period']->status,
                    $r['orders'], $r['cancelled'], $r['bottles'],
                    $p['revenue'], $p['paid'], $p['unpaid'], $p['cogs'], $p['gross'], $p['margin'],
                    $p['spent'], $p['extraSpent'], $p['cash'],
                    $r['best'] ?? '',
                ], ';');
            }
            fclose($out);
        }, 'rekap-yokancha-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<array>
     */
    private function rows(): array
    {
        return PoPeriod::orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(function (PoPeriod $period) {
                $data = $this->report->build($period);

                return [
                    'period' => $period,
                    'orders' => $data['orders']->count(),
                    'cancelled' => $data['cancelledCount'],
                    'bottles' => $data['bottles'],
                    'profit' => $data['profit'],
                    'best' => $data['byProduct'][0]['label'] ?? null,
                    'bestBottles' => $data['byProduct'][0]['bottles'] ?? 0,
                ];
            })->all();
    }

    /**
     * @param list<array> $rows
     */
    private function sum(array $rows): array
    {
        $total = ['orders' => 0, 'bottles' => 0, 'revenue' => 0, 'paid' => 0, 'unpaid' => 0, 'cogs' => 0, 'gross' => 0, 'cash' => 0];
        foreach ($rows as $r) {
            $total['orders'] += $r['orders'];
            $total['bottles'] += $r['bottles'];
            foreach (['revenue', 'paid', 'unpaid', 'cogs', 'gross', 'cash'] as $key) {
                $total[$key] += $r['profit'][$key];
            }
        }
        $total['margin'] = $total['revenue'] > 0 ? (int) round($total['gross'] / $total['revenue'] * 100) : 0;
        $total['periods'] = count($rows);

        return $total;
    }
}
