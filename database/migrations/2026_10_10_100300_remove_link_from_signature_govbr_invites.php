<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tira o link de devolução do convite do gov.br: o Lara não é acessível de
     * fora, e a pessoa devolve o arquivo ao atendente.
     *
     * Só faz algo onde a primeira versão de `2026_10_10_100200` rodou
     * (homologação): lá a tabela tem as colunas do link e a trilha tem o valor
     * `signer`. Num banco novo, a 100200 já cria a tabela sem elas, e esta não
     * muda nada.
     */
    public function up(): void
    {
        $colunas = array_values(array_filter(
            ['token_hash', 'expires_at', 'revoked_at', 'last_opened_at'],
            fn(string $coluna) => Schema::hasColumn('signature_govbr_invites', $coluna),
        ));

        if ($colunas !== []) {
            Schema::table('signature_govbr_invites', function (Blueprint $table) use ($colunas) {
                if (in_array('token_hash', $colunas, true)) {
                    $table->dropUnique(['token_hash']);
                }

                $table->dropColumn($colunas);
            });
        }

        // O valor `signer` da trilha sai só se nenhum evento o usa: reescrever a
        // trilha para caber no enum antigo não é opção.
        if (
            DB::getDriverName() === 'mysql'
            && str_contains($this->actorTypeDefinition(), "'signer'")
            && !DB::table('signature_audit_events')->where('actor_type', 'signer')->exists()
        ) {
            Schema::table('signature_audit_events', function (Blueprint $table) {
                $table->enum('actor_type', ['user', 'kiosk', 'system'])->default('system')->change();
            });
        }
    }

    /** Sem volta: o link foi retirado do processo. */
    public function down(): void
    {
    }

    private function actorTypeDefinition(): string
    {
        return (string) DB::selectOne(
            "select COLUMN_TYPE as tipo from information_schema.COLUMNS
              where TABLE_SCHEMA = database() and TABLE_NAME = 'signature_audit_events' and COLUMN_NAME = 'actor_type'"
        )?->tipo;
    }
};
