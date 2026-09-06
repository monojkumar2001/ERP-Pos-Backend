<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification
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
            ->subject('Register OTP Code - Softifybd')
            ->view('emails.otp-code', [
                'title' => 'Register OTP Code',
                'subtitle' => 'Register OTP Code',
                'name' => $name,
                'intro' => 'Your One-Time Password (OTP) for email verification is:',
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
