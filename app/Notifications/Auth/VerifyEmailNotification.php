<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $viewData = [
            'name' => $notifiable->name,
            'verificationUrl' => $this->verificationUrl($notifiable),
            'expiresInMinutes' => (int) config('authentication.email_verification.expires_in_minutes'),
        ];

        return (new MailMessage)
            ->subject('Verifikasi Email InvoiceHub')
            ->view('emails.auth.verify-email', $viewData)
            ->text('emails.auth.verify-email-text', $viewData);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('authentication.email_verification.expires_in_minutes')),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }
}
