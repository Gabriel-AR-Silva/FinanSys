<?php

namespace Tests\Feature;

use App\Contracts\MailtrapSandboxSender;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class SendMailtrapTestEmailTest extends TestCase
{
    public function test_command_sends_to_configured_recipient_without_network_access(): void
    {
        config()->set('services.mailtrap.test_recipient', 'developer@example.com');
        $this->mock(MailtrapSandboxSender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendTestMessage')->once()->with('developer@example.com')->andReturn([
                'success' => true,
                'message_ids' => ['sandbox-message-id'],
            ]);
        });

        $this->artisan('send-mail')
            ->expectsOutput('Mensagem aceita pelo Mailtrap Sandbox. ID: sandbox-message-id.')
            ->assertSuccessful();
    }

    public function test_command_accepts_an_explicit_recipient(): void
    {
        $this->mock(MailtrapSandboxSender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendTestMessage')->once()->with('other@example.com')->andReturn(['success' => true]);
        });

        $this->artisan('send-mail', ['--to' => 'other@example.com'])
            ->expectsOutput('Mensagem aceita pelo Mailtrap Sandbox.')
            ->assertSuccessful();
    }

    public function test_command_reports_external_failure_without_exposing_credentials(): void
    {
        config()->set('services.mailtrap.test_recipient', 'developer@example.com');
        $this->mock(MailtrapSandboxSender::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendTestMessage')->once()->andThrow(new RuntimeException('secret response'));
        });

        $this->artisan('send-mail')
            ->expectsOutput('O Mailtrap não aceitou a mensagem. Consulte o log local para detalhes técnicos.')
            ->assertFailed();
    }
}
