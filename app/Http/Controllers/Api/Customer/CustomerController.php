<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\CustomerSummaryRequest;
use App\Http\Requests\Api\Customer\ListCustomerInvoicesRequest;
use App\Http\Requests\Api\Customer\ListCustomersRequest;
use App\Http\Requests\Api\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\Customer\UpdateCustomerRequest;
use App\Http\Resources\Api\CustomerResource;
use App\Http\Resources\Api\InvoiceSummaryResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Customer\CustomerQueryService;
use App\Services\Customer\CustomerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerQueryService $queries,
        private readonly CustomerService $customers,
    ) {}

    public function index(ListCustomersRequest $request): JsonResponse
    {
        $paginator = $this->queries->paginate($request->user(), $request->validated());

        return response()->json(['success' => true, 'data' => [
            'customers' => CustomerResource::collection($paginator->items())->resolve($request),
            'pagination' => $this->pagination($paginator),
        ]]);
    }

    public function summary(CustomerSummaryRequest $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'summary' => $this->queries->summary(
                $request->user(), (int) $request->validated('year'), (int) $request->validated('month'),
            ),
        ]]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->user(), $request->validated());

        return response()->json([
            'success' => true, 'message' => 'Pelanggan berhasil ditambahkan.',
            'data' => ['customer' => (new CustomerResource($this->queries->detail($customer)))->resolve($request)],
        ], 201);
    }

    public function show(Request $request, int $customer): JsonResponse
    {
        $model = $this->ownedCustomer($request, $customer);

        return response()->json(['success' => true, 'data' => [
            'customer' => (new CustomerResource($this->queries->detail($model)))->resolve($request),
        ]]);
    }

    public function update(UpdateCustomerRequest $request, int $customer): JsonResponse
    {
        $model = $this->customers->update(
            $this->ownedCustomer($request, $customer), $request->user(), $request->validated(),
        );

        return response()->json([
            'success' => true, 'message' => 'Data pelanggan berhasil diperbarui.',
            'data' => ['customer' => (new CustomerResource($this->queries->detail($model)))->resolve($request)],
        ]);
    }

    public function destroy(Request $request, int $customer): JsonResponse
    {
        $this->customers->delete($this->ownedCustomer($request, $customer));

        return response()->json([
            'success' => true, 'message' => 'Pelanggan berhasil dihapus.',
            'data' => ['deleted' => true],
        ]);
    }

    public function invoices(ListCustomerInvoicesRequest $request, int $customer): JsonResponse
    {
        $model = $this->ownedCustomer($request, $customer);
        $status = $request->validated('status');
        $query = Invoice::query()->where('user_id', $request->user()->id)->where('customer_id', $model->id);
        if ($status === 'overdue') {
            $query->where('status', Invoice::STATUS_UNPAID)->whereDate('due_date', '<', today());
        } elseif ($status) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', Invoice::STATUS_VOID);
        }
        $paginator = $query->orderByDesc('issue_date')->orderByDesc('id')->paginate(
            (int) $request->validated('perPage'), page: (int) $request->validated('page'),
        )->withQueryString();

        return response()->json(['success' => true, 'data' => [
            'customer' => ['id' => $model->id, 'customerCode' => $model->customer_code, 'name' => $model->name],
            'invoices' => InvoiceSummaryResource::collection($paginator->items())->resolve($request),
            'pagination' => $this->pagination($paginator),
        ]]);
    }

    private function ownedCustomer(Request $request, int $customer): Customer
    {
        return Customer::query()->where('user_id', $request->user()->id)->findOrFail($customer);
    }

    /** @return array<string, int|string|null> */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(), 'perPage' => $paginator->perPage(),
            'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
            'previousPageUrl' => $paginator->previousPageUrl(), 'nextPageUrl' => $paginator->nextPageUrl(),
        ];
    }
}
