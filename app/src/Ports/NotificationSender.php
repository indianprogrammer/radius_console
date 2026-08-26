<?php

namespace App\Src\Ports;

/**
 * Port for outbound notifications. Concrete adapters target the external
 * Call/SMS server, WhatsApp server, and Email server (SRD §2.3 #2/#3).
 */
interface NotificationSender
{
    public function sendSms(string $to, string $message): array;

    public function sendWhatsApp(string $to, string $template, array $vars): array;

    public function sendEmail(string $to, string $subject, string $body): array;
}
