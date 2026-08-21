<?php

namespace App\Services\Ai\Features;

use App\Enums\Ai\AiFeature;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiProviderException;
use App\Exceptions\Ai\AiValidationException;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Prompts\PaymentReminderPrompt;
use App\Services\Ai\Sanitizer;
use App\Services\Ai\Schemas\PaymentReminderSchema;
use App\Services\Ai\Support\AiPrompt;
use App\Services\Subscription\EntitlementService;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentReminderService
{
    public function __construct(
        private readonly AiOrchestrator $orchestrator,
        private readonly EntitlementService $entitlements,
        private readonly Sanitizer $sanitizer,
    ) {}

    /**
     * Produce reminder drafts. Nothing is ever sent from here: delivery stays with the
     * existing POST /invoices/{invoice}/send endpoint, driven by the user.
     *
     * @param  array{channel?: string|null, tone?: string|null}  $options
     * @return array<string, mixed>
     */
    public function draft(User $user, Invoice $invoice, array $options = []): array
    {
        $this->guardPayable($invoice);

        $channel = $options['channel'] ?? 'whatsapp';
        $requestedTone = $options['tone'] ?? null;
        $canChooseTone = $this->entitlements->has($user, 'ai.reminder_tones');

        if ($requestedTone !== null && ! $canChooseTone) {
            $this->entitlements->require($user, 'ai.reminder_tones');
        }

        $tones = match (true) {
            $requestedTone !== null => [$requestedTone],
            $canChooseTone => PaymentReminderSchema::TONES,
            default => [$this->autoTone($invoice)],
        };

        $payload = $this->context($invoice, $channel, $tones);

        try {
            $run = $this->orchestrator->runStructured(
                $user,
                AiFeature::PaymentReminderDraft,
                new AiPrompt(
                    systemInstruction: PaymentReminderPrompt::system(),
                    userContent: PaymentReminderPrompt::user($payload),
                    schema: PaymentReminderSchema::definition(),
                    hashPayload: $payload,
                ),
                fn (array $output): array => $this->validate($output, $invoice, $channel, $tones),
            );
        } catch (AiDisabledException|AiProviderException|AiValidationException $exception) {
            return $this->fallback($invoice, $channel, $tones, $this->reasonFor($exception));
        }

        /** @var array<string, mixed> $result */
        $result = $run->structured_output ?? [];

        return [...$result, 'aiRunId' => $run->id, 'aiAvailable' => true, 'source' => 'ai'];
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  list<string>  $tones
     * @return array<string, mixed>
     */
    private function validate(array $output, Invoice $invoice, string $channel, array $tones): array
    {
        $violations = [];
        $drafts = $output['drafts'] ?? null;

        if (! is_array($drafts) || $drafts === []) {
            throw new AiValidationException(['drafts wajib berisi minimal satu draft.']);
        }

        $clean = [];
        $seenTones = [];

        foreach (array_slice($drafts, 0, PaymentReminderSchema::MAX_DRAFTS) as $index => $draft) {
            if (! is_array($draft)) {
                $violations[] = "drafts[{$index}] bukan objek.";

                continue;
            }

            $tone = $draft['tone'] ?? null;
            if (! is_string($tone) || ! in_array($tone, PaymentReminderSchema::TONES, true)) {
                $violations[] = "drafts[{$index}].tone di luar nada yang diizinkan.";

                continue;
            }

            if (! in_array($tone, $tones, true)) {
                $violations[] = "drafts[{$index}].tone tidak diminta.";

                continue;
            }

            if (in_array($tone, $seenTones, true)) {
                continue;
            }

            $raw = $draft['message'] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                $violations[] = "drafts[{$index}].message kosong.";

                continue;
            }

            // Checked before sanitising: an over-long reminder is rejected outright rather
            // than silently truncated, which would cut the message mid-sentence.
            if (mb_strlen(trim($raw)) > PaymentReminderSchema::MAX_MESSAGE_LENGTH) {
                $violations[] = "drafts[{$index}].message melebihi ".PaymentReminderSchema::MAX_MESSAGE_LENGTH.' karakter.';

                continue;
            }

            $message = $this->sanitizer->text($raw, PaymentReminderSchema::MAX_MESSAGE_LENGTH);
            if ($message === null) {
                $violations[] = "drafts[{$index}].message kosong setelah dibersihkan.";

                continue;
            }

            if (! str_contains($message, $invoice->number)) {
                $violations[] = "drafts[{$index}].message tidak menyebut nomor invoice.";

                continue;
            }

            if (preg_match('/\{\{|\}\}|\[(?:nama|isi|masukkan)/iu', $message) === 1) {
                $violations[] = "drafts[{$index}].message masih mengandung placeholder.";

                continue;
            }

            $subject = $channel === 'email'
                ? $this->sanitizer->text($draft['subject'] ?? null, PaymentReminderSchema::MAX_SUBJECT_LENGTH)
                : null;

            $seenTones[] = $tone;
            $clean[] = ['tone' => $tone, 'subject' => $subject, 'message' => $message];
        }

        if ($clean === []) {
            throw new AiValidationException($violations === [] ? ['Tidak ada draft yang lolos validasi.'] : $violations);
        }

        return ['drafts' => $clean, 'channel' => $channel];
    }

    /**
     * Laravel can always write a correct, if plain, reminder. The feature therefore keeps
     * working when the provider is down or the quota is spent.
     *
     * @param  list<string>  $tones
     * @return array<string, mixed>
     */
    private function fallback(Invoice $invoice, string $channel, array $tones, string $reason): array
    {
        $tone = $tones[0] ?? $this->autoTone($invoice);
        $amount = $this->rupiah($invoice->balance_due);
        $due = $invoice->due_date->translatedFormat('d F Y');
        $opening = match ($tone) {
            'firm' => 'Kami ingatkan kembali bahwa tagihan berikut sudah melewati jatuh tempo.',
            'neutral' => 'Kami informasikan tagihan berikut masih tercatat belum lunas.',
            default => 'Semoga sehat selalu. Kami ingin mengingatkan tagihan berikut dengan hormat.',
        };

        $message = sprintf(
            "Halo %s,\n\n%s\n\nInvoice %s sebesar %s jatuh tempo pada %s.\n\nMohon konfirmasinya bila pembayaran sudah dilakukan. Terima kasih.\n\n%s",
            $invoice->customer_name, $opening, $invoice->number, $amount, $due, $invoice->issuer_name,
        );

        return [
            'drafts' => [[
                'tone' => $tone,
                'subject' => $channel === 'email' ? "Pengingat pembayaran invoice {$invoice->number}" : null,
                'message' => mb_substr($message, 0, PaymentReminderSchema::MAX_MESSAGE_LENGTH),
            ]],
            'channel' => $channel,
            'aiRunId' => null,
            'aiAvailable' => false,
            'source' => 'template',
            'unavailableReason' => $reason,
        ];
    }

    private function guardPayable(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_UNPAID || $invoice->balance_due <= 0) {
            throw ValidationException::withMessages([
                'invoice' => ['Hanya invoice dengan sisa tagihan yang dapat dibuatkan pengingat.'],
            ]);
        }
    }

    private function autoTone(Invoice $invoice): string
    {
        $daysOverdue = $this->daysOverdue($invoice);

        return match (true) {
            $daysOverdue > 30 => 'firm',
            $daysOverdue > 7 => 'neutral',
            default => 'polite',
        };
    }

    private function daysOverdue(Invoice $invoice): int
    {
        return max(0, (int) today()->diffInDays($invoice->due_date, absolute: false) * -1);
    }

    /**
     * Only what the model needs to write the message. Amounts are pre-formatted by Laravel
     * so the model copies a string instead of doing arithmetic.
     *
     * @param  list<string>  $tones
     * @return array<string, mixed>
     */
    private function context(Invoice $invoice, string $channel, array $tones): array
    {
        $reminderCount = $invoice->activities()->whereIn('type', ['sent', 'resent'])->count();

        return [
            'invoiceNumber' => $invoice->number,
            'customerName' => $this->sanitizer->name($invoice->customer_name),
            'businessName' => $this->sanitizer->name($invoice->issuer_name),
            'totalAmount' => (int) $invoice->total_amount,
            'totalAmountFormatted' => $this->rupiah($invoice->total_amount),
            'balanceDue' => (int) $invoice->balance_due,
            'balanceDueFormatted' => $this->rupiah($invoice->balance_due),
            'paidAmountFormatted' => $this->rupiah($invoice->paid_amount),
            'isPartiallyPaid' => $invoice->paid_amount > 0,
            'issueDate' => $invoice->issue_date?->format('Y-m-d'),
            'dueDate' => $invoice->due_date?->format('Y-m-d'),
            'dueDateFormatted' => $invoice->due_date?->translatedFormat('d F Y'),
            'daysOverdue' => $this->daysOverdue($invoice),
            'timesAlreadySent' => $reminderCount,
            'channel' => $channel,
            'requestedTones' => $tones,
        ];
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function reasonFor(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AiDisabledException => $exception->reason,
            $exception instanceof AiProviderException => $exception->errorCode,
            default => 'invalid_output',
        };
    }
}
