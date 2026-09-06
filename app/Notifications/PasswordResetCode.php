<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCode extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected int $expiresInMinutes = 5
    ) {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $name = $notifiable->name ?? 'there';

        return (new MailMessage)
            ->subject('Password Reset OTP - Softifybd')
            ->view('emails.otp-code', [
                'title' => 'Password Reset OTP',
                'subtitle' => 'Password Reset OTP',
                'name' => $name,
                'intro' => 'Your One-Time Password (OTP) for password reset is:',
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
