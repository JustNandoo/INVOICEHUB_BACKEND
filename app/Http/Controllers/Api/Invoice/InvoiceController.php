<?php

namespace App\Http\Controllers\Api\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Invoice\ListInvoicesRequest;
use App\Http\Requests\Api\Invoice\SendInvoiceRequest;
use App\Http\Requests\Api\Invoice\StoreInvoicePaymentRequest;
use App\Http\Requests\Api\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Api\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Api\InvoicePaymentResource;
use App\Http\Resources\Api\InvoiceResource;
use App\Http\Resources\Api\InvoiceSummaryResource;
use App\Models\Invoice;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Invoice\InvoicePdfService;
use App\Services\Invoice\InvoiceQueryService;
use App\Services\Invoice\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceQueryService $queries,
        private readonly InvoiceService $invoices,
        private readonly InvoiceDeliveryService $delivery,
        private readonly InvoicePdfService $pdf,
    ) {}

    public function index(ListInvoicesRequest $request): JsonResponse
    {
        $paginator = $this->queries->paginate($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => InvoiceSummaryResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'currentPage' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'lastPage' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'previousPageUrl' => $paginator->previousPageUrl(),
                    'nextPageUrl' => $paginator->nextPageUrl(),
                ],
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['summary' => $this->queries->summary($request->user())],
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => $invoice->status === Invoice::STATUS_DRAFT
                ? 'Draft invoice berhasil disimpan.'
                : 'Invoice berhasil dibuat.',
            'data' => ['invoice' => (new InvoiceResource($invoice))->resolve($request)],
        ], 201);
    }

    public function show(Request $request, int $invoice): JsonResponse
    {
        $model = $this->ownedInvoice($request, $invoice);

        return response()->json([
            'success' => true,
            'data' => ['invoice' => (new InvoiceResource($this->invoices->loadDetail($model)))->resolve($request)],
        ]);
    }

    public function update(UpdateInvoiceRequest $request, int $invoice): JsonResponse
    {
        $model = $this->ownedInvoice($request, $invoice);
        $updated = $this->invoices->update($model, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Invoice berhasil diperbarui.',
            'data' => ['invoice' => (new InvoiceResource($updated))->resolve($request)],
        ]);
    }

    public function destroy(Request $request, int $invoice): JsonResponse
    {
        $action = $this->invoices->deleteOrVoid(
            $this->ownedInvoice($request, $invoice),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => $action === 'deleted'
                ? 'Draft invoice berhasil dihapus.'
                : 'Invoice berhasil dibatalkan.',
            'data' => ['action' => $action],
        ]);
    }

    public function send(SendInvoiceRequest $request, int $invoice): JsonResponse
    {
        $result = $this->delivery->send(
            $this->ownedInvoice($request, $invoice),
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => $result['channel'] === 'email'
                ? 'Invoice berhasil dikirim melalui email.'
                : 'Tautan pengiriman WhatsApp berhasil dibuat.',
            'data' => [
                'invoice' => (new InvoiceResource($result['invoice']))->resolve($request),
                'delivery' => [
                    'channel' => $result['channel'],
                    'recipient' => $result['recipient'],
                    'actionUrl' => $result['actionUrl'],
                ],
            ],
        ]);
    }

    public function storePayment(StoreInvoicePaymentRequest $request, int $invoice): JsonResponse
    {
        $result = $this->invoices->recordPayment(
            $this->ownedInvoice($request, $invoice),
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => $result['invoice']->status === Invoice::STATUS_PAID
                ? 'Pembayaran dicatat dan invoice telah lunas.'
                : 'Pembayaran sebagian berhasil dicatat.',
            'data' => [
                'payment' => (new InvoicePaymentResource($result['payment']))->resolve($request),
                'invoice' => (new InvoiceResource($result['invoice']))->resolve($request),
            ],
        ], 201);
    }

    public function downloadPdf(Request $request, int $invoice): Response
    {
        $model = $this->ownedInvoice($request, $invoice);
        $content = $this->pdf->render($model);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$model->number.'.pdf"',
            'Content-Length' => (string) strlen($content),
        ]);
    }

    private function ownedInvoice(Request $request, int $invoice): Invoice
    {
        return Invoice::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($invoice);
    }
}
