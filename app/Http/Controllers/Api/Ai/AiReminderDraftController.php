<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\GenerateReminderDraftRequest;
use App\Models\Invoice;
use App\Services\Ai\Features\PaymentReminderService;
use Illuminate\Http\JsonResponse;

class AiReminderDraftController extends Controller
{
    public function store(GenerateReminderDraftRequest $request, int $invoice, PaymentReminderService $reminders): JsonResponse
    {
        $model = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($invoice);

        $result = $reminders->draft($request->user(), $model, $request->validated());

        return response()->json([
            'success' => true,
            'message' => $result['aiAvailable']
                ? 'Draft pengingat siap. Silakan tinjau dan sunting sebelum dikirim.'
                : 'AI sedang tidak tersedia. Menampilkan draft template.',
            'data' => [
                'invoice' => [
                    'id' => $model->id,
                    'invoiceNumber' => $model->number,
                    'customerName' => $model->customer_name,
                    'balanceDue' => $model->balance_due,
                    'dueDate' => $model->due_date?->format('Y-m-d'),
                ],
                'reminder' => $result,
                // The draft is never sent from here; the user posts it to the existing endpoint.
                'sendWith' => [
                    'method' => 'POST',
                    'url' => "/api/v1/invoices/{$model->id}/send",
                    'body' => ['channel' => $result['channel'], 'message' => '<draft yang sudah Anda sunting>'],
                ],
            ],
        ]);
    }
}
