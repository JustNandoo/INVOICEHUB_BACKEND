<?php

namespace App\Http\Controllers\Api\Tax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tax\MarkTaxReportReportedRequest;
use App\Http\Requests\Api\Tax\MonthlyTaxReportsRequest;
use App\Http\Requests\Api\Tax\TaxPeriodRequest;
use App\Http\Resources\Api\TaxpayerProfileResource;
use App\Http\Resources\Api\TaxReportResource;
use App\Services\Tax\TaxReportPdfService;
use App\Services\Tax\TaxReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxReportController extends Controller
{
    public function __construct(
        private readonly TaxReportService $reports,
        private readonly TaxReportPdfService $pdf,
    ) {}

    public function overview(TaxPeriodRequest $request): JsonResponse
    {
        $year = (int) $request->validated('year');
        $month = (int) $request->validated('month');
        $report = $this->reports->preview($request->user(), $year, $month);
        $previous = $month === 1
            ? $this->reports->preview($request->user(), $year - 1, 12)
            : $this->reports->preview($request->user(), $year, $month - 1);
        $yearReports = collect(range(1, $month))->map(
            fn (int $itemMonth) => $this->reports->preview($request->user(), $year, $itemMonth),
        );
        $previousRevenue = $previous->total_revenue;
        $growth = $previousRevenue === 0 ? null : round((($report->total_revenue - $previousRevenue) / $previousRevenue) * 100, 2);

        return response()->json(['success' => true, 'data' => [
            'taxProfile' => (new TaxpayerProfileResource($this->reports->profile($request->user())))->resolve($request),
            'report' => (new TaxReportResource($report))->resolve($request),
            'yearlySummary' => [
                'year' => $year,
                'totalRevenue' => $yearReports->sum('total_revenue'),
                'taxableRevenue' => $yearReports->sum('taxable_revenue'),
                'estimatedTax' => $yearReports->sum('estimated_tax'),
                'paidInvoiceCount' => $yearReports->sum('paid_invoice_count'),
            ],
            'comparison' => [
                'previousRevenue' => $previousRevenue,
                'currentRevenue' => $report->total_revenue,
                'growthPercentage' => $growth,
            ],
        ]]);
    }

    public function monthly(MonthlyTaxReportsRequest $request): JsonResponse
    {
        $items = $this->reports->monthly(
            $request->user(), (int) $request->validated('year'), (int) $request->validated('months'),
        );

        return response()->json(['success' => true, 'data' => [
            'monthlyReports' => TaxReportResource::collection($items)->resolve($request),
        ]]);
    }

    public function show(Request $request, int $year, int $month): JsonResponse
    {
        $this->validatePeriod($year, $month);
        $report = $this->reports->preview($request->user(), $year, $month);

        return response()->json(['success' => true, 'data' => [
            'report' => (new TaxReportResource($report))->resolve($request),
        ]]);
    }

    public function recalculate(Request $request, int $year, int $month): JsonResponse
    {
        $this->validatePeriod($year, $month);
        $report = $this->reports->recalculate($request->user(), $year, $month);

        return response()->json([
            'success' => true, 'message' => 'Laporan pajak berhasil dihitung ulang.',
            'data' => ['report' => (new TaxReportResource($report))->resolve($request)],
        ]);
    }

    public function finalize(Request $request, int $year, int $month): JsonResponse
    {
        $this->validatePeriod($year, $month);
        $report = $this->reports->finalize($request->user(), $year, $month);

        return response()->json([
            'success' => true, 'message' => 'Laporan pajak berhasil difinalisasi dan dikunci.',
            'data' => ['report' => (new TaxReportResource($report))->resolve($request)],
        ]);
    }

    public function markReported(MarkTaxReportReportedRequest $request, int $year, int $month): JsonResponse
    {
        $this->validatePeriod($year, $month);
        $report = $this->reports->markReported($request->user(), $year, $month, $request->validated());

        return response()->json([
            'success' => true, 'message' => 'Laporan berhasil ditandai sebagai sudah dilaporkan.',
            'data' => ['report' => (new TaxReportResource($report))->resolve($request)],
        ]);
    }

    public function monthlyPdf(Request $request, int $year, int $month): Response
    {
        $this->validatePeriod($year, $month);
        $report = $this->reports->ownedReport($request->user(), $year, $month);
        abort_unless($report->isLocked(), 422, 'Finalize this report before downloading its PDF.');
        $content = $this->pdf->monthly($report);

        return $this->pdfResponse($content, "tax-report-{$year}-{$month}.pdf");
    }

    public function annualPdf(Request $request, int $year): Response
    {
        abort_unless($year >= 2000 && $year <= 2100, 404);

        return $this->pdfResponse($this->pdf->annual($request->user(), $year), "annual-tax-report-{$year}.pdf");
    }

    private function validatePeriod(int $year, int $month): void
    {
        abort_unless($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12, 404);
    }

    private function pdfResponse(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
        ]);
    }
}
