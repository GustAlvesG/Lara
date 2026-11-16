<?php

namespace App\Services\Parking;

/**
 * SIV — "Condutores mais prováveis" da busca de placa.
 *
 * Recebe, para cada uma das últimas leituras da placa, a lista de pessoas
 * ligadas a ela pelo horário (catraca do sócio ou portaria do externo) e
 * devolve todas as pessoas encontradas, da mais frequente para a menos.
 *
 * Duas medidas por pessoa:
 *
 *   - `share`: a fatia dela no total de aparições, em %. Soma 100 entre
 *     todas as pessoas. É o número grande da tela.
 *   - `readings`: em quantas leituras ela apareceu (de `total_readings`).
 *
 * Até 16/11/2026 o controller dividia as aparições pelo número de PESSOAS
 * DIFERENTES, o que dava percentuais acima de 100% (8 aparições ÷ 2 pessoas
 * = 400%). O divisor certo é o total de aparições.
 *
 * Uma pessoa conta no máximo uma vez por leitura: a catraca às vezes registra
 * o mesmo sócio duas vezes nos mesmos segundos, e isso não é passar duas vezes.
 */
class LikelyDrivers
{
    /**
     * @param  array<int, array<int, array{name: ?string, telephone?: ?string, label?: ?string}>>  $readings
     *         uma entrada por leitura da câmera; cada uma com as pessoas ligadas a ela.
     * @return array{total_readings: int, total_appearances: int, people: array<int, array{name: string, telephone: string, label: string, readings: int, share: float}>}
     */
    public static function rank(array $readings): array
    {
        $people = [];
        $totalAppearances = 0;

        foreach ($readings as $candidates) {
            $seen = [];
            foreach ($candidates as $candidate) {
                $name = trim((string) ($candidate['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $telephone = trim((string) ($candidate['telephone'] ?? ''));
                $label = trim((string) ($candidate['label'] ?? ''));

                // Mesma pessoa = mesmo nome, telefone e tipo (sócio sem tipo).
                $key = mb_strtoupper($name) . '|' . $telephone . '|' . $label;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $people[$key] ??= ['name' => $name, 'telephone' => $telephone, 'label' => $label, 'readings' => 0];
                $people[$key]['readings']++;
                $totalAppearances++;
            }
        }

        $rows = array_values(array_map(function (array $person) use ($totalAppearances) {
            $person['share'] = $totalAppearances > 0
                ? round($person['readings'] / $totalAppearances * 100, 1)
                : 0.0;

            return $person;
        }, $people));

        // Mais aparições primeiro; empate por nome, para a ordem não variar entre buscas.
        usort($rows, fn (array $a, array $b) => [$b['readings'], $a['name']] <=> [$a['readings'], $b['name']]);

        return [
            'total_readings' => count($readings),
            'total_appearances' => $totalAppearances,
            'people' => $rows,
        ];
    }
}
