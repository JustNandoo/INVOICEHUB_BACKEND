<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

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
            'resetUrl' => $this->resetUrl($notifiable),
            'expiresInMinutes' => (int) config('auth.passwords.users.expire', 60),
        ];

        return (new MailMessage)
            ->subject('Atur Ulang Password InvoiceHub')
            ->view('emails.auth.reset-password', $viewData)
            ->text('emails.auth.reset-password-text', $viewData);
    }

    /**
     * Tautan mengarah ke halaman frontend, bukan ke API. Halaman itulah yang
     * memanggil endpoint reset setelah pengguna mengisi password barunya.
     */
    private function resetUrl(User $user): string
    {
        return rtrim((string) config('authentication.password_reset.url'), '/')
            .'?'.http_build_query([
                'token' => $this->token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
    }
}
