<?php

namespace App\Contracts;

interface MailtrapSandboxSender
{
    /** @return array<string, mixed> */
    public function sendTestMessage(string $recipient): array;
}
