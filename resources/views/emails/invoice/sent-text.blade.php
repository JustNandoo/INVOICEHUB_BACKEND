@php
    $terlambat = $invoice->due_date?->isPast()
        && $invoice->status !== \App\Models\Invoice::STATUS_PAID
        && $invoice->balance_due > 0;
    $sisa = $invoice->balance_due > 0 ? $invoice->balance_due : $invoice->total_amount;
    $kontak = $invoice->issuer_email ? ', atau hubungi kami di '.$invoice->issuer_email : '';
@endphp
{{ $invoice->issuer_name }}
{{ $terlambat ? 'TAGIHAN JATUH TEMPO' : 'TAGIHAN BARU' }} - Invoice {{ $invoice->number }}

Halo {{ $invoice->customer_name }},

{{ $customMessage ?: ($terlambat
    ? 'Kami ingin mengingatkan bahwa tagihan berikut sudah melewati tanggal jatuh tempo. Bila pembayaran sudah dilakukan, mohon abaikan pesan ini.'
    : 'Berikut kami kirimkan tagihan atas transaksi Anda. Rincian lengkapnya ada pada lampiran PDF.') }}

Nomor invoice : {{ $invoice->number }}
Tanggal terbit: {{ $invoice->issue_date?->translatedFormat('d F Y') ?? '-' }}
Jatuh tempo   : {{ $invoice->due_date?->translatedFormat('d F Y') ?? '-' }}
{{ $invoice->balance_due > 0 && $invoice->balance_due < $invoice->total_amount ? 'Sisa tagihan' : 'Total tagihan' }} : Rp {{ number_format($sisa, 0, ',', '.') }}
@if ($invoice->notes)

Catatan: {{ $invoice->notes }}
@endif

Rincian lengkap terlampir sebagai berkas PDF. Ada pertanyaan mengenai tagihan ini?
Balas saja email ini{{ $kontak }}.

Terima kasih,
{{ $invoice->issuer_name }}

--
Dikirim oleh {{ $invoice->issuer_name }} menggunakan InvoiceHub.
