<?php

namespace App\Imports;

use App\Exceptions\ImportRowException;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\CategoriaService;
use Illuminate\Support\Str;

/**
 * Cadastro em massa de jogadores do Placar Clube a partir do modelo .xlsx.
 *
 * A planilha faz as duas coisas de uma vez: cadastra o jogador (que
 * pertence a UMA equipe e UMA modalidade) e, quando a coluna "Categoria do
 * time" está preenchida, já o coloca no elenco daquele time. Deixando as
 * colunas de vínculo em branco, só cadastra.
 *
 * Equipe e modalidade são informadas por NOME/slug, não por id — quem
 * preenche a planilha não conhece os ids do banco.
 *
 * Imagem e vídeo ficam de fora de propósito: são upload, não célula de
 * planilha, e continuam sendo enviados pela ficha do jogador.
 */
class JogadorImport extends SpreadsheetImport
{
    /** Equipes/modalidades/times já resolvidos, para não consultar por linha. */
    private array $cacheEquipes = [];
    private array $cacheModalidades = [];

    /** "documento" já visto no arquivo => linha em que apareceu. */
    private array $documentosVistos = [];

    public function columns(): array
    {
        return [
            'nome' => 'Nome *',
            'equipe' => 'Equipe *',
            'modalidade' => 'Modalidade *',
            'nome_exibicao' => 'Nome no telão',
            'data_nascimento' => 'Data de nascimento',
            'documento' => 'Documento',
            'categoria' => 'Categoria do time',
            'numero' => 'Número da camisa',
            'posicao' => 'Posição',
            'temporada' => 'Temporada',
        ];
    }

    public function aliases(): array
    {
        return [
            'nome' => 'nome',
            'name' => 'nome',
            'equipe' => 'equipe',
            'modalidade' => 'modalidade',
            'esporte' => 'modalidade',
            'nome_no_telao' => 'nome_exibicao',
            'nome_exibicao' => 'nome_exibicao',
            'apelido' => 'nome_exibicao',
            'data_de_nascimento' => 'data_nascimento',
            'data_nascimento' => 'data_nascimento',
            'nascimento' => 'data_nascimento',
            'documento' => 'documento',
            'rg' => 'documento',
            'cpf' => 'documento',
            'categoria_do_time' => 'categoria',
            'categoria' => 'categoria',
            'time' => 'categoria',
            'numero_da_camisa' => 'numero',
            'numero' => 'numero',
            'camisa' => 'numero',
            'posicao' => 'posicao',
            'temporada' => 'temporada',
            'ano' => 'temporada',
        ];
    }

    public function example(): array
    {
        return [
            'nome' => 'Carlos Souza',
            'equipe' => 'Clube dos Funcionários',
            'modalidade' => 'futsal',
            'nome_exibicao' => 'Carlinhos',
            'data_nascimento' => '15/03/2004',
            'documento' => 'MG1234567',
            'categoria' => 'Adulto',
            'numero' => '10',
            'posicao' => 'Ala',
            'temporada' => (string) now()->year,
        ];
    }

    public function templateName(): string
    {
        return 'modelo-importacao-jogadores.xlsx';
    }

    protected function textColumns(): array
    {
        // Número da camisa é texto: "07" não pode virar 7, e documento
        // perderia zeros à esquerda se o Excel tratasse como número.
        return ['documento', 'numero', 'temporada'];
    }

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'equipe_id' => ['required', 'integer', 'exists:equipes,id'],
            'modalidade_id' => ['required', 'integer', 'exists:modalidades,id'],
            'nome_exibicao' => ['nullable', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date'],
            'documento' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:10'],
            'posicao' => ['nullable', 'string', 'max:255'],
            'temporada' => ['nullable', 'integer', 'min:1900', 'max:2200'],
        ];
    }

    protected function attributes(): array
    {
        return [
            'equipe_id' => 'equipe',
            'modalidade_id' => 'modalidade',
            'nome_exibicao' => 'nome no telão',
            'data_nascimento' => 'data de nascimento',
            'numero' => 'número da camisa',
            'posicao' => 'posição',
        ];
    }

    /**
     * @throws ImportRowException
     */
    protected function prepare(array $row, int $line): array
    {
        $nomeEquipe = trim((string) ($row['equipe'] ?? ''));
        $modalidade = trim((string) ($row['modalidade'] ?? ''));

        if ($nomeEquipe === '') {
            throw new ImportRowException('Equipe não informada.');
        }

        if ($modalidade === '') {
            throw new ImportRowException('Modalidade não informada.');
        }

        $equipe = $this->resolverEquipe($nomeEquipe);
        if (!$equipe) {
            throw new ImportRowException("Equipe \"{$nomeEquipe}\" não existe. Cadastre-a antes de importar.");
        }

        $modalidadeResolvida = $this->resolverModalidade($modalidade);
        if (!$modalidadeResolvida) {
            throw new ImportRowException("Modalidade \"{$modalidade}\" não existe — use futsal, basquete ou volei.");
        }

        $documento = trim((string) ($row['documento'] ?? ''));

        // `unique` no banco não pega repetição dentro do próprio arquivo.
        if ($documento !== '') {
            $chave = mb_strtolower($documento);

            if (isset($this->documentosVistos[$chave])) {
                throw new ImportRowException(
                    "Documento {$documento} repetido (já informado na linha {$this->documentosVistos[$chave]})."
                );
            }

            $this->documentosVistos[$chave] = $line;
        }

        $nascimento = ImportValues::date((string) ($row['data_nascimento'] ?? ''), 'data de nascimento');

        return [
            'nome' => trim((string) ($row['nome'] ?? '')),
            'equipe_id' => $equipe->id,
            'modalidade_id' => $modalidadeResolvida->id,
            'nome_exibicao' => blank($row['nome_exibicao'] ?? '') ? null : trim((string) $row['nome_exibicao']),
            'data_nascimento' => $nascimento === '' ? null : $nascimento,
            'documento' => $documento === '' ? null : $documento,
            'categoria' => blank($row['categoria'] ?? '') ? null : trim((string) $row['categoria']),
            'numero' => blank($row['numero'] ?? '') ? null : trim((string) $row['numero']),
            'posicao' => blank($row['posicao'] ?? '') ? null : trim((string) $row['posicao']),
            'temporada' => blank($row['temporada'] ?? '') ? null : (int) $row['temporada'],
        ];
    }

    /**
     * Só faz sentido pedir número/posição se a linha disser em qual time o
     * jogador entra — senão o dado seria descartado em silêncio.
     */
    protected function validateRow(array $data): array
    {
        $erros = [];

        if (blank($data['categoria'] ?? null)) {
            foreach (['numero' => 'número da camisa', 'posicao' => 'posição'] as $campo => $rotulo) {
                if (filled($data[$campo] ?? null)) {
                    $erros[] = "Preencha a categoria do time para informar {$rotulo}.";
                }
            }
        }

        return $erros;
    }

    protected function persist(array $data)
    {
        $jogador = Jogador::create([
            'equipe_id' => $data['equipe_id'],
            'modalidade_id' => $data['modalidade_id'],
            'nome' => $data['nome'],
            'nome_exibicao' => $data['nome_exibicao'],
            'data_nascimento' => $data['data_nascimento'],
            'documento' => $data['documento'],
            'criado_em_campo' => false,
            'ativo' => true,
        ]);

        if (filled($data['categoria'] ?? null)) {
            $this->vincularAoElenco($jogador, $data);
        }

        return $jogador;
    }

    /**
     * O time é criado se ainda não existir para aquela equipe/modalidade/
     * categoria — importar um elenco inteiro não deveria exigir cadastrar o
     * time antes, à mão. A categoria passa pelo CategoriaService, então
     * "Sub 15" na planilha cai no "Sub-15" que já existe.
     */
    private function vincularAoElenco(Jogador $jogador, array $data): void
    {
        $categoria = CategoriaService::resolver($data['categoria']);

        $time = Time::firstOrCreate(
            [
                'equipe_id' => $data['equipe_id'],
                'modalidade_id' => $data['modalidade_id'],
                'categoria' => $categoria,
            ],
            ['criado_em_campo' => false, 'ativo' => true],
        );

        Elenco::updateOrCreate(
            [
                'time_id' => $time->id,
                'jogador_id' => $jogador->id,
                'temporada' => $data['temporada'] ?? now()->year,
            ],
            [
                'numero' => $data['numero'],
                'posicao' => $data['posicao'],
                'ativo' => true,
            ],
        );
    }

    private function resolverEquipe(string $nome): ?Equipe
    {
        $chave = mb_strtolower(trim($nome));

        return $this->cacheEquipes[$chave] ??= Equipe::whereRaw('LOWER(nome) = ?', [$chave])
            ->orWhereRaw('LOWER(nome_curto) = ?', [$chave])
            ->first();
    }

    private function resolverModalidade(string $valor): ?Modalidade
    {
        $chave = Str::ascii(mb_strtolower(trim($valor)));

        return $this->cacheModalidades[$chave] ??= Modalidade::resolver($chave)
            ?? Modalidade::whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($valor))])->first();
    }
}
