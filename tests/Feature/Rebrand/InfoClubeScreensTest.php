<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Aviso;
use App\Models\DataInfo;
use App\Models\Tag;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * InfoClube repaginado (Informações e Avisos): cartões com imagem ou
 * substituto local, busca em toda lista, capa da área com as abas e as ações
 * que dependem de permissão.
 *
 * Renderiza as views com models não salvos e relações já carregadas — o que o
 * controller entregaria —, sem banco.
 */
class InfoClubeScreensTest extends TestCase
{
    use RendersScreens;

    private function info(int $id, array $attributes = [], array $tags = []): DataInfo
    {
        $info = (new DataInfo)->forceFill(array_merge([
            'id' => $id,
            'information_id' => $id,
            'name' => 'Aula de Forró',
            'description' => '<i>Quintas às 19h</i>',
            'responsible' => 'Edvaldo',
            'responsible_contact' => '(21) 99999-0000;',
            'name_price' => 'Mensal;',
            'price_associated' => '70;',
            'price_not_associated' => '90;',
            'status' => 'Aberto',
            'location' => 'Sede',
            'image' => null,
            'created_at' => Carbon::parse('2026-09-01 10:00'),
        ], $attributes));

        $info->setRelation('tags', collect(array_map(fn ($name) => (new Tag)->forceFill(['name' => $name]), $tags)));
        $info->setRelation('user', null);

        return $info;
    }

    private function aviso(int $id, array $attributes = []): Aviso
    {
        $aviso = (new Aviso)->forceFill(array_merge([
            'id' => $id,
            'title' => 'Piscina fechada para manutenção',
            'content' => '<p>Reabre na <b>segunda</b>.</p>',
            'privacy' => 'publico',
            'image' => null,
            'expires_at' => null,
            'created_at' => Carbon::now()->subDay(),
        ], $attributes));

        $aviso->setRelation('creator', null);
        $aviso->setRelation('lembretes', collect());
        $aviso->setRelation('tags', collect([(new Tag)->forceFill(['name' => 'piscina'])]));

        return $aviso;
    }

    private function informacoes(array $items, string $search = ''): array
    {
        return [
            'infos' => new LengthAwarePaginator($items, count($items), 12, 1, ['path' => route('information.index')]),
            'search' => $search,
        ];
    }

    public function test_informacoes_em_cartoes_com_busca_e_capa_da_area(): void
    {
        $html = $this->tela($this->usuario(UserAccess::none()), 'information.index', [], 'information.index', $this->informacoes([
            $this->info(1, ['image' => 'forro.jpg'], ['danca']),
            $this->info(2, ['name' => 'Ballet Infantil']),
        ]), ['q' => '']);

        // Foto real quando há, substituto local quando não há — nunca placehold.co.
        $this->assertStringContainsString('src="' . asset('images/forro.jpg') . '"', $html);
        $this->assertStringNotContainsString('placehold.co', $html);
        $this->assertMatchesRegularExpression('#aria-hidden="true">\s*BI\s*</span>#', $html);

        $this->assertStringContainsString('#danca', $html);
        $this->assertStringContainsString('R$ 70,00', $html);
        $this->assertStringContainsString('https://wa.me/5521999990000', $html);

        // Busca, capa do InfoClube com Informações ativa, sem bootstrap-grid.
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('aria-label="Páginas de InfoClube"', $html);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('information.index'), '#') . '"\s+aria-current="page"#', $html);
        $this->assertStringNotContainsString('bootstrap-grid', $html);

        // Criar só para quem edita o InfoClube.
        $this->assertStringNotContainsString(route('information.create'), $html);
    }

    public function test_quem_edita_ve_o_botao_de_nova_informacao(): void
    {
        $html = $this->tela($this->usuario(new UserAccess([P::INFOCLUBE_EDITAR])), 'information.index', [], 'information.index',
            $this->informacoes([$this->info(1)]));

        $this->assertStringContainsString(route('information.create'), $html);
        $this->assertStringContainsString('Nova informação', $html);
    }

    public function test_busca_sem_resultado_oferece_limpar(): void
    {
        $html = $this->tela($this->usuario(UserAccess::none()), 'information.index', [], 'information.index',
            $this->informacoes([], 'xadrez'), ['q' => 'xadrez']);

        $this->assertStringContainsString('Nenhuma informação corresponde a “xadrez”', $html);
        $this->assertStringContainsString('limpe a busca', $html);
        $this->assertStringContainsString('value="xadrez"', $html);
    }

    public function test_detalhe_e_historico_da_informacao(): void
    {
        $info = $this->info(5, ['description' => '<p>Texto completo</p>'], ['danca']);
        $info->price_rows = [['name' => 'Mensal', 'associated' => '70', 'not_associated' => '90']];
        $info->schedule_rows = [['day' => 'Quinta', 'start' => '19:00', 'end' => '20:30']];
        $info->responsible_rows = [['name' => 'Edvaldo', 'contact' => '(21) 99999-0000']];

        $user = $this->usuario(new UserAccess([P::INFOCLUBE_EDITAR]));

        $show = $this->tela($user, 'information.show', [5], 'information.show', ['info' => $info]);
        $this->assertStringContainsString('aria-label="Voltar"', $show);
        $this->assertStringContainsString(route('information.history', 5), $show);
        $this->assertStringContainsString(route('information.edit', 5), $show);
        $this->assertStringContainsString('R$ 90,00', $show);
        $this->assertStringContainsString('19:00 às 20:30', $show);
        // A tela de detalhe também fica na área: a aba Informações segue ativa.
        $this->assertStringContainsString('aria-label="Páginas de InfoClube"', $show);

        $history = $this->tela($user, 'information.history', [5], 'information.history', ['info' => collect([$info])]);
        $this->assertStringContainsString('Versão 1 de 1', $history);
        $this->assertStringContainsString('Atual', $history);
        $this->assertStringNotContainsString('bootstrap-grid', $history);
    }

    public function test_formularios_da_informacao_sem_placeholder_externo(): void
    {
        $user = $this->usuario(new UserAccess([P::INFOCLUBE_EDITAR]));

        $create = $this->tela($user, 'information.create', [], 'information.create', []);
        $this->assertStringContainsString('action="' . route('information.store') . '"', $create);
        $this->assertStringContainsString('x-text="initials()"', $create);
        $this->assertStringContainsString('Criar informação', $create);
        $this->assertStringNotContainsString('placehold.co', $create);
        $this->assertStringNotContainsString('bootstrap-grid', $create);
        $this->assertStringContainsString('aria-label="Páginas de InfoClube"', $create);

        $info = $this->info(7, [], ['danca', 'forro', 'sede']);
        $info->price_rows = [['name' => 'Mensal', 'associated' => '70', 'not_associated' => '90']];
        $info->schedule_rows = [];
        $info->responsible_rows = [];

        $edit = $this->tela($user, 'information.edit', [7], 'information.edit', ['info' => $info]);
        $this->assertStringContainsString('name="information_id" value="7"', $edit);
        $this->assertStringContainsString('Salvar nova versão', $edit);
        $this->assertStringContainsString(route('information.history', 7), $edit);
        // As tags salvas viajam para o Alpine.
        $this->assertStringContainsString('forro', $edit);
        $this->assertStringNotContainsString('placehold.co', $edit);
    }

    public function test_formularios_do_aviso(): void
    {
        $user = $this->usuario(UserAccess::none());
        $colega = (new \App\Models\User)->forceFill(['id' => 4, 'name' => 'Marina Costa']);

        $create = $this->tela($user, 'avisos.create', [], 'avisos.create', ['users' => collect([$colega])]);
        $this->assertStringContainsString('action="' . route('avisos.store') . '"', $create);
        foreach (['Pessoal', 'Setor', 'Público', 'Grupo'] as $opcao) {
            $this->assertStringContainsString($opcao, $create);
        }
        $this->assertStringContainsString('Marina Costa', $create);
        $this->assertStringContainsString('Publicar aviso', $create);
        // Emojis trocados por ícones do sistema.
        $this->assertStringNotContainsString('🔒', $create);

        $aviso = $this->aviso(8, ['image' => 'piscina.jpg', 'privacy' => 'grupo']);
        $aviso->setRelation('users', collect([$colega]));

        $edit = $this->tela($user, 'avisos.edit', [8], 'avisos.edit', ['aviso' => $aviso, 'users' => collect([$colega])]);
        $this->assertStringContainsString('action="' . route('avisos.update', 8) . '"', $edit);
        $this->assertStringContainsString('name="remove_image"', $edit);
        $this->assertStringContainsString('form="aviso-delete-form"', $edit);
        $this->assertMatchesRegularExpression('#value="grupo"\s+checked#', $edit);
        $this->assertStringContainsString('value="Piscina fechada para manutenção"', $edit);
    }

    public function test_avisos_com_busca_abas_e_expirados(): void
    {
        $ativo = $this->aviso(1, ['image' => 'piscina.jpg', 'expires_at' => Carbon::today()->addDay()]);
        $expirado = $this->aviso(2, ['title' => 'Festa junina', 'expires_at' => Carbon::today()->subDays(5)]);

        $html = $this->tela($this->usuario(UserAccess::none()), 'avisos.index', [], 'avisos.index', [
            'avisos' => collect([$ativo]),
            'expirados' => collect([$expirado]),
            'todos' => collect([$ativo, $expirado]),
            'search' => null,
        ]);

        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('Expirados (1)', $html);
        $this->assertStringContainsString('Expira em breve', $html);
        $this->assertStringContainsString('Público', $html);
        $this->assertStringContainsString('src="' . asset('images/avisos/piscina.jpg') . '"', $html);
        $this->assertStringContainsString('Reabre na segunda.', $html);
        $this->assertStringContainsString(route('avisos.index', ['q' => 'piscina']), $html);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('avisos.index'), '#') . '"\s+aria-current="page"#', $html);
    }

    public function test_detalhe_do_aviso_com_quem_leu(): void
    {
        $aviso = $this->aviso(3, ['privacy' => 'grupo', 'expires_at' => Carbon::today()->addDays(20)]);
        $leitor = (new \App\Models\User)->forceFill(['id' => 4, 'name' => 'Marina Costa']);

        $html = $this->tela($this->usuario(UserAccess::none()), 'avisos.show', [3], 'avisos.show', [
            'aviso' => $aviso,
            'viewHistory' => collect([['user' => $leitor, 'last_view' => Carbon::now()->subHour(), 'count' => 2]]),
        ]);

        // "grupo" tinha caído em "Setor" na tela antiga.
        $this->assertStringContainsString('Grupo', $html);
        $this->assertStringContainsString('Expira em ' . Carbon::today()->addDays(20)->format('d/m/Y'), $html);
        $this->assertStringContainsString('<b>segunda</b>', $html);
        $this->assertStringContainsString('Marina Costa', $html);
        $this->assertStringContainsString('2×', $html);
        $this->assertStringContainsString('max-w-[860px]', $html);
    }
}
