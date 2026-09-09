<?php

namespace App\Contracts\Notifications;

interface SmsOtpSenderInterface
{
    public function send(string $phone, string $message): void;
}
