<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use League\Csv\Writer;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {}

    public function dashboard(): JsonResponse
    {
        $data = $this->analyticsService->getDashboard(auth()->id());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function exportPdf(): Response
    {
        $data = $this->analyticsService->getDashboard(auth()->id());
        $ownerName = htmlspecialchars(auth()->user()->name ?? 'Owner');
        $dateRange = now()->startOfMonth()->format('M d, Y') . ' - ' . now()->format('M d, Y');

        $html = '<!DOCTYPE html><html><head><style>';
        $html .= 'body{font-family:sans-serif;padding:40px;color:#333}';
        $html .= 'h1{margin:0 0 5px}h2{margin:0 0 20px;color:#666;font-weight:normal}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-top:20px}';
        $html .= 'th,td{border:1px solid #ddd;padding:10px;text-align:left}';
        $html .= 'th{background:#f5f5f5;font-weight:bold}';
        $html .= '.metric-value{text-align:right}';
        $html .= '</style></head><body>';
        $html .= '<h1>Analytics Report</h1>';
        $html .= '<h2>' . $ownerName . ' &mdash; ' . $dateRange . '</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Metric</th><th class="metric-value">Value</th></tr>';
        $html .= '<tr><td>MRR</td><td class="metric-value">$' . number_format($data['mrr'], 2) . '</td></tr>';
        $html .= '<tr><td>ARR</td><td class="metric-value">$' . number_format($data['arr'], 2) . '</td></tr>';
        $html .= '<tr><td>Active Subscribers</td><td class="metric-value">' . $data['active_subscribers'] . '</td></tr>';
        $html .= '<tr><td>Churn Rate</td><td class="metric-value">' . $data['churn_rate'] . '%</td></tr>';
        $html .= '<tr><td>LTV</td><td class="metric-value">$' . number_format($data['ltv'], 2) . '</td></tr>';
        $html .= '<tr><td>Total Revenue</td><td class="metric-value">$' . number_format($data['total_revenue'], 2) . '</td></tr>';
        $html .= '<tr><td>NRR</td><td class="metric-value">' . $data['nrr'] . '%</td></tr>';
        $html .= '<tr><td>Expansion MRR</td><td class="metric-value">$' . number_format($data['expansion_mrr'], 2) . '</td></tr>';
        $html .= '</table></body></html>';

        $pdf = Pdf::loadHtml($html);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="analytics-report.pdf"',
        ]);
    }

    public function exportCsv(): Response
    {
        $data = $this->analyticsService->getDashboard(auth()->id());

        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->insertOne(['metric', 'value']);

        $csv->insertOne(['mrr', $data['mrr']]);
        $csv->insertOne(['arr', $data['arr']]);
        $csv->insertOne(['active_subscribers', $data['active_subscribers']]);
        $csv->insertOne(['churn_rate', $data['churn_rate']]);
        $csv->insertOne(['ltv', $data['ltv']]);
        $csv->insertOne(['total_revenue', $data['total_revenue']]);
        $csv->insertOne(['nrr', $data['nrr']]);
        $csv->insertOne(['expansion_mrr', $data['expansion_mrr']]);
        $csv->insertOne(['new_subscribers_this_month', $data['new_subscribers_this_month']]);
        $csv->insertOne(['canceled_this_month', $data['canceled_this_month']]);
        $csv->insertOne(['total_commission', $data['total_commission']]);
        $csv->insertOne(['trial_conversions_rate', $data['trial_conversions']['rate']]);

        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="analytics-report.csv"',
        ]);
    }
}
