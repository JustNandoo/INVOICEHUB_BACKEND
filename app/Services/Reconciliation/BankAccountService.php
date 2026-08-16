<?php

namespace App\Services\Reconciliation;

use App\Models\BankAccount;

class BankAccountService
{
    /** @return array{account: BankAccount, importedTransactions: int} */
    public function sync(BankAccount $account): array
    {
        $account->update([
            'last_synced_at' => now(),
            'last_sync_error' => null,
            'connection_status' => 'connected',
        ]);

        return ['account' => $account->refresh(), 'importedTransactions' => 0];
    }
}
