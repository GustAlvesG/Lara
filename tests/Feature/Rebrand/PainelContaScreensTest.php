<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Contactor;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Painel inicial, Home Assistant, Bot WhatsApp, Conta (usuários, setores,
 * perfil) e as telas de entrada, no visual novo: paleta por tokens, ação em
 * grená, carmim só no logo e busca em toda lista.
 *
 * Models não salvos, sem banco; usuário mock.
 */
class PainelContaScreensTest extends TestCase
{
    use RendersScreens;

    private function miolo(string $html): string
    {
        $inicio = strpos($html, '<main>');
        $fim = strpos($html, '<footer');

        return substr($html, (int) $inicio, $fim === false ? null : $fim - (int) $inicio);
    }

    private function assertSemPaletaAntiga(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '#\b(?:bg|text|border)-(?:gray|indigo|red|green|amber|violet|sky|teal|rose|emerald)-\d{2,3}\b#',
            $this->miolo($html)
        );
    }

    public function test_painel_inicial_por_area_com_graficos_nas_cores_do_tema(): void
    {
        $pessoa = $this->usuario(new UserAccess([P::SIV_BUSCA, P::RESERVAS_AGENDAMENTOS]), 'Marina Souza Lima');
        $serie = ['labels' => ['01/10'], 'data' => [3]];

        $html = $this->tela($pessoa, 'dashboard', [], 'dashboard', [
            'user' => $pessoa,
            'avisos' => collect(),
            'info' => ['total' => 12, 'categories' => collect()],
            'parking' => ['today' => 40, 'month' => 900, 'authTotal' => 7, 'authExpiring' => 2, 'chart' => $serie],
            'reservations' => ['today' => 5, 'upcomingCount' => 9, 'revenue' => 1234.5, 'upcoming' => collect(), 'chart' => ['labels' => collect(), 'data' => collect()]],
            'partners' => ['companies' => 4, 'workers' => 30, 'allowedToday' => 11, 'deniedToday' => 1, 'chart' => ['labels' => [], 'allowed' => [], 'denied' => []]],
        ]);

        // Primeiro nome, sem título duplicado nem faixa vermelha de boas-vindas.
        $this->assertStringContainsString('Olá, Marina', $html);
        $this->assertStringNotContainsString('from-red-800', $html);
        // Cada bloco na cor da sua área.
        $this->assertStringContainsString('--c: var(--a-portaria)', $html);
        $this->assertStringContainsString('--c: var(--a-reservas)', $html);
        $this->assertStringContainsString('2 expiram em 30 dias', $html);
        $this->assertStringContainsString('R$ 1.234,50', $html);
        // Sem a permissão, o bloco do Home Assistant não aparece.
        $this->assertStringNotContainsString('Nenhum interruptor cadastrado', $html);
        // Gráficos leem os tokens do tema, não cores fixas.
        $this->assertStringContainsString("token('a-portaria-ink')", $html);
        $this->assertStringNotContainsString('#6366f1', $html);
        $this->assertSemPaletaAntiga($html);
    }

    public function test_interruptor_do_painel_diz_o_estado_em_texto(): void
    {
        $this->requisicaoEm('dashboard');
        $contactor = (new Contactor)->forceFill(['id' => 3, 'name' => 'Quadra 1']);
        $state = new class {
            public bool $on = true;

            public function reason(): string
            {
                return 'Reserva até 20:00';
            }

            public function isManual(): bool
            {
                return true;
            }
        };

        $html = (string) $this->blade('<x-dashboard.ha-switch :contactor="$contactor" :state="$state" />', compact('contactor', 'state'));

        $this->assertStringContainsString('Ligado · Reserva até 20:00', $html);
        $this->assertStringContainsString('aria-label="Desligar Quadra 1"', $html);
        $this->assertStringContainsString(route('home-assistant.quick.clear', $contactor), $html);
        $this->assertStringContainsString('peer-checked:bg-ok', $html);
    }

    public function test_bot_whatsapp_mostra_o_modo_sombra_e_busca_fluxos(): void
    {
        $fluxo = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'bot_flows';
        };
        $fluxo->forceFill(['id' => 2, 'name' => 'Carro de aplicativo', 'slug' => 'uber', 'active' => true]);
        $numeros = array_fill_keys(['conversas', 'recebidas', 'respostas', 'transbordos', 'pedidos_uber', 'falhas'], 0);
        $numeros['falhas'] = 2;

        $html = $this->tela($this->usuario(new UserAccess([P::BOT_WHATSAPP])), 'poli-bot.index', [], 'poli-bot.index', [
            'modo' => 'shadow',
            'numeros' => $numeros,
            'fluxos' => [['model' => $fluxo, 'gatilho' => 'uber, carro', 'passos' => 6, 'erros' => [], 'versao' => null]],
        ]);

        // A chave do modo é "shadow" — não pode virar nome de classe.
        $this->assertStringContainsString('Modo sombra', $html);
        $this->assertStringContainsString('Respostas (simuladas)', $html);
        $this->assertStringContainsString('id="fluxos"', $html);
        $this->assertStringContainsString(route('poli-bot.flows.toggle', 2), $html);
        $this->assertSemPaletaAntiga($html);
    }

    public function test_usuarios_com_busca_na_pagina_e_iniciais(): void
    {
        $setor = (new Sector)->forceFill(['id' => 1, 'name' => 'Portaria', 'full_access' => false]);
        $setor->setRelation('pivot', (object) ['role' => 'coordinator']);

        $user = (new User)->forceFill(['id' => 21, 'name' => 'Rafael Porteiro', 'email' => 'rafael@clube.test', 'matricula' => '00123', 'status_id' => 1]);
        $user->setRelation('sectors', collect([$setor]));
        $user->setRelation('directPermissions', collect());

        $html = $this->tela($this->usuario(new UserAccess([P::USUARIOS_GERENCIAR, P::SETORES_GERENCIAR])), 'users.index', [], 'user.index', [
            'users' => collect([$user]),
        ]);

        $this->assertStringContainsString('laraSearch(', $html);
        $this->assertStringContainsString('id="usuarios"', $html);
        $this->assertMatchesRegularExpression('#aria-hidden="true">RP</span>#', $html);
        $this->assertStringContainsString('coordenador', $html);
        $this->assertStringContainsString(route('sectors.index'), $html);
        $this->assertStringContainsString(route('users.destroy', 21), $html);
        $this->assertStringNotContainsString('filterUsers', $html);
        $this->assertSemPaletaAntiga($html);
    }

    public function test_setores_e_historico_de_acesso_com_busca(): void
    {
        $setor = (new Sector)->forceFill(['id' => 1, 'name' => 'Diretoria', 'description' => 'Tudo', 'full_access' => true]);
        $setor->users_count = 3;
        $setor->permissions_count = 0;
        $gestao = $this->usuario(new UserAccess([P::USUARIOS_GERENCIAR, P::SETORES_GERENCIAR]));

        $html = $this->tela($gestao, 'sectors.index', [], 'sector.index', ['sectors' => collect([$setor])]);
        $this->assertStringContainsString('id="sectors-container"', $html);
        $this->assertMatchesRegularExpression('#3 membros ·\s+todas as permissões#u', $html);
        $this->assertStringContainsString('Acesso total', $html);
        $this->assertSemPaletaAntiga($html);

        $html = $this->tela($gestao, 'sectors.audit', [], 'sector.audit', [
            'logs' => new LengthAwarePaginator([], 0, 50, 1, ['path' => route('sectors.audit')]),
        ], ['q' => 'rafael']);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('value="rafael"', $html);
        $this->assertStringContainsString('Nenhuma mudança encontrada com essa busca.', $html);
    }

    public function test_login_sem_faixa_vermelha_e_com_as_duas_formas_de_entrar(): void
    {
        $this->requisicaoEm('login');
        $html = (string) $this->view('auth.login');

        $this->assertStringContainsString('name="login_type"', $html);
        // Matrícula é a forma principal: vem selecionada e é a primeira aba.
        $this->assertStringContainsString('name="login_type" id="login_type" value="matricula"', $html);
        $this->assertLessThan(strpos($html, 'data-login-tab="email"'), strpos($html, 'data-login-tab="matricula"'));
        $this->assertStringContainsString('name="password"', $html);
        // Carmim só no logo; a ação é grená e as abas usam tokens.
        $this->assertStringNotContainsString('linear-gradient(90deg,rgba(160, 0, 1', $html);
        $this->assertStringNotContainsString('style="background: #A00001;"', $html);
        $this->assertStringContainsString("const ACTIVE = ['bg-surface', 'shadow-card', 'text-ink'];", $html);
        $this->assertStringContainsString('bg-grena', $html);
        $this->assertDoesNotMatchRegularExpression('#\b(?:bg|text|border)-(?:gray|indigo|red)-\d{2,3}\b#', $html);
    }

    public function test_demais_telas_de_entrada_usam_o_mesmo_cartao(): void
    {
        foreach (['register', 'forgot-password', 'verify-email', 'confirm-password', 'reset-password'] as $tela) {
            $source = file_get_contents(resource_path("views/auth/{$tela}.blade.php"));
            $this->assertStringContainsString('<x-auth-card', $source, $tela);
            $this->assertDoesNotMatchRegularExpression('#(?:gray|indigo|red)-\d{2,3}#', $source, $tela);
        }
    }

    public function test_porta_de_entrada_e_do_lara_e_nao_a_do_laravel(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('O sistema do Clube, feito pelo Clube.', $html);
        $this->assertStringContainsString('href="' . route('login') . '"', $html);
        $this->assertStringContainsString('href="' . route('register') . '"', $html);
        // As áreas aparecem nas suas cores.
        $this->assertStringContainsString('--c: var(--a-portaria)', $html);
        $this->assertStringContainsString('--c: var(--a-reservas)', $html);
        // Nada da página padrão do framework, nem o Tailwind por CDN.
        $this->assertStringNotContainsString('laravel.com', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('#FF2D20', $html);
    }
}
