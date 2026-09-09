<?php

namespace App\Contracts\Notifications;

interface EmailOtpSenderInterface
{
    public function send(string $email, string $subject, string $message): void;
}
