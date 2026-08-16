<?php

namespace App\Http\Controllers\Api\Tax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tax\ListTaxFindingsRequest;
use App\Http\Requests\Api\Tax\ResolveTaxFindingRequest;
use App\Http\Resources\Api\TaxAuditFindingResource;
use App\Models\TaxAuditFinding;
use App\Models\TaxPeriodReport;
use App\Services\Tax\TaxLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxAuditFindingController extends Controller
{
    public function index(ListTaxFindingsRequest $request): JsonResponse
    {
        $query = TaxAuditFinding::query()->where('user_id', $request->user()->id)
            ->where('year', $request->validated('year'))->where('month', $request->validated('month'));
        if ($request->validated('status')) {
            $query->where('status', $request->validated('status'));
        }
        $findings = $query->orderByRaw("case severity when 'critical' then 1 when 'warning' then 2 else 3 end")
            ->orderByDesc('amount')->get();

        return response()->json(['success' => true, 'data' => [
            'totalFindings' => $findings->count(),
            'findings' => TaxAuditFindingResource::collection($findings)->resolve($request),
        ]]);
    }

    public function include(Request $request, int $finding, TaxLedgerService $ledger): JsonResponse
    {
        $model = $this->ownedFinding($request, $finding);
        abort_unless($model->status === TaxAuditFinding::STATUS_OPEN, 422, 'This finding has already been resolved.');
        $entry = $ledger->includeFinding($request->user(), $model);
        $this->refreshReportFindingCount($model, true);

        return response()->json([
            'success' => true, 'message' => 'Transaksi berhasil dimasukkan ke ledger pajak.',
            'data' => [
                'finding' => (new TaxAuditFindingResource($model->fresh()))->resolve($request),
                'ledgerEntryId' => $entry->id,
                'reportNeedsRecalculation' => true,
            ],
        ]);
    }

    public function resolve(ResolveTaxFindingRequest $request, int $finding): JsonResponse
    {
        $model = $this->ownedFinding($request, $finding);
        abort_unless($model->status === TaxAuditFinding::STATUS_OPEN, 422, 'This finding has already been resolved.');
        $status = $request->validated('resolution') === 'corrected'
            ? TaxAuditFinding::STATUS_RESOLVED : TaxAuditFinding::STATUS_DISMISSED;
        $model->update([
            'status' => $status, 'resolution' => $request->validated('resolution'),
            'notes' => $request->validated('notes'), 'resolved_by' => $request->user()->id, 'resolved_at' => now(),
        ]);
        $this->refreshReportFindingCount($model);

        return response()->json([
            'success' => true, 'message' => 'Temuan pajak berhasil diselesaikan.',
            'data' => ['finding' => (new TaxAuditFindingResource($model->fresh()))->resolve($request)],
        ]);
    }

    private function ownedFinding(Request $request, int $finding): TaxAuditFinding
    {
        return TaxAuditFinding::query()->where('user_id', $request->user()->id)->findOrFail($finding);
    }

    private function refreshReportFindingCount(TaxAuditFinding $finding, bool $needsRecalculation = false): void
    {
        if (! $finding->tax_period_report_id) {
            return;
        }

        $finding->report()->update([
            'findings_count' => TaxAuditFinding::query()->where('tax_period_report_id', $finding->tax_period_report_id)
                ->where('status', TaxAuditFinding::STATUS_OPEN)->count(),
            ...($needsRecalculation ? ['status' => TaxPeriodReport::STATUS_REVISION_REQUIRED] : []),
        ]);
    }
}
