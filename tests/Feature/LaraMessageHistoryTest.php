<?php

namespace Tests\Feature;

use App\Models\LaraMessage;
use Tests\TestCase;

/**
 * Histórico do chat da Lara: a migration, o escopo que a tela usa e a
 * retenção. Nenhum teste aqui autentica ninguém — o model User fixa a conexão
 * `mysql`, então tocá-lo levaria a suíte para fora do SQLite.
 *
 * Sem RefreshDatabase de propósito: rodar a cadeia inteira de migrations hoje
 * falha em `add_columns_member` x `tourments` (as duas adicionam `members.title`),
 * o que derruba qualquer teste que use a trait — nada a ver com a Lara. Aqui só
 * a migration desta feature é aplicada, no SQLite :memory: que o phpunit.xml
 * configura, e cada teste recebe um banco novo.
 */
class LaraMessageHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require base_path('database/migrations/2026_08_03_120000_create_lara_messages_table.php'))->up();
    }

    private function mensagem(int $userId, string $conversa, string $conteudo, string $role = LaraMessage::ROLE_USER): LaraMessage
    {
        return LaraMessage::create([
            'user_id' => $userId,
            'conversation_uuid' => $conversa,
            'role' => $role,
            'conteudo' => $conteudo,
        ]);
    }

    public function test_da_conversa_traz_so_a_conversa_daquele_usuario_em_ordem(): void
    {
        $conversa = '11111111-1111-1111-1111-111111111111';
        $outra = '22222222-2222-2222-2222-222222222222';

        $this->mensagem(1, $conversa, 'primeira');
        $this->mensagem(1, $conversa, 'resposta', LaraMessage::ROLE_ASSISTANT);
        $this->mensagem(1, $outra, 'de outra conversa');
        $this->mensagem(2, $conversa, 'de outro usuário');

        $mensagens = LaraMessage::daConversa(1, $conversa)->get();

        $this->assertSame(['primeira', 'resposta'], $mensagens->pluck('conteudo')->all());
    }

    public function test_status_padrao_e_ok_e_o_erro_fica_registrado(): void
    {
        $conversa = '33333333-3333-3333-3333-333333333333';

        $padrao = $this->mensagem(1, $conversa, 'oi');

        $falha = LaraMessage::create([
            'user_id' => 1,
            'conversation_uuid' => $conversa,
            'role' => LaraMessage::ROLE_ASSISTANT,
            'conteudo' => 'Vou te transferir para o setor responsável, só um momento!',
            'status' => 'erro',
            'latencia_ms' => 30012,
            'erro' => 'conexão: Connection timed out',
        ]);

        $this->assertSame('ok', $padrao->fresh()->status);
        $this->assertSame('erro', $falha->fresh()->status);
        $this->assertSame(30012, $falha->fresh()->latencia_ms);
    }

    public function test_prune_remove_so_o_que_passou_da_retencao(): void
    {
        $conversa = '44444444-4444-4444-4444-444444444444';

        $antiga = $this->mensagem(1, $conversa, 'antiga');
        $antiga->forceFill(['created_at' => now()->subDays(120)])->save();

        $this->mensagem(1, $conversa, 'recente');

        $this->artisan('app:prune-lara-messages', ['--days' => 90])->assertSuccessful();

        $this->assertSame(['recente'], LaraMessage::pluck('conteudo')->all());
    }
}
