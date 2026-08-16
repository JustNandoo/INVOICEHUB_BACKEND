<?php

namespace App\Services\Invoice;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvoiceDeliveryService
{
    public function __construct(
        private readonly InvoicePdfService $pdf,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array{channel: string, recipient?: string|null, message?: string|null}  $data
     * @return array{invoice: Invoice, channel: string, recipient: string, actionUrl: string|null}
     */
    public function send(Invoice $invoice, User $user, array $data): array
    {
        $channel = $data['channel'];
        $recipient = trim((string) ($data['recipient'] ?? $this->defaultRecipient($invoice, $channel)));

        if ($recipient === '') {
            throw ValidationException::withMessages([
                'recipient' => ["Tujuan {$channel} pelanggan belum tersedia."],
            ]);
        }

        $actionUrl = null;
        if ($channel === 'email') {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw ValidationException::withMessages(['recipient' => ['Alamat email tidak valid.']]);
            }

            Mail::to($recipient)->send(new InvoiceMail(
                $invoice,
                $this->pdf->render($invoice),
                $data['message'] ?? null,
            ));
        } else {
            $phone = $this->normalizeWhatsAppNumber($recipient);
            $message = $data['message'] ?? sprintf(
                'Halo %s, invoice %s sebesar Rp %s dari %s telah diterbitkan.',
                $invoice->customer_name,
                $invoice->number,
                number_format($invoice->total_amount, 0, ',', '.'),
                $invoice->issuer_name,
            );
            $recipient = $phone;
            $actionUrl = 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
        }

        return [
            'invoice' => $this->invoices->markSent($invoice, $user, $channel, $recipient),
            'channel' => $channel,
            'recipient' => $recipient,
            'actionUrl' => $actionUrl,
        ];
    }

    private function defaultRecipient(Invoice $invoice, string $channel): ?string
    {
        return $channel === 'email' ? $invoice->customer_email : $invoice->customer_whatsapp;
    }

    private function normalizeWhatsAppNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 15) {
            throw ValidationException::withMessages([
                'recipient' => ['Nomor WhatsApp tidak valid. Gunakan nomor Indonesia, misalnya +62 812 3456 7890.'],
            ]);
        }

        return $digits;
    }
}
