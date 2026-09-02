<?php

namespace App\Console\Commands;

use App\Contracts\MailtrapSandboxSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('send-mail {--to= : Destinatário técnico usado pelo Mailtrap Sandbox}')]
#[Description('Envia uma mensagem de verificação para o Mailtrap Sandbox local')]
class SendMailtrapTestEmail extends Command
{
    public function __construct(private MailtrapSandboxSender $sender)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando só pode ser executado em local ou testing.');

            return self::FAILURE;
        }

        $recipient = $this->option('to') ?: config('services.mailtrap.test_recipient');
        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Informe --to ou configure MAILTRAP_TEST_RECIPIENT com um e-mail válido.');
        }

        try {
            $result = $this->sender->sendTestMessage($recipient);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('O Mailtrap não aceitou a mensagem. Consulte o log local para detalhes técnicos.');

            return self::FAILURE;
        }

        $messageId = $result['message_ids'][0] ?? $result['message_id'] ?? null;
        $suffix = is_string($messageId) ? " ID: {$messageId}." : '';
        $this->info("Mensagem aceita pelo Mailtrap Sandbox.{$suffix}");

        return self::SUCCESS;
    }
}
