<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\EmailCampaign\EmailCampaign;
use App\Models\EmailCampaign\EmailCampaignRecipient;
use App\Models\EmailCampaign\EmailSuppression;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Concerns\CreatesEmailCampaignSchema;
use Tests\TestCase;

/**
 * O motor do Disparo de E-mails (`email-campaigns:send` → CampaignSender):
 * personaliza e envia, respeita o teto por rodada, pula o "Não enviar para",
 * obedece pausa e agenda, e separa os três tipos de falha (recusa definitiva,
 * temporária e servidor fora do ar).
 *
 * Usa um transporte SMTP de mentira registrado como mailer: assim o e-mail
 * passa pelo caminho real do Symfony (cabeçalhos, imagem embutida) sem rede.
 */
class EmailCampaignSenderTest extends TestCase
{
    use CreatesEmailCampaignSchema;

    /** @var list<\Symfony\Component\Mime\Email> */
    private array $delivered = [];

    /** @var array<string, string> a mensagem como saiu para o "servidor" */
    private array $raw = [];

    /** Decide, por destinatário, se o "servidor" aceita ou lança. */
    private ?Closure $server = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEmailCampaignSchema();

        Mail::extend('campanha-teste', function () {
            return new class($this) extends AbstractTransport {
                public function __construct(private $test)
                {
                    parent::__construct();
                }

                protected function doSend(SentMessage $message): void
                {
                    $this->test->receive($message);
                }

                public function __toString(): string
                {
                    return 'campanha-teste://';
                }
            };
        });

        config([
            'mail.mailers.campanha-teste' => ['transport' => 'campanha-teste'],
            'email_campaigns.mailer' => 'campanha-teste',
            'email_campaigns.per_minute' => 100,
        ]);
    }

    public function receive(SentMessage $message): void
    {
        $to = $message->getEnvelope()->getRecipients()[0]->getAddress();

        if ($this->server) {
            ($this->server)($to);
        }

        $this->delivered[$to] = $message->getOriginalMessage();
        $this->raw[$to] = $message->toString();
    }

    private function campaign(array $recipients, array $attributes = []): EmailCampaign
    {
        $campaign = EmailCampaign::create($attributes + [
            'name' => 'Festa',
            'subject' => '{{primeiro_nome}}, venha!',
            'from_address' => 'campanhas@clube.test',
            'from_name' => 'Clube',
            'reply_to' => 'secretaria@clube.test',
            'html' => '<p>Olá {{nome}}, sua mesa é a {{mesa}}.</p><a href="https://clube.test/festa">Ver a festa</a>',
            'status' => EmailCampaign::STATUS_SENDING,
            'started_at' => now(),
        ]);

        foreach ($recipients as $email => $data) {
            $campaign->recipients()->create(['email' => $email, 'name' => $data['nome'] ?? null, 'data' => array_diff_key($data, ['nome' => 1])]);
        }

        return $campaign;
    }

    private function runOnce(?int $limit = null): void
    {
        Artisan::call('email-campaigns:send', array_filter(['--limit' => $limit]));
    }

    public function test_envia_personalizado_e_conclui(): void
    {
        $campaign = $this->campaign([
            'ana@x.com.br' => ['nome' => 'Ana Souza', 'mesa' => '12'],
            'beto@x.com.br' => ['nome' => '<Beto>', 'mesa' => ''],
        ]);

        $this->runOnce();

        $this->assertCount(2, $this->delivered);

        $ana = $this->delivered['ana@x.com.br'];
        $this->assertSame('Ana, venha!', $ana->getSubject());
        $this->assertStringContainsString('Olá Ana Souza, sua mesa é a 12.', $ana->getHtmlBody());
        $this->assertStringContainsString('Ver a festa (https://clube.test/festa)', $ana->getTextBody());
        $this->assertSame('campanhas@clube.test', $ana->getFrom()[0]->getAddress());
        $this->assertSame('Clube', $ana->getFrom()[0]->getName());
        $this->assertSame('secretaria@clube.test', $ana->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('mailto:secretaria@clube.test', $ana->getHeaders()->get('List-Unsubscribe')->getBodyAsString());

        // Valor da lista é escapado no HTML.
        $this->assertStringContainsString('Olá &lt;Beto&gt;', $this->delivered['beto@x.com.br']->getHtmlBody());

        $campaign->refresh();
        $this->assertSame(EmailCampaign::STATUS_DONE, $campaign->status);
        $this->assertNotNull($campaign->finished_at);
        $this->assertSame(2, $campaign->counts()['sent']);
    }

    public function test_respeita_o_limite_por_rodada(): void
    {
        $campaign = $this->campaign(['a@x.com.br' => [], 'b@x.com.br' => [], 'c@x.com.br' => []]);

        $this->runOnce(limit: 2);
        $this->assertCount(2, $this->delivered);
        $this->assertSame(EmailCampaign::STATUS_SENDING, $campaign->fresh()->status);

        $this->runOnce(limit: 2);
        $this->assertCount(3, $this->delivered);
        $this->assertSame(EmailCampaign::STATUS_DONE, $campaign->fresh()->status);
    }

    public function test_pula_quem_esta_em_nao_enviar_para(): void
    {
        $campaign = $this->campaign(['a@x.com.br' => [], 'saiu@x.com.br' => []]);
        EmailSuppression::create(['email' => 'saiu@x.com.br']);

        $this->runOnce();

        $this->assertSame(['a@x.com.br'], array_keys($this->delivered));
        $this->assertSame(EmailCampaignRecipient::STATUS_SKIPPED, $campaign->recipients()->where('email', 'saiu@x.com.br')->value('status'));
        $this->assertSame(EmailCampaign::STATUS_DONE, $campaign->fresh()->status);
    }

    public function test_pausada_rascunho_e_agendada_no_futuro_nao_enviam(): void
    {
        $this->campaign(['a@x.com.br' => []], ['status' => EmailCampaign::STATUS_PAUSED]);
        $this->campaign(['b@x.com.br' => []], ['status' => EmailCampaign::STATUS_DRAFT]);
        $this->campaign(['c@x.com.br' => []], ['status' => EmailCampaign::STATUS_SCHEDULED, 'scheduled_at' => now()->addHour(), 'started_at' => null]);
        $due = $this->campaign(['d@x.com.br' => []], ['status' => EmailCampaign::STATUS_SCHEDULED, 'scheduled_at' => now()->subMinute(), 'started_at' => null]);

        $this->runOnce();

        $this->assertSame(['d@x.com.br'], array_keys($this->delivered));
        $this->assertSame(EmailCampaign::STATUS_DONE, $due->fresh()->status);
        $this->assertNotNull($due->fresh()->started_at);
    }

    public function test_recusa_definitiva_falha_na_hora_e_seguidas_pausam_a_campanha(): void
    {
        config(['email_campaigns.max_consecutive_failures' => 3]);
        $campaign = $this->campaign([
            'ok@x.com.br' => [], 'nao1@x.com.br' => [], 'nao2@x.com.br' => [], 'nao3@x.com.br' => [], 'depois@x.com.br' => [],
        ]);

        $this->server = function (string $to) {
            if (str_starts_with($to, 'nao')) {
                throw new UnexpectedResponseException('550 5.1.1 Mailbox does not exist', 550);
            }
        };

        $this->runOnce();

        $statuses = $campaign->recipients()->pluck('status', 'email')->all();
        $this->assertSame(EmailCampaignRecipient::STATUS_SENT, $statuses['ok@x.com.br']);
        $this->assertSame(EmailCampaignRecipient::STATUS_FAILED, $statuses['nao1@x.com.br']);
        $this->assertSame(EmailCampaignRecipient::STATUS_PENDING, $statuses['depois@x.com.br']);
        $this->assertStringContainsString('Mailbox does not exist', $campaign->recipients()->where('email', 'nao1@x.com.br')->value('error'));

        $campaign->refresh();
        $this->assertSame(EmailCampaign::STATUS_PAUSED, $campaign->status);
        $this->assertStringContainsString('Pausada automaticamente', $campaign->last_error);
    }

    public function test_recusa_temporaria_tenta_de_novo_ate_o_limite(): void
    {
        $campaign = $this->campaign(['cheio@x.com.br' => []]);
        $this->server = fn () => throw new UnexpectedResponseException('452 Mailbox full', 452);

        $this->runOnce();
        $recipient = $campaign->recipients()->first();
        $this->assertSame(EmailCampaignRecipient::STATUS_PENDING, $recipient->status);
        $this->assertSame(1, $recipient->attempts);

        $this->runOnce();
        $this->runOnce();
        $recipient->refresh();
        $this->assertSame(EmailCampaignRecipient::STATUS_FAILED, $recipient->status);
        $this->assertSame(3, $recipient->attempts);
        $this->assertSame(EmailCampaign::STATUS_DONE, $campaign->fresh()->status);

        // Reenviar as que falharam: volta para a fila e a campanha volta a enviar.
        $this->server = null;
        $this->assertSame(1, app(\App\Services\EmailCampaign\EmailCampaignService::class)->retryFailed($campaign->fresh()));
        $this->runOnce();
        $this->assertSame(EmailCampaignRecipient::STATUS_SENT, $recipient->fresh()->status);
    }

    public function test_servidor_fora_nao_gasta_tentativa_do_destinatario(): void
    {
        $campaign = $this->campaign(['a@x.com.br' => [], 'b@x.com.br' => []]);
        $this->server = fn () => throw new TransportException('Connection could not be established with host "smtp"');

        $this->runOnce();

        $this->assertSame([0, 0], $campaign->recipients()->orderBy('id')->pluck('attempts')->all());
        $this->assertSame([EmailCampaignRecipient::STATUS_PENDING], $campaign->recipients()->distinct()->pluck('status')->all());
        $campaign->refresh();
        $this->assertSame(EmailCampaign::STATUS_SENDING, $campaign->status);
        $this->assertStringContainsString('Servidor de e-mail indisponível', $campaign->last_error);

        // Voltou: envia e limpa o aviso.
        $this->server = null;
        $this->runOnce();
        $campaign->refresh();
        $this->assertSame(EmailCampaign::STATUS_DONE, $campaign->status);
        $this->assertNull($campaign->last_error);
    }

    public function test_imagem_do_editor_vai_embutida(): void
    {
        $name = '0b8a2f4e-1c2d-4e5f-8a9b-0c1d2e3f4a5b.png';
        File::ensureDirectoryExists(storage_path('app/email-campaigns/images'));
        File::copy(base_path('public/favicon.ico'), storage_path('app/email-campaigns/images/' . $name));

        $url = route('email-campaigns.images.show', $name);
        $this->campaign(['a@x.com.br' => []], ['html' => '<img src="' . $url . '" alt="Banner"><p>{{mesa}}</p>']);

        try {
            $this->runOnce();
        } finally {
            File::delete(storage_path('app/email-campaigns/images/' . $name));
        }

        $email = $this->delivered['a@x.com.br'];
        $this->assertStringNotContainsString($url, $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $this->assertSame($name, $email->getAttachments()[0]->getFilename());

        // O Symfony troca o `cid:arquivo` pelo Content-ID da parte embutida.
        $this->assertStringContainsString('cid:', $email->getHtmlBody());
        $this->assertMatchesRegularExpression('/Content-ID: <[^>]+>/', $this->raw['a@x.com.br']);
        $this->assertStringNotContainsString('cid:' . $name, $this->raw['a@x.com.br']);
    }

    public function test_email_de_teste_sai_na_hora_com_marca_no_assunto(): void
    {
        $campaign = $this->campaign(['ana@x.com.br' => ['nome' => 'Ana', 'mesa' => '3']], ['status' => EmailCampaign::STATUS_DRAFT]);
        $staff = $this->makeUser('Quem testa');
        $this->joinSector($staff, 'TI');

        $this->actingAs($staff)->post(route('email-campaigns.test', $campaign), ['test_email' => 'eu@clube.test'])
            ->assertSessionHas('success');

        $this->assertSame('[TESTE] Ana, venha!', $this->delivered['eu@clube.test']->getSubject());
        $this->assertStringContainsString('sua mesa é a 3', $this->delivered['eu@clube.test']->getHtmlBody());
        $this->assertSame(EmailCampaignRecipient::STATUS_PENDING, $campaign->recipients()->value('status'));
    }
}
