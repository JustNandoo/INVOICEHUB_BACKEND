Invoice {{ $invoice->number }}

Halo {{ $invoice->customer_name }},

{{ $customMessage ?: 'Invoice terbaru Anda sudah diterbitkan. Detail lengkap tersedia pada lampiran PDF di email ini.' }}

Total: Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}
Jatuh tempo: {{ $invoice->due_date->translatedFormat('d M Y') }}

Email otomatis dari {{ $invoice->issuer_name }} melalui InvoiceHub.
