<?php

namespace App\Services\Tax;

use App\Models\TaxPeriodReport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

class TaxReportPdfService
{
    public function monthly(TaxPeriodReport $report): string
    {
        $report->loadMissing(['owner', 'taxRule', 'sources']);

        return Pdf::loadView('pdf.tax-report', ['report' => $report])->setPaper('a4')->output();
    }

    public function annual(User $user, int $year): string
    {
        $reports = TaxPeriodReport::query()->with('taxRule')->where('user_id', $user->id)
            ->where('year', $year)->whereIn('status', [TaxPeriodReport::STATUS_READY, TaxPeriodReport::STATUS_REPORTED])
            ->orderBy('month')->get();

        if ($reports->isEmpty()) {
            abort(404, 'No finalized tax reports are available for this year.');
        }

        return Pdf::loadView('pdf.annual-tax-report', compact('user', 'year', 'reports'))->setPaper('a4')->output();
    }
}
