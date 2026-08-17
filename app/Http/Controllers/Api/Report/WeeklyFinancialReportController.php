<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Notification\WeeklyFinancialReportResource;
use App\Models\WeeklyFinancialReport;
use App\Services\Report\WeeklyFinancialReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WeeklyFinancialReportController extends Controller
{
    public function __construct(private readonly WeeklyFinancialReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = WeeklyFinancialReport::query()
            ->where('user_id', $request->user()->id)
            ->latest('period_end')
            ->paginate(min(max((int) $request->integer('perPage', 12), 1), 50));

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => WeeklyFinancialReportResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'currentPage' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'lastPage' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $weeklyReport): JsonResponse
    {
        $report = $this->ownedReport($request, $weeklyReport);

        return response()->json([
            'success' => true,
            'data' => ['report' => (new WeeklyFinancialReportResource($report))->resolve($request)],
        ]);
    }

    public function pdf(Request $request, int $weeklyReport): Response
    {
        $report = $this->ownedReport($request, $weeklyReport);
        $content = $this->reports->renderPdf($report);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="weekly-report-'.$report->period_start->format('Ymd').'.pdf"',
            'Content-Length' => (string) strlen($content),
        ]);
    }

    private function ownedReport(Request $request, int $id): WeeklyFinancialReport
    {
        return WeeklyFinancialReport::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
