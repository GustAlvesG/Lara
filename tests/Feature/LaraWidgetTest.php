<?php

namespace Tests\Feature;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Http\Controllers\LaraChatController;
use App\Models\LaraMessage;
use App\Services\Lara\LaraClient;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Lara (IA) como balão do layout: aparece em toda tela para quem tem a
 * permissão, carrega a conversa só quando abre (GET /lara/conversa) e /lara
 * virou atalho que abre o balão na página de onde a pessoa veio.
 *
 * Usuário mock (o User está preso à conexão mysql) e a VM da IA trocada por
 * um dublê — nada aqui sai da máquina.
 */
class LaraWidgetTest extends TestCase
{
    use RendersScreens;

    private const CONVERSA = '11111111-1111-1111-1111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        (require base_path('database/migrations/2026_08_03_120000_create_lara_messages_table.php'))->up();

        $lara = \Mockery::mock(LaraClient::class);
        $lara->shouldReceive('enabled')->andReturn(true);
        $lara->shouldReceive('healthy')->andReturn(true);
        $this->app->instance(LaraClient::class, $lara);
    }

    private function comLara()
    {
        return $this->usuario(new UserAccess([P::LARA, P::SIV_BUSCA]));
    }

    private function mensagem(string $role, string $conteudo, $quando = null): void
    {
        $m = LaraMessage::create([
            'user_id' => 9,
            'conversation_uuid' => self::CONVERSA,
            'role' => $role,
            'conteudo' => $conteudo,
        ]);

        if ($quando) {
            $m->forceFill(['created_at' => $quando])->save();
        }
    }

    public function test_balao_aparece_em_qualquer_tela_para_quem_tem_a_permissao(): void
    {
        $html = $this->actingAs($this->comLara())->get(route('docs.show', 'README'))->assertOk()->getContent();

        $this->assertStringContainsString('laraWidget(', $html);
        $this->assertStringContainsString('aria-label="Abrir a Lara (assistente de IA)"', $html);
        // URLs fixas, não route(): o layout não pode cair por cache de rotas.
        // Vêm no JSON do x-data, com as barras escapadas.
        $this->assertStringContainsString(url('/lara/conversa'), str_replace('\\', '', $html));
    }

    public function test_sem_permissao_nao_ha_balao(): void
    {
        $html = $this->actingAs($this->usuario(new UserAccess([P::SIV_BUSCA])))
            ->get(route('docs.show', 'README'))->assertOk()->getContent();

        $this->assertStringNotContainsString('laraWidget(', $html);
    }

    public function test_lara_volta_para_a_pagina_anterior_com_o_balao_aberto(): void
    {
        $this->actingAs($this->comLara())
            ->from(route('docs.index'))
            ->get(route('lara.index'))
            ->assertRedirect(route('docs.index'))
            ->assertSessionHas(LaraChatController::OPEN_FLASH, true);
    }

    public function test_lara_sem_pagina_anterior_abre_o_balao_no_inicio(): void
    {
        $this->actingAs($this->comLara())
            ->get(route('lara.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(LaraChatController::OPEN_FLASH, true);
    }

    public function test_conversa_traz_o_historico_e_o_estado_da_ia(): void
    {
        $this->mensagem(LaraMessage::ROLE_USER, 'qual o horário da academia?');
        $this->mensagem(LaraMessage::ROLE_ASSISTANT, 'Das 6h às 22h.');

        $this->actingAs($this->comLara())
            ->withSession(['lara.conversation_uuid' => self::CONVERSA])
            ->getJson(route('lara.conversation'))
            ->assertOk()
            ->assertJsonPath('mensagens.0.conteudo', 'qual o horário da academia?')
            ->assertJsonPath('mensagens.1.role', 'assistant')
            ->assertJsonPath('configurado', true)
            ->assertJsonPath('disponivel', true)
            ->assertJsonPath('pendente', false);
    }

    public function test_pergunta_recente_sem_resposta_fica_pendente(): void
    {
        // Feita numa tela anterior: o ask() de lá ainda está esperando a IA.
        $this->mensagem(LaraMessage::ROLE_USER, 'e a piscina?');

        $this->actingAs($this->comLara())
            ->withSession(['lara.conversation_uuid' => self::CONVERSA])
            ->getJson(route('lara.conversation'))
            ->assertJsonPath('pendente', true);
    }

    public function test_pergunta_antiga_sem_resposta_nao_trava_o_balao(): void
    {
        $prazo = (int) config('services.lara.timeout', 30) + 10;
        $this->mensagem(LaraMessage::ROLE_USER, 'ficou sem resposta', now()->subSeconds($prazo + 5));

        $this->actingAs($this->comLara())
            ->withSession(['lara.conversation_uuid' => self::CONVERSA])
            ->getJson(route('lara.conversation'))
            ->assertJsonPath('pendente', false);
    }

    public function test_conversa_exige_a_permissao(): void
    {
        $this->actingAs($this->usuario(new UserAccess([P::SIV_BUSCA])))
            ->getJson(route('lara.conversation'))
            ->assertForbidden();
    }
}
