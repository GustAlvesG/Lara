<?php

namespace Tests\Concerns;

/**
 * Cria as tabelas do mapa de cotação no SQLite da suíte.
 *
 * Sem `RefreshDatabase` pelo mesmo motivo já registrado em
 * {@see CreatesFreelancerPixSchema}: a cadeia completa de migrations não roda
 * hoje (`add_columns_member` e `tourments` adicionam `members.title` as duas),
 * e várias delas dependem de `users`, cuja model fixa a conexão `mysql`.
 *
 * Aqui as migrations aplicadas são AS DE VERDADE, lidas do arquivo — não uma
 * cópia do schema. É de propósito: uma cópia deixaria de acusar divergência no
 * dia em que uma coluna mudar. As cinco rodam sem adaptação porque nenhuma
 * delas declara foreign key para `users` (o vínculo com o autor é
 * `unsignedBigInteger` solto, pelo mesmo motivo de `questor_order_decisions`).
 *
 * A ordem importa: `cotacao_mapa_itens` tem FK para `cotacao_mapa_fornecedores`
 * (a coluna `vencedor_id`).
 */
trait CreatesCotacaoSchema
{
    protected function createCotacaoSchema(): void
    {
        $migrations = [
            '2026_09_03_100100_create_cotacao_mapas_table.php',
            '2026_09_03_100200_create_cotacao_mapa_fornecedores_table.php',
            '2026_09_03_100300_create_cotacao_mapa_itens_table.php',
            '2026_09_03_100400_create_cotacao_precos_table.php',
            '2026_09_03_100500_create_cotacao_mapa_logs_table.php',
        ];

        foreach ($migrations as $arquivo) {
            (require base_path('database/migrations/' . $arquivo))->up();
        }
    }
}
