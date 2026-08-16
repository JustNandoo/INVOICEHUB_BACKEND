<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
        return new Envelope(subject: "Invoice {$this->invoice->number} dari {$this->invoice->issuer_name}");
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
