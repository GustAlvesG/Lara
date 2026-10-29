<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\EmailCampaign\EmailCampaign;
use App\Models\EmailCampaign\EmailCampaignRecipient;
use App\Models\EmailCampaign\EmailSuppression;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesEmailCampaignSchema;
use Tests\TestCase;

/**
 * Disparo de E-mails no painel: permissão, campanha em rascunho, importação
 * da lista (.csv do Excel e do Google, .xlsx, sem cabeçalho), as recusas do
 * Enviar, agendar, pausada só acrescenta, duplicar, imagens e as telas.
 */
class EmailCampaignPanelTest extends TestCase
{
    use CreatesEmailCampaignSchema;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        config(['email_campaigns.from_addresses' => ['campanhas@clube.test', 'social@clube.test']]);

        $this->createEmailCampaignSchema();

        // TI tem acesso total: alcança `disparo-emails` sem configurar setor.
        $this->staff = $this->makeUser('Pessoa do Marketing');
        $this->joinSector($this->staff, 'TI');
    }

    private function campaign(array $attributes = []): EmailCampaign
    {
        return EmailCampaign::create($attributes + [
            'name' => 'Festa Junina',
            'subject' => 'Olá, {{primeiro_nome}}!',
            'from_address' => 'campanhas@clube.test',
            'html' => '<p>Oi {{nome}}</p>',
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);
    }

    private function csv(string $content, string $name = 'lista.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lista');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'lista') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'lista.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function import(EmailCampaign $campaign, UploadedFile $file, string $mode = 'append')
    {
        return $this->actingAs($this->staff)->post(route('email-campaigns.recipients.import', $campaign), ['list' => $file, 'mode' => $mode]);
    }

    public function test_sem_permissao_nao_abre(): void
    {
        $outsider = $this->makeUser('Sem Acesso');

        $this->actingAs($outsider)->get(route('email-campaigns.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('email-suppressions.index'))->assertForbidden();
    }

    public function test_cria_rascunho_com_remetente_da_lista(): void
    {
        $this->actingAs($this->staff)->post(route('email-campaigns.store'), [
            'name' => 'Convite', 'subject' => 'Venha', 'from_address' => 'qualquer@outro.test', 'html' => '<p>x</p>',
        ])->assertSessionHasErrors('from_address');

        $this->actingAs($this->staff)->post(route('email-campaigns.store'), [
            'name' => 'Convite', 'subject' => 'Venha', 'from_address' => 'social@clube.test', 'from_name' => 'Social', 'html' => '<p>x</p>',
        ])->assertRedirect();

        $campaign = EmailCampaign::where('name', 'Convite')->firstOrFail();
        $this->assertSame(EmailCampaign::STATUS_DRAFT, $campaign->status);
        $this->assertSame($this->staff->id, $campaign->created_by);
    }

    public function test_csv_do_excel_com_ponto_e_virgula_e_acento_windows(): void
    {
        $campaign = $this->campaign();
        $content = mb_convert_encoding(
            "Nome;E-mail;Título\n"
            . "José Araújo;JOSE@Exemplo.com.br;1001\n"
            . "Repetido;jose@exemplo.com.br;1002\n"
            . "Inválido;não-é-email;1003\n"
            . "Casal;ana@exemplo.com.br; beto@exemplo.com.br;1004\n"
            . ";;\n"
            . "Sem domínio;fulano@localhost;1005\n",
            'Windows-1252', 'UTF-8'
        );

        // A linha do casal tem um ";" a mais: a célula de e-mail fica com um endereço e o resto desloca.
        $this->import($campaign, $this->csv($content))->assertRedirect(route('email-campaigns.show', $campaign))
            ->assertSessionHas('import_rejected', fn ($rejected) => $rejected['count'] === 2);

        $recipients = $campaign->recipients()->orderBy('id')->get();
        $this->assertSame(['jose@exemplo.com.br', 'ana@exemplo.com.br'], $recipients->pluck('email')->all());
        $this->assertSame('José Araújo', $recipients[0]->name);
        $this->assertSame('1001', $recipients[0]->data['titulo']);
        $this->assertSame('Título', $campaign->fresh()->columns['titulo']);
        $this->assertArrayHasKey('titulo', $campaign->fresh()->variables());
    }

    public function test_csv_do_google_com_virgula_e_celula_com_dois_emails(): void
    {
        $campaign = $this->campaign();
        $content = "\xEF\xBB\xBF" . "email,nome\n\"ana@x.com.br; beto@x.com.br\",Família Souza\ncarla@x.com.br,Carla\n";

        $this->import($campaign, $this->csv($content))->assertRedirect();

        $this->assertSame(
            ['ana@x.com.br' => 'Família Souza', 'beto@x.com.br' => 'Família Souza', 'carla@x.com.br' => 'Carla'],
            $campaign->recipients()->orderBy('id')->pluck('name', 'email')->all()
        );
    }

    public function test_lista_sem_cabecalho_e_xlsx(): void
    {
        $campaign = $this->campaign();

        $this->import($campaign, $this->csv("ana@x.com.br\nbeto@x.com.br\n"))->assertRedirect();
        $this->assertSame(2, $campaign->recipients()->count());

        $this->import($campaign, $this->xlsx([
            ['Endereço de e-mail', 'Nome completo', 'Telefone'],
            ['carla@x.com.br', 'Carla Dias', 61999990000],
            ['ana@x.com.br', 'Ana de novo', ''],
        ]))->assertRedirect()->assertSessionHas('success', fn ($message) => str_contains($message, '1 repetido'));

        $carla = $campaign->recipients()->where('email', 'carla@x.com.br')->firstOrFail();
        $this->assertSame('Carla Dias', $carla->name);
        $this->assertSame('61999990000', $carla->data['telefone']);
        $this->assertSame(3, $campaign->recipients()->count());
    }

    public function test_lista_sem_coluna_de_email_e_recusada(): void
    {
        $campaign = $this->campaign();

        $this->import($campaign, $this->csv("Nome;Telefone\nAna;123\n"))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'coluna de e-mail'));

        $this->assertSame(0, $campaign->recipients()->count());
    }

    public function test_respeita_o_teto_de_destinatarios(): void
    {
        config(['email_campaigns.max_recipients' => 2]);
        $campaign = $this->campaign();

        $this->import($campaign, $this->csv("email\na@x.com.br\nb@x.com.br\nc@x.com.br\n"))
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 ficaram de fora'));

        $this->assertSame(2, $campaign->recipients()->count());
    }

    public function test_enviar_recusa_sem_lista_e_com_variavel_que_a_lista_nao_tem(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->staff)->post(route('email-campaigns.start', $campaign), ['when' => 'now'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Importe a lista'));

        $this->import($campaign, $this->csv("email;nome\na@x.com.br;Ana\n"));
        $campaign->update(['html' => '<p>Oi {{nome}}, sua mesa é a {{mesa}}</p>']);

        $this->actingAs($this->staff)->post(route('email-campaigns.start', $campaign), ['when' => 'now'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, '{{mesa}}'));
        $this->assertSame(EmailCampaign::STATUS_DRAFT, $campaign->fresh()->status);

        $campaign->update(['html' => '<p>Oi {{nome}}</p>']);
        $this->actingAs($this->staff)->post(route('email-campaigns.start', $campaign), ['when' => 'now'])->assertSessionHas('success');

        $campaign->refresh();
        $this->assertSame(EmailCampaign::STATUS_SENDING, $campaign->status);
        $this->assertNotNull($campaign->started_at);

        // Enviando: não edita mais.
        $this->actingAs($this->staff)->get(route('email-campaigns.edit', $campaign))->assertRedirect(route('email-campaigns.show', $campaign));
    }

    public function test_agendar_e_desfazer(): void
    {
        $campaign = $this->campaign();
        $this->import($campaign, $this->csv("email\na@x.com.br\n"));

        $this->actingAs($this->staff)->post(route('email-campaigns.start', $campaign), ['when' => 'schedule', 'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('scheduled_at');

        $this->actingAs($this->staff)->post(route('email-campaigns.start', $campaign), ['when' => 'schedule', 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHas('success');
        $this->assertSame(EmailCampaign::STATUS_SCHEDULED, $campaign->fresh()->status);

        $this->actingAs($this->staff)->post(route('email-campaigns.unschedule', $campaign))->assertSessionHas('success');
        $this->assertSame(EmailCampaign::STATUS_DRAFT, $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->scheduled_at);
    }

    public function test_pausada_so_acrescenta_a_lista(): void
    {
        $campaign = $this->campaign(['status' => EmailCampaign::STATUS_PAUSED]);
        $campaign->recipients()->create(['email' => 'ja@x.com.br', 'status' => EmailCampaignRecipient::STATUS_SENT]);

        $this->import($campaign, $this->csv("email\nnovo@x.com.br\n"), 'replace')
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'acrescentada'));

        $this->import($campaign, $this->csv("email\nnovo@x.com.br\nja@x.com.br\n"))->assertSessionHas('success');

        $this->assertSame(
            ['ja@x.com.br' => EmailCampaignRecipient::STATUS_SENT, 'novo@x.com.br' => EmailCampaignRecipient::STATUS_PENDING],
            $campaign->recipients()->orderBy('email')->pluck('status', 'email')->all()
        );

        $this->actingAs($this->staff)->delete(route('email-campaigns.recipients.clear', $campaign))->assertSessionHas('error');
        $this->assertSame(2, $campaign->recipients()->count());
    }

    public function test_pausar_retomar_e_cancelar(): void
    {
        $campaign = $this->campaign(['status' => EmailCampaign::STATUS_SENDING]);

        $this->actingAs($this->staff)->post(route('email-campaigns.pause', $campaign))->assertSessionHas('success');
        $this->assertSame(EmailCampaign::STATUS_PAUSED, $campaign->fresh()->status);

        $this->actingAs($this->staff)->post(route('email-campaigns.resume', $campaign))->assertSessionHas('success');
        $this->assertSame(EmailCampaign::STATUS_SENDING, $campaign->fresh()->status);

        $this->actingAs($this->staff)->delete(route('email-campaigns.destroy', $campaign))->assertSessionHas('error');

        $this->actingAs($this->staff)->post(route('email-campaigns.cancel', $campaign))->assertSessionHas('success');
        $this->assertSame(EmailCampaign::STATUS_CANCELED, $campaign->fresh()->status);

        $this->actingAs($this->staff)->delete(route('email-campaigns.destroy', $campaign))->assertRedirect(route('email-campaigns.index'));
        $this->assertNull(EmailCampaign::find($campaign->id));
    }

    public function test_duplicar_copia_a_lista_de_volta_para_a_fila(): void
    {
        $campaign = $this->campaign(['status' => EmailCampaign::STATUS_DONE]);
        $campaign->recipients()->create(['email' => 'a@x.com.br', 'name' => 'Ana', 'data' => ['mesa' => '7'], 'status' => EmailCampaignRecipient::STATUS_SENT]);
        $campaign->recipients()->create(['email' => 'b@x.com.br', 'status' => EmailCampaignRecipient::STATUS_FAILED, 'error' => 'x']);

        $this->actingAs($this->staff)->post(route('email-campaigns.duplicate', $campaign))->assertRedirect();

        $copy = EmailCampaign::where('name', 'Cópia de Festa Junina')->firstOrFail();
        $this->assertSame(EmailCampaign::STATUS_DRAFT, $copy->status);
        $this->assertSame([EmailCampaignRecipient::STATUS_PENDING], $copy->recipients()->distinct()->pluck('status')->all());
        $this->assertSame('7', $copy->recipients()->where('email', 'a@x.com.br')->first()->data['mesa']);
    }

    public function test_telas_abrem(): void
    {
        $campaign = $this->campaign();
        $this->import($campaign, $this->csv("email;nome\nana@x.com.br;Ana Souza\n"));
        EmailSuppression::create(['email' => 'saiu@x.com.br']);

        $this->actingAs($this->staff)->get(route('email-campaigns.index'))->assertOk()->assertSee('Festa Junina');
        $this->actingAs($this->staff)->get(route('email-campaigns.create'))->assertOk()->assertSee('Modelo inicial');
        $this->actingAs($this->staff)->get(route('email-campaigns.edit', $campaign))->assertOk();
        $this->actingAs($this->staff)->get(route('email-campaigns.show', $campaign))->assertOk()
            ->assertSee('ana@x.com.br')->assertSee('Mandar teste');
        $this->actingAs($this->staff)->get(route('email-suppressions.index'))->assertOk()->assertSee('saiu@x.com.br');
        $this->actingAs($this->staff)->get(route('email-campaigns.progress', $campaign))->assertOk()
            ->assertJsonPath('counts.pending', 1)->assertJsonPath('status', EmailCampaign::STATUS_DRAFT);
        $this->actingAs($this->staff)->get(route('email-campaigns.list-template', 'xlsx'))->assertOk();
        $this->actingAs($this->staff)->get(route('email-campaigns.recipients.export', $campaign))->assertOk();
    }

    public function test_previa_troca_variaveis_escapadas_e_bloqueia_script(): void
    {
        $campaign = $this->campaign(['html' => '<p>Oi {{nome}}</p><script>alert(1)</script>']);
        $campaign->recipients()->create(['email' => 'a@x.com.br', 'name' => '<b>Ana</b>']);

        $response = $this->actingAs($this->staff)->get(route('email-campaigns.preview', $campaign))->assertOk();

        $this->assertStringContainsString('Oi &lt;b&gt;Ana&lt;/b&gt;', $response->getContent());
        $this->assertStringNotContainsString('<script>', $response->getContent());
        $this->assertStringContainsString("script-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_imagem_do_editor_sobe_e_abre_so_logado(): void
    {
        $response = $this->actingAs($this->staff)->postJson(route('email-campaigns.images.store'), [
            'image' => UploadedFile::fake()->image('banner.png', 600, 200),
        ])->assertOk();

        $url = $response->json('url');
        $file = basename(parse_url($url, PHP_URL_PATH));
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}\.png$/', $file);

        $this->actingAs($this->staff)->get(route('email-campaigns.images.show', $file))->assertOk();
        $this->actingAs($this->staff)->get(route('email-campaigns.images.show', '../../.env'))->assertNotFound();

        File::delete(storage_path('app/email-campaigns/images/' . $file));
    }

    public function test_nao_enviar_para_aceita_varios_e_normaliza(): void
    {
        $this->actingAs($this->staff)->post(route('email-suppressions.store'), [
            'emails' => "Ana@X.com.br\nbeto@x.com.br; lixo\nana@x.com.br",
            'reason' => 'pediu',
        ])->assertSessionHas('success', fn ($message) => str_contains($message, '2 e-mail(s)') && str_contains($message, 'lixo'));

        $this->assertSame(['ana@x.com.br', 'beto@x.com.br'], EmailSuppression::orderBy('email')->pluck('email')->all());

        $suppression = EmailSuppression::where('email', 'ana@x.com.br')->first();
        $this->actingAs($this->staff)->delete(route('email-suppressions.destroy', $suppression))->assertSessionHas('success');
        $this->assertSame(1, EmailSuppression::count());
    }
}
