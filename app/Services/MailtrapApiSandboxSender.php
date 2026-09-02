<?php

namespace App\Services;

use App\Contracts\MailtrapSandboxSender;
use GuzzleHttp\Client;
use Mailtrap\Config;
use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use RuntimeException;
use Symfony\Component\Mime\Address;

class MailtrapApiSandboxSender implements MailtrapSandboxSender
{
    public function sendTestMessage(string $recipient): array
    {
        $apiKey = config('services.mailtrap.api_key');
        $inboxId = config('services.mailtrap.inbox_id');
        if (! is_string($apiKey) || $apiKey === '' || ! is_numeric($inboxId)) {
            throw new RuntimeException('Configure MAILTRAP_API_KEY e MAILTRAP_INBOX_ID no .env local.');
        }
        if (config('services.mailtrap.use_sandbox') !== true) {
            throw new RuntimeException('O comando local exige MAILTRAP_USE_SANDBOX=true.');
        }

        $email = (new MailtrapEmail)
            ->from(new Address((string) config('mail.from.address'), (string) config('mail.from.name')))
            ->to(new Address($recipient))
            ->subject('FinanSys: integração local validada')
            ->category('Integration Test')
            ->text('O envio local do FinanSys para o Mailtrap Sandbox foi concluído com sucesso.');

        $config = new Config($apiKey);
        $caBundle = config('services.mailtrap.ca_bundle');
        if (is_string($caBundle) && $caBundle !== '') {
            $path = str_starts_with($caBundle, storage_path()) ? $caBundle : base_path($caBundle);
            if (! is_file($path)) {
                throw new RuntimeException('O bundle CA configurado para o Mailtrap não foi encontrado.');
            }
            $config->setHttpClient(new Client(['verify' => $path]));
        }

        $client = new MailtrapClient($config);
        $response = $client->sandbox()->emails((int) $inboxId)->send($email);

        return ResponseHelper::toArray($response);
    }
}
