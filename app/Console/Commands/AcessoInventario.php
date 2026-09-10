<?php

namespace App\Console\Commands;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Fase 1 da reforma do acesso: fotografa o que existe hoje (papéis do Spatie,
 * permissões de cada um, quem está em cada papel, setores e membros) e grava o
 * **esqueleto do de-para** que a migração de dados vai ler.
 *
 * Só lê o banco. Não cria, não altera e não apaga nada — pode rodar em
 * produção no meio do expediente.
 *
 * Existe como comando, e não como uma consulta que alguém monta na hora, por
 * um motivo só: a migração vai mover gente de acesso. O que não estiver nesta
 * lista não vai ser migrado, e o efeito de esquecer um papel só aparece na
 * segunda-feira, quando a pessoa não consegue entrar. A lista tem que sair do
 * banco, não da memória de quem escreve a migration.
 *
 * O arquivo gerado sai com o lado esquerdo preenchido (o que existe) e o lado
 * direito em branco (para onde vai). Quem preenche é o administrador; depois
 * ele entra no repositório e vira a entrada da migration da fase 3.
 *
 *     php artisan acesso:inventario
 *     php artisan acesso:inventario --so-listar     # só a tela, não grava
 */
class AcessoInventario extends Command
{
    protected $signature = 'acesso:inventario
                            {--saida= : Caminho do arquivo gerado (padrão: database/data/acesso-de-para.php)}
                            {--force : Sobrescreve o arquivo se ele já existir}
                            {--so-listar : Só mostra o inventário na tela, sem gravar arquivo}';

    protected $description = 'Levanta papéis, permissões e setores de hoje e gera o esqueleto do de-para da reforma de acesso.';

    /**
     * Os dois papéis que sobrevivem à reforma. Não entram no de-para porque
     * não viram setor nenhum: `admin` continua sendo o nível de administração
     * e `user` o piso de todo mundo logado.
     */
    private const PAPEIS_MANTIDOS = ['admin', 'user'];

    /**
     * Regras de acesso que hoje estão codificadas como Gate no
     * AppServiceProvider — "estar no setor X" escrito em PHP. A migração da
     * fase 3 cria uma permissão com EXATAMENTE cada um destes nomes e concede
     * ao setor indicado, para que nenhuma view ou rota precise mudar.
     *
     * Estão aqui para aparecer no relatório: quem preenche o de-para precisa
     * saber que estes setores já têm destino definido, e conferir se o nome
     * bate com o que existe no banco.
     */
    private const GATES_QUE_VIRAM_PERMISSAO = [
        'manage-freelancer-payments' => 'Contabilidade, Gerência',
        'track-freelancer-batches'   => 'Comercial, Contabilidade, Gerência',
        'acessar-cotacao'            => 'Contabilidade',
        'manage-placar-cadastro'     => 'Esporte',
        'view-placar-scout'          => 'Esporte',
    ];

    /**
     * Consultados em resources/views/layouts/app.blade.php (linhas 216-217) e
     * **nunca definidos em lugar nenhum** — hoje dão false para todo mundo, e
     * o menu de banco de horas não aparece para ninguém.
     *
     * Não é bug desta reforma, mas vira decisão dela: quando o admin passar a
     * ter tudo, um Gate inexistente continua false, mas uma permissão criada
     * com esse nome apareceria para o admin. Ou se cria a permissão de
     * verdade, ou se corrige a view.
     */
    private const GATES_FANTASMAS = ['manage-comp-time', 'view-comp-time'];

    /** Quantos usuários listar por papel no arquivo antes de resumir. */
    private const MAX_USUARIOS_LISTADOS = 80;

    public function handle(): int
    {
        $papeis  = Role::with(['permissions', 'users'])->orderBy('name')->get();
        $setores = Sector::with('users')->orderBy('name')->get();

        $this->cabecalho();
        $this->mostrarPapeis($papeis);
        $this->mostrarSetores($setores);

        $pendencias = $this->mostrarPendencias($papeis);

        $this->mostrarGates();

        if ($this->option('so-listar')) {
            return self::SUCCESS;
        }

        return $this->gravar($papeis, $setores, $pendencias);
    }

    private function cabecalho(): void
    {
        $conexao = config('database.default');
        $banco   = config("database.connections.{$conexao}.database");

        $this->newLine();
        $this->line("<options=bold>Inventário de acesso</> — conexão <options=bold>{$conexao}</>, banco <options=bold>{$banco}</>");
        $this->comment('Somente leitura: este comando não altera nada.');
        $this->newLine();
    }

    /** @param \Illuminate\Support\Collection<int,Role> $papeis */
    private function mostrarPapeis($papeis): void
    {
        $this->line('<options=bold>PAPÉIS (Spatie)</>');

        if ($papeis->isEmpty()) {
            $this->warn('Nenhum papel cadastrado.');
            $this->newLine();

            return;
        }

        $this->table(
            ['Papel', 'Usuários', 'Ativos', 'Permissões', 'Destino'],
            $papeis->map(function (Role $papel) {
                $ativos = $papel->users->filter(fn($u) => (int) $u->status_id === 1)->count();

                return [
                    $papel->name,
                    $papel->users->count(),
                    $ativos,
                    $papel->permissions->count(),
                    in_array($papel->name, self::PAPEIS_MANTIDOS, true)
                        ? 'mantido como está'
                        : '<fg=yellow>vira setor — preencher</>',
                ];
            })->all()
        );

        $this->newLine();
    }

    /** @param \Illuminate\Support\Collection<int,Sector> $setores */
    private function mostrarSetores($setores): void
    {
        $this->line('<options=bold>SETORES QUE JÁ EXISTEM</>');
        $this->comment('Use exatamente estes nomes no de-para para reaproveitar o setor; qualquer outro nome cria um setor novo.');

        if ($setores->isEmpty()) {
            $this->warn('Nenhum setor cadastrado.');
            $this->newLine();

            return;
        }

        $this->table(
            ['Setor', 'Membros', 'Coordenadores'],
            $setores->map(function (Sector $setor) {
                $coordenadores = $setor->users
                    ->filter(fn($u) => $u->pivot->role === 'coordinator')
                    ->pluck('name')
                    ->all();

                return [
                    $setor->name,
                    $setor->users->count(),
                    $coordenadores ? implode(', ', $coordenadores) : '<fg=yellow>nenhum</>',
                ];
            })->all()
        );

        $this->newLine();
    }

    /**
     * O que a migração não consegue decidir sozinha. Cada item aqui é uma
     * pessoa que pode ficar sem acesso, ou uma permissão que pode sumir do
     * mapa, se ninguém olhar.
     *
     * @param  \Illuminate\Support\Collection<int,Role>  $papeis
     * @return array{semPapel: \Illuminate\Support\Collection, multiPapel: \Illuminate\Support\Collection, orfas: \Illuminate\Support\Collection, individuais: \Illuminate\Support\Collection}
     */
    private function mostrarPendencias($papeis): array
    {
        $semPapel = User::doesntHave('roles')
            ->where('status_id', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'matricula']);

        $multiPapel = User::has('roles', '>', 1)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'matricula']);

        // Permissão que não está em papel nenhum não tem de onde ser copiada:
        // ou alguém a concede a um setor à mão, ou ela morre no processo.
        $orfas = Permission::doesntHave('roles')->orderBy('name')->get(['id', 'name']);

        // Concessões diretas que já existem hoje. Continuam valendo depois da
        // reforma (viram "permissão individual"), então não precisam de
        // de-para — mas quem preenche precisa saber que elas existem, senão
        // conclui errado que fulano só tem o que o papel dele dá.
        $individuais = User::has('permissions')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $this->line('<options=bold>O QUE PRECISA DE DECISÃO</>');

        if ($semPapel->isNotEmpty()) {
            $this->warn("{$semPapel->count()} usuário(s) ATIVO(s) sem papel nenhum — não vão para setor algum pelo mapa dos papéis:");
            foreach ($semPapel as $u) {
                $this->line("  · {$u->name} <fg=gray>({$u->email})</>");
            }
            $this->newLine();
        }

        if ($multiPapel->isNotEmpty()) {
            $this->warn("{$multiPapel->count()} usuário(s) com MAIS DE UM papel — vão para um setor de cada papel:");
            foreach ($multiPapel as $u) {
                $this->line("  · {$u->name} <fg=gray>(" . $u->roles->pluck('name')->implode(', ') . ')</>');
            }
            $this->newLine();
        }

        if ($orfas->isNotEmpty()) {
            $this->warn("{$orfas->count()} permissão(ões) que não estão em papel nenhum — sem destino automático:");
            $this->line('  ' . $orfas->pluck('name')->implode(', '));
            $this->newLine();
        }

        if ($individuais->isNotEmpty()) {
            $this->line("{$individuais->count()} usuário(s) com permissão concedida direto (fora do papel). Continuam valendo como permissão individual:");
            foreach ($individuais as $u) {
                $this->line("  · {$u->name} <fg=gray>(" . $u->permissions->pluck('name')->implode(', ') . ')</>');
            }
            $this->newLine();
        }

        if ($semPapel->isEmpty() && $multiPapel->isEmpty() && $orfas->isEmpty() && $individuais->isEmpty()) {
            $this->info('Nada ambíguo: todo usuário ativo tem exatamente um papel e toda permissão está em algum papel.');
            $this->newLine();
        }

        return compact('semPapel', 'multiPapel', 'orfas', 'individuais');
    }

    private function mostrarGates(): void
    {
        $this->line('<options=bold>REGRAS QUE HOJE ESTÃO NO CÓDIGO</>');
        $this->comment('A migração cria uma permissão com cada um destes nomes e concede ao setor indicado. Confira se o setor existe com esse nome exato.');

        $this->table(
            ['Permissão a criar', 'Setores que recebem'],
            collect(self::GATES_QUE_VIRAM_PERMISSAO)
                ->map(fn(string $setores, string $gate) => [$gate, $setores])
                ->values()
                ->all()
        );

        $this->warn('Gates consultados na view do menu mas nunca definidos (hoje sempre false): ' . implode(', ', self::GATES_FANTASMAS));
        $this->comment('Decidir: criar a permissão de verdade, ou tirar a checagem da view.');
        $this->newLine();
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Role>  $papeis
     * @param  \Illuminate\Support\Collection<int,Sector>  $setores
     */
    private function gravar($papeis, $setores, array $pendencias): int
    {
        $caminho = $this->option('saida') ?: database_path('data/acesso-de-para.php');

        if (File::exists($caminho) && !$this->option('force')) {
            $this->error('O arquivo já existe: ' . $caminho);
            $this->comment('Use --force para sobrescrever (você perde o que já preencheu) ou --so-listar para não gravar.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($caminho));
        File::put($caminho, $this->montarArquivo($papeis, $setores, $pendencias));

        $aPreencher = $papeis->reject(fn(Role $p) => in_array($p->name, self::PAPEIS_MANTIDOS, true))->count();

        $this->info('Esqueleto gravado em ' . $caminho);
        $this->line("Faltam <options=bold>{$aPreencher}</> papel(éis) para você apontar o setor de destino.");
        $this->comment('Abra o arquivo, preencha, e commite: é ele que a migração da fase 3 vai ler.');

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Role>  $papeis
     * @param  \Illuminate\Support\Collection<int,Sector>  $setores
     */
    private function montarArquivo($papeis, $setores, array $pendencias): string
    {
        $conexao = config('database.default');
        $banco   = config("database.connections.{$conexao}.database");

        $l = [];
        $l[] = '<?php';
        $l[] = '';
        $l[] = '/**';
        $l[] = ' * DE-PARA DO ACESSO — papéis do Spatie  ->  setores.';
        $l[] = ' *';
        $l[] = ' * Gerado por `php artisan acesso:inventario` em ' . now()->format('d/m/Y H:i')
             . ' contra o banco `' . $banco . '`.';
        $l[] = ' *';
        $l[] = ' * O lado esquerdo é o que existia no banco naquele momento. O lado direito';
        $l[] = ' * é o que VOCÊ decide, e é o que a migração da fase 3 vai executar:';
        $l[] = ' *';
        $l[] = ' *   setor          nome do setor de destino. Se já existir com esse nome exato,';
        $l[] = ' *                  é reaproveitado; senão é criado. `null` descarta o papel —';
        $l[] = ' *                  quem só tinha ele fica sem acesso nenhum.';
        $l[] = ' *   permissoes     "copiar"  o setor nasce com as permissões que o papel tinha';
        $l[] = ' *                            (ninguém perde acesso no deploy; você poda depois';
        $l[] = ' *                            na tela, com calma).';
        $l[] = ' *                  "nenhuma" o setor nasce vazio e você monta a matriz do zero.';
        $l[] = ' *   coordenadores  e-mails de quem entra como COORDENADOR. Todo o resto do papel';
        $l[] = ' *                  entra como colaborador.';
        $l[] = ' *';
        $l[] = ' * Regravar este arquivo com --force apaga o que você preencheu.';
        $l[] = ' */';

        // O retrato do banco vem depois do docblock e antes do `return`: é
        // referência para quem preenche, e memória da decisão depois.
        $l = array_merge($l, $this->retratoDoBanco($setores, $pendencias));

        $l[] = '';
        $l[] = 'return [';
        $l[] = '';
        $l[] = '    /*';
        $l[] = '    |--------------------------------------------------------------------------';
        $l[] = '    | Papéis  ->  setores';
        $l[] = '    |--------------------------------------------------------------------------';
        $l[] = '    */';
        $l[] = '';
        $l[] = "    'papeis' => [";

        foreach ($papeis as $papel) {
            $l = array_merge($l, $this->blocoDoPapel($papel));
        }

        $l[] = '    ],';
        $l[] = '';
        $l[] = '    /*';
        $l[] = '    |--------------------------------------------------------------------------';
        $l[] = '    | Exceções pessoa a pessoa';
        $l[] = '    |--------------------------------------------------------------------------';
        $l[] = '    |';
        $l[] = '    | Aplicadas DEPOIS do mapa dos papéis, e somam-se a ele. Use para quem não';
        $l[] = '    | se resolve pelo papel: quem está em dois setores, quem coordena um setor';
        $l[] = '    | e colabora em outro, e os usuários sem papel nenhum listados abaixo.';
        $l[] = '    |';
        $l[] = "    |     'fulano@clube.com' => [";
        $l[] = "    |         ['setor' => 'Comercial',      'papel' => 'coordinator'],";
        $l[] = "    |         ['setor' => 'Contabilidade',  'papel' => 'collaborator'],";
        $l[] = '    |     ],';
        $l[] = '    */';
        $l[] = '';
        $l[] = "    'pessoas' => [";

        $l = array_merge($l, $this->blocoDasPessoas($pendencias));

        $l[] = '    ],';
        $l[] = '';
        $l[] = '];';
        $l[] = '';

        return implode(PHP_EOL, $l);
    }

    /** @return string[] */
    private function blocoDoPapel(Role $papel): array
    {
        $mantido = in_array($papel->name, self::PAPEIS_MANTIDOS, true);
        $ativos  = $papel->users->filter(fn($u) => (int) $u->status_id === 1)->count();

        $l = [];
        $l[] = '';
        $l[] = '        // ' . str_pad(' ' . $papel->name . ' ', 68, '-', STR_PAD_BOTH);
        $l[] = '        // ' . $papel->users->count() . ' usuário(s), ' . $ativos . ' ativo(s) · '
             . $papel->permissions->count() . ' permissão(ões)';

        if ($mantido) {
            $l[] = '        //';
            $l[] = '        // Este papel NÃO vira setor: continua existindo depois da reforma.';
            $l[] = '        // Deixado aqui só para o inventário ficar completo.';
        }

        $l = array_merge($l, $this->comentarioLista(
            'permissões',
            $papel->permissions->pluck('name')->sort()->values()->all()
        ));

        $usuarios = $papel->users
            ->sortBy('name')
            ->map(fn(User $u) => $u->email . ((int) $u->status_id === 1 ? '' : ' [INATIVO]'))
            ->values()
            ->all();

        $l = array_merge($l, $this->comentarioLista('usuários', $usuarios, self::MAX_USUARIOS_LISTADOS));

        $l[] = '        ' . var_export($papel->name, true) . ' => [';

        if ($mantido) {
            $l[] = "            'setor'         => null, // papel mantido — não migrar";
            $l[] = "            'permissoes'    => 'nenhuma',";
            $l[] = "            'coordenadores' => [],";
        } else {
            $l[] = "            'setor'         => null,     // <<< PREENCHA: nome do setor de destino";
            $l[] = "            'permissoes'    => 'copiar', // 'copiar' | 'nenhuma'";
            $l[] = "            'coordenadores' => [";
            $l[] = "                // 'fulano@clube.com',";
            $l[] = '            ],';
        }

        $l[] = '        ],';

        return $l;
    }

    /** @return string[] */
    private function blocoDasPessoas(array $pendencias): array
    {
        $l = [];

        if ($pendencias['semPapel']->isNotEmpty()) {
            $l[] = '';
            $l[] = '        // Ativos SEM PAPEL NENHUM — o mapa dos papéis não alcança estes.';
            $l[] = '        // Se ficarem de fora, ficam sem acesso a nada além do que for público.';
            foreach ($pendencias['semPapel'] as $u) {
                $l[] = '        // ' . $u->email . '  (' . $this->limpar($u->name) . ')';
            }
        }

        if ($pendencias['multiPapel']->isNotEmpty()) {
            $l[] = '';
            $l[] = '        // Com MAIS DE UM papel — pelo mapa acima vão para um setor de cada.';
            $l[] = '        // Liste aqui só se quiser um arranjo diferente disso.';
            foreach ($pendencias['multiPapel'] as $u) {
                $l[] = '        // ' . $u->email . '  (' . $u->roles->pluck('name')->implode(' + ') . ')';
            }
        }

        if ($l === []) {
            $l[] = '';
            $l[] = '        // Nada pendente: todo usuário ativo tinha exatamente um papel.';
        }

        $l[] = '';

        return $l;
    }

    /**
     * O retrato do que existia, em comentário, logo abaixo do docblock: os
     * setores disponíveis (para acertar o nome), as permissões que ninguém
     * herda e as concessões diretas que já existem. Não é lido por nada — é
     * referência para quem preenche, e memória da decisão depois.
     *
     * @param  \Illuminate\Support\Collection<int,Sector>  $setores
     * @return string[]
     */
    private function retratoDoBanco($setores, array $pendencias): array
    {
        $l = [];

        if ($setores->isNotEmpty()) {
            $l[] = '// SETORES QUE JÁ EXISTEM (use o nome exato para reaproveitar):';
            foreach ($setores as $setor) {
                $coord = $setor->users->filter(fn($u) => $u->pivot->role === 'coordinator')->pluck('name')->all();
                $l[] = '//   · ' . $this->limpar($setor->name)
                     . ' — ' . $setor->users->count() . ' membro(s)'
                     . ($coord ? ', coordenado por ' . $this->limpar(implode(', ', $coord)) : ', SEM COORDENADOR');
            }
            $l[] = '';
        }

        if ($pendencias['orfas']->isNotEmpty()) {
            $l[] = '// PERMISSÕES QUE NÃO ESTÃO EM PAPEL NENHUM — nenhum setor herda estas por';
            $l[] = '// "copiar". Se alguma ainda for necessária, conceda à mão na tela do setor:';
            foreach ($pendencias['orfas']->chunk(6) as $pedaco) {
                $l[] = '//   ' . $pedaco->pluck('name')->implode(', ');
            }
            $l[] = '';
        }

        if ($pendencias['individuais']->isNotEmpty()) {
            $l[] = '// CONCESSÕES DIRETAS QUE JÁ EXISTEM — continuam valendo como permissão';
            $l[] = '// individual depois da reforma. Não precisam de de-para:';
            foreach ($pendencias['individuais'] as $u) {
                $l[] = '//   · ' . $u->email . ': ' . $u->permissions->pluck('name')->implode(', ');
            }
            $l[] = '';
        }

        if ($l !== []) {
            array_unshift($l, '', '// ---------------------------------------------------------------------------');
            $l[] = '// ---------------------------------------------------------------------------';
        }

        return $l;
    }

    /**
     * Uma lista longa quebrada em várias linhas de comentário, para o arquivo
     * continuar legível num editor de 100 colunas.
     *
     * @param  string[]  $itens
     * @return string[]
     */
    private function comentarioLista(string $rotulo, array $itens, ?int $limite = null): array
    {
        if ($itens === []) {
            return ['        // ' . $rotulo . ': nenhuma'];
        }

        $resto = 0;
        if ($limite !== null && count($itens) > $limite) {
            $resto = count($itens) - $limite;
            $itens = array_slice($itens, 0, $limite);
        }

        $l = [];
        $linha = '        // ' . $rotulo . ': ';
        $recuo = '        //   ';

        foreach ($itens as $item) {
            $item = $this->limpar($item);

            if (mb_strlen($linha) + mb_strlen($item) + 2 > 96) {
                $l[] = rtrim($linha);
                $linha = $recuo;
            }

            $linha .= $item . ', ';
        }

        $l[] = rtrim(rtrim($linha), ',');

        if ($resto > 0) {
            $l[] = $recuo . '... e mais ' . $resto . ' (veja a lista completa com --so-listar)';
        }

        return $l;
    }

    /** Nada de quebra de linha dentro de um comentário de uma linha só. */
    private function limpar(?string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $texto));
    }
}
