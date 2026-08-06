<?php

namespace App\Services\Placar;

use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\JogoEvento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * POST /jogos/{jogo}/eventos — o endpoint mais importante da API. Recebe um
 * lote, valida cada evento individualmente (um inválido não derruba os
 * demais) e grava com idempotência por `uuid`.
 *
 * A idempotência é resolvida tentando o INSERT e capturando a violação de
 * unique, não pré-checando existência — cobre também a corrida rara de dois
 * envios do mesmo lote quase simultâneos. Isso só é seguro porque o projeto
 * roda em MySQL/InnoDB: uma duplicate-key error numa instrução dentro de uma
 * transação não a envenena (diferente de Postgres) — dá para continuar
 * inserindo as próximas linhas na mesma transação depois de capturar o erro.
 */
class JogoEventoLoteService
{
    const LIMITE_LOTE = 200;

    /**
     * @return array{aceitos: string[], duplicados: string[], rejeitados: array<array{uuid: ?string, motivo: string}>}
     */
    public function processar(Jogo $jogo, array $eventosBrutos): array
    {
        $eventosBrutos = array_slice($eventosBrutos, 0, self::LIMITE_LOTE);

        $jogadoresValidos = $this->carregarJogadoresCitados($eventosBrutos);

        $aceitos = [];
        $duplicados = [];
        $rejeitados = [];

        DB::transaction(function () use ($jogo, $eventosBrutos, $jogadoresValidos, &$aceitos, &$duplicados, &$rejeitados) {
            foreach ($eventosBrutos as $bruto) {
                $uuid = is_array($bruto) ? ($bruto['uuid'] ?? null) : null;

                $motivo = $this->validar($jogo, $bruto, $jogadoresValidos);
                if ($motivo !== null) {
                    $rejeitados[] = ['uuid' => is_string($uuid) ? $uuid : null, 'motivo' => $motivo];
                    continue;
                }

                try {
                    JogoEvento::create($this->paraInsercao($jogo, $bruto));
                    $aceitos[] = $uuid;
                } catch (QueryException $e) {
                    if ($this->violacaoDeUuid($e)) {
                        // Já existia (envio anterior, ou repetido dentro deste
                        // mesmo lote) — idempotência, não é erro.
                        $duplicados[] = $uuid;
                    } elseif ($this->violacaoDeSequencia($e)) {
                        $rejeitados[] = ['uuid' => $uuid, 'motivo' => 'sequência já registrada com outro uuid'];
                    } else {
                        throw $e;
                    }
                }
            }
        });

        if ($this->afetaPlacar($aceitos, $eventosBrutos)) {
            $placar = $jogo->calcularPlacar();
            $jogo->update($placar);
        }

        return ['aceitos' => $aceitos, 'duplicados' => $duplicados, 'rejeitados' => $rejeitados];
    }

    /**
     * Só recalcula o cache do placar se o lote trouxe algo que o afeta —
     * evita um UPDATE em `jogos` a cada lote que só tem crono_play/timeout.
     */
    private function afetaPlacar(array $aceitos, array $eventosBrutos): bool
    {
        if ($aceitos === []) {
            return false;
        }

        $tiposQueAfetam = [JogoEvento::TIPO_PONTO, JogoEvento::TIPO_SET, JogoEvento::TIPO_ESTORNO];

        foreach ($eventosBrutos as $bruto) {
            if (is_array($bruto) && in_array($bruto['tipo'] ?? null, $tiposQueAfetam, true)) {
                return true;
            }
        }

        return false;
    }

    /** Uma query só para todos os jogador_id citados no lote, não uma por item. */
    private function carregarJogadoresCitados(array $eventosBrutos)
    {
        $ids = collect($eventosBrutos)
            ->filter(fn ($e) => is_array($e))
            ->pluck('jogador_id')
            ->filter()
            ->unique()
            ->values();

        return $ids->isEmpty() ? collect() : Jogador::whereIn('id', $ids)->pluck('id')->flip();
    }

    private function validar(Jogo $jogo, mixed $bruto, $jogadoresValidos): ?string
    {
        if (!is_array($bruto)) {
            return 'evento deve ser um objeto';
        }

        $uuid = $bruto['uuid'] ?? null;
        if (!is_string($uuid) || !Str::isUuid($uuid)) {
            return 'uuid ausente ou inválido';
        }

        $sequencia = $bruto['sequencia'] ?? null;
        if (!is_int($sequencia) && !(is_string($sequencia) && ctype_digit($sequencia))) {
            return 'sequencia ausente ou inválida';
        }

        $tipo = $bruto['tipo'] ?? null;
        if (!in_array($tipo, JogoEvento::TIPOS, true)) {
            return "tipo '{$tipo}' desconhecido";
        }

        $ocorridoEm = $bruto['ocorrido_em'] ?? null;
        if (!is_string($ocorridoEm) || !$this->dataValida($ocorridoEm)) {
            return 'ocorrido_em ausente ou inválido';
        }

        $timeId = $bruto['time_id'] ?? null;
        if ($timeId !== null && !in_array((int) $timeId, [$jogo->time_casa_id, $jogo->time_fora_id], true)) {
            return 'time_id não pertence a este jogo';
        }

        $jogadorId = $bruto['jogador_id'] ?? null;
        if ($jogadorId !== null && !isset($jogadoresValidos[(int) $jogadorId])) {
            return 'jogador_id inexistente';
        }

        $modalidadeSlug = $jogo->modalidade->slug;

        if ($tipo === JogoEvento::TIPO_SET && !ModalidadeRegras::permiteSet($modalidadeSlug)) {
            return "tipo 'set' só é válido em vôlei";
        }

        if ($tipo === JogoEvento::TIPO_FALTA && !ModalidadeRegras::permiteFalta($modalidadeSlug)) {
            return "modalidade '{$modalidadeSlug}' não tem 'falta'";
        }

        if ($tipo === JogoEvento::TIPO_PONTO) {
            $valor = $bruto['valor'] ?? null;
            $valorInt = is_numeric($valor) ? (int) $valor : null;
            if (!ModalidadeRegras::valorValidoDePonto($modalidadeSlug, $valorInt)) {
                return "valor de ponto inválido para '{$modalidadeSlug}'";
            }
        }

        return null;
    }

    private function dataValida(string $valor): bool
    {
        try {
            Carbon::parse($valor);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function paraInsercao(Jogo $jogo, array $bruto): array
    {
        return [
            'uuid' => $bruto['uuid'],
            'jogo_id' => $jogo->id,
            'sequencia' => (int) $bruto['sequencia'],
            'tipo' => $bruto['tipo'],
            'time_id' => isset($bruto['time_id']) ? (int) $bruto['time_id'] : null,
            'jogador_id' => isset($bruto['jogador_id']) ? (int) $bruto['jogador_id'] : null,
            'valor' => isset($bruto['valor']) ? (int) $bruto['valor'] : null,
            'periodo' => isset($bruto['periodo']) ? (int) $bruto['periodo'] : null,
            'cronometro_ms' => isset($bruto['cronometro_ms']) ? (int) $bruto['cronometro_ms'] : null,
            'ocorrido_em' => Carbon::parse($bruto['ocorrido_em']),
            'payload' => $bruto['payload'] ?? null,
        ];
    }

    /**
     * A mensagem de unique-violation não tem o mesmo formato entre drivers —
     * MySQL nomeia a constraint (`jogo_eventos_uuid_unique`), SQLite lista a
     * coluna (`jogo_eventos.uuid`). Em vez de casar o nome inteiro da
     * constraint (frágil entre drivers), basta procurar o nome da coluna que
     * só aparece numa das duas: `uuid` nunca aparece na mensagem da
     * constraint composta, e `sequencia` nunca aparece na da simples.
     */
    private function violacaoDeUuid(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'uuid');
    }

    private function violacaoDeSequencia(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'sequencia');
    }
}
