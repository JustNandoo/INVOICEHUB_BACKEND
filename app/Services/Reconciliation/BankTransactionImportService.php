<?php

namespace App\Services\Reconciliation;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankTransactionImport;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class BankTransactionImportService
{
    public function __construct(
        private readonly ReconciliationService $reconciliations,
    ) {}

    public function import(User $user, BankAccount $account, UploadedFile $file): BankTransactionImport
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('reconciliation/imports', Str::uuid().'.'.$extension, 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages(['file' => ['File mutasi gagal disimpan.']]);
        }
        $import = BankTransactionImport::query()->create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'processing',
        ]);

        try {
            $rows = IOFactory::load(Storage::disk('local')->path($path))->getActiveSheet()->toArray(null, true, true, false);
            $result = $this->processRows($user, $account, $import, $rows);
            $import->update([
                ...$result,
                'status' => $result['failed_rows'] > 0 && $result['imported_rows'] === 0 ? 'failed' : 'completed',
                'completed_at' => now(),
            ]);
            $account->update(['last_synced_at' => now(), 'last_sync_error' => null]);
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'errors' => [['row' => null, 'message' => $exception->getMessage()]],
                'completed_at' => now(),
            ]);
            throw ValidationException::withMessages(['file' => ['File mutasi tidak dapat diproses: '.$exception->getMessage()]]);
        }

        return $import->refresh();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{total_rows: int, imported_rows: int, duplicate_rows: int, failed_rows: int, errors: array<int, array<string, mixed>>}
     */
    private function processRows(User $user, BankAccount $account, BankTransactionImport $import, array $rows): array
    {
        if (count($rows) < 2) {
            throw new \RuntimeException('File harus memiliki header dan minimal satu baris data.');
        }
        $headers = array_map(fn ($value): string => $this->normalizeHeader((string) $value), array_shift($rows));
        $indexes = $this->headerIndexes($headers);
        if (! isset($indexes['date'], $indexes['amount'])) {
            throw new \RuntimeException('Kolom wajib tanggal/date dan nominal/amount tidak ditemukan.');
        }

        $imported = 0;
        $duplicates = 0;
        $failed = 0;
        $errors = [];
        foreach ($rows as $offset => $row) {
            $rowNumber = $offset + 2;
            if (collect($row)->filter(fn ($value): bool => $value !== null && trim((string) $value) !== '')->isEmpty()) {
                continue;
            }
            try {
                $transaction = $this->normalizeRow($row, $indexes);
                $fingerprint = hash('sha256', implode('|', [
                    $account->id, $transaction['external_transaction_id'] ?? '', $transaction['transaction_at'],
                    $transaction['amount'], $transaction['sender_name'] ?? '', $transaction['description'] ?? '', $transaction['reference'] ?? '',
                ]));
                if (BankTransaction::query()->where('bank_account_id', $account->id)->where('fingerprint', $fingerprint)->exists()) {
                    $duplicates++;

                    continue;
                }
                $bankTransaction = BankTransaction::query()->create([
                    ...$transaction,
                    'user_id' => $user->id,
                    'bank_account_id' => $account->id,
                    'bank_transaction_import_id' => $import->id,
                    'fingerprint' => $fingerprint,
                    'status' => BankTransaction::STATUS_UNMATCHED,
                    'raw_payload' => $row,
                ]);
                $this->autoReconcileExact($user, $bankTransaction);
                $imported++;
            } catch (Throwable $exception) {
                $failed++;
                if (count($errors) < 50) {
                    $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
                }
            }
        }

        return [
            'total_rows' => $imported + $duplicates + $failed,
            'imported_rows' => $imported,
            'duplicate_rows' => $duplicates,
            'failed_rows' => $failed,
            'errors' => $errors,
        ];
    }

    /** @param array<int, mixed> $row @param array<string, int> $indexes @return array<string, mixed> */
    private function normalizeRow(array $row, array $indexes): array
    {
        $dateValue = $row[$indexes['date']] ?? null;
        $amountValue = $row[$indexes['amount']] ?? null;
        if ($dateValue === null || $amountValue === null) {
            throw new \RuntimeException('Tanggal dan nominal wajib diisi.');
        }
        $date = is_numeric($dateValue)
            ? CarbonImmutable::instance(Date::excelToDateTimeObject((float) $dateValue))
            : CarbonImmutable::parse((string) $dateValue);
        $amount = $this->parseAmount($amountValue);
        if ($amount === 0) {
            throw new \RuntimeException('Nominal harus lebih besar dari nol.');
        }
        $typeValue = strtolower((string) ($row[$indexes['type'] ?? -1] ?? ''));
        $type = str_contains($typeValue, 'debit') || str_contains($typeValue, 'keluar') || str_starts_with(trim((string) $amountValue), '-')
            ? 'debit' : 'credit';

        return [
            'external_transaction_id' => $this->optional($row, $indexes, 'external_id'),
            'type' => $type,
            'amount' => $amount,
            'sender_name' => $this->optional($row, $indexes, 'sender'),
            'description' => $this->optional($row, $indexes, 'description'),
            'reference' => $this->optional($row, $indexes, 'reference'),
            'transaction_at' => $date,
        ];
    }

    /** @param array<int, string> $headers @return array<string, int> */
    private function headerIndexes(array $headers): array
    {
        $aliases = [
            'date' => ['tanggal', 'date', 'transaction date', 'tanggal transaksi'],
            'amount' => ['nominal', 'amount', 'jumlah'],
            'sender' => ['pengirim', 'sender', 'nama pengirim'],
            'description' => ['keterangan', 'description', 'deskripsi'],
            'reference' => ['referensi', 'reference', 'ref'],
            'external_id' => ['id transaksi', 'transaction id', 'external id'],
            'type' => ['tipe', 'type', 'jenis'],
        ];
        $indexes = [];
        foreach ($aliases as $key => $names) {
            foreach ($names as $name) {
                $index = array_search($name, $headers, true);
                if ($index !== false) {
                    $indexes[$key] = $index;
                    break;
                }
            }
        }

        return $indexes;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $header) ?? ''));
    }

    private function parseAmount(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            return (int) abs(round($value));
        }
        $value = preg_replace('/[.,]00$/', '', trim((string) $value)) ?? '';

        return (int) preg_replace('/\D/', '', $value);
    }

    /** @param array<int, mixed> $row @param array<string, int> $indexes */
    private function optional(array $row, array $indexes, string $key): ?string
    {
        if (! isset($indexes[$key])) {
            return null;
        }
        $value = trim((string) ($row[$indexes[$key]] ?? ''));

        return $value === '' ? null : $value;
    }

    private function autoReconcileExact(User $user, BankTransaction $transaction): void
    {
        if ($transaction->type !== 'credit') {
            return;
        }
        $text = ($transaction->description ?? '').' '.($transaction->reference ?? '');
        if (preg_match('/INV-\d{4}-\d+/i', $text, $matches) !== 1) {
            return;
        }
        $invoice = Invoice::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(number) = ?', [strtolower($matches[0])])
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('balance_due', $transaction->amount)
            ->first();
        if ($invoice !== null) {
            $this->reconciliations->confirmExactRule($user, $transaction, $invoice);
        }
    }
}
