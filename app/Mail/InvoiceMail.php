<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        private readonly string $pdfContent,
        public readonly ?string $customMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $terlambat = $this->invoice->due_date?->isPast()
            && $this->invoice->status !== Invoice::STATUS_PAID
            && $this->invoice->balance_due > 0;

        return new Envelope(
            subject: $terlambat
                ? "Pengingat: Invoice {$this->invoice->number} dari {$this->invoice->issuer_name} telah jatuh tempo"
                : "Invoice {$this->invoice->number} dari {$this->invoice->issuer_name}",
            /*
             * Balasan diarahkan ke penerbit invoice, bukan ke kotak surat InvoiceHub.
             *
             * Surat ini dikirim dari alamat sistem, jadi tanpa Reply-To setiap balasan
             * pelanggan — pertanyaan tagihan, konfirmasi transfer, keberatan — akan masuk
             * ke kotak surat platform dan tidak pernah sampai ke pemilik usaha yang
             * menagih. Untuk surat penagihan, itu kehilangan yang mahal.
             */
            replyTo: filter_var($this->invoice->issuer_email, FILTER_VALIDATE_EMAIL)
                ? [new Address($this->invoice->issuer_email, $this->invoice->issuer_name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice.sent', text: 'emails.invoice.sent-text');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->pdfContent,
                "{$this->invoice->number}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
