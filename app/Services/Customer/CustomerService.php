<?php

namespace App\Services\Customer;

use App\Events\Customer\CustomerCreated;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly CustomerNumberService $numbers,
        private readonly WhatsappNormalizer $whatsapp,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Customer
    {
        return DB::transaction(function () use ($user, $data): Customer {
            $phone = $this->whatsapp->normalize($data['whatsapp']);
            $customerCode = $this->numbers->next($user);
            $this->guardUniqueWhatsapp($user, $phone['normalized']);

            $customer = Customer::query()->create([
                'user_id' => $user->id,
                'customer_code' => $customerCode,
                'name' => trim($data['name']),
                'email' => $this->nullableTrim($data['email'] ?? null),
                'whatsapp' => $phone['display'],
                'whatsapp_normalized' => $phone['normalized'],
                'city' => $this->nullableTrim($data['city'] ?? null),
                'address' => $this->nullableTrim($data['address'] ?? null),
                'source' => $data['source'] ?? 'manual',
                'is_active' => $data['isActive'] ?? true,
            ]);
            CustomerCreated::dispatch($customer->id);

            return $customer;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Customer $customer, User $user, array $data): Customer
    {
        $attributes = [];
        if (array_key_exists('name', $data)) {
            $attributes['name'] = trim($data['name']);
        }
        if (array_key_exists('whatsapp', $data)) {
            $phone = $this->whatsapp->normalize($data['whatsapp']);
            $this->guardUniqueWhatsapp($user, $phone['normalized'], $customer->id);
            $attributes['whatsapp'] = $phone['display'];
            $attributes['whatsapp_normalized'] = $phone['normalized'];
        }
        foreach (['email', 'city', 'address'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $this->nullableTrim($data[$field]);
            }
        }
        if (array_key_exists('source', $data)) {
            $attributes['source'] = $data['source'];
        }
        if (array_key_exists('isActive', $data)) {
            $attributes['is_active'] = $data['isActive'];
        }

        $customer->update($attributes);

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $outstanding = Invoice::query()->where('customer_id', $customer->id)
            ->where('status', Invoice::STATUS_UNPAID)->where('balance_due', '>', 0)->sum('balance_due');
        if ($outstanding > 0) {
            throw ValidationException::withMessages([
                'customer' => ['Customer cannot be deleted while outstanding invoices remain. Deactivate the customer instead.'],
            ]);
        }

        $customer->update(['is_active' => false]);
        $customer->delete();
    }

    private function guardUniqueWhatsapp(User $user, string $normalized, ?int $exceptId = null): void
    {
        $exists = Customer::query()->withTrashed()->where('user_id', $user->id)
            ->where('whatsapp_normalized', $normalized)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['whatsapp' => ['This WhatsApp number is already used by another customer.']]);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
