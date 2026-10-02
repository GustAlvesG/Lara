<?php

namespace Database\Seeders;

use App\Authorization\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * O seed PADRÃO de permissões: roda em todo deploy.
 *
 *   php artisan db:seed --class=PermissionCatalogSeeder --force
 *
 * Espelha o catálogo (`App\Authorization\Permissions`) na tabela
 * `permissions`, para que toda permissão declarada no código exista no banco
 * e possa ser dada a um setor na tela de Setores, ou a uma pessoa na tela de
 * Usuários.
 *
 * Para um módulo novo, a regra é uma só: declarar a permissão no catálogo
 * (constante + linha em CATALOG). O deploy seguinte a cria aqui — não é
 * preciso escrever uma migration por módulo.
 *
 * O que este seed faz, e só isto:
 *   - cria a permissão do catálogo que ainda não existe no banco;
 *   - acerta a descrição da que já existe, se o rótulo mudou no catálogo.
 *
 * O que ele NÃO faz, de propósito:
 *   - não dá permissão a setor nem a pessoa: ela nasce solta, e quem decide
 *     quem a recebe é a tela (os setores de acesso total a alcançam por
 *     definição — ver AccessResolver);
 *   - não mexe em `sector_permission` nem em `model_has_permissions`: o que já
 *     foi configurado fica como está;
 *   - não apaga permissão que saiu do catálogo — isso é do comando
 *     `acesso:limpar-legado`, rodado por alguém, e não a cada deploy.
 *
 * Pode rodar quantas vezes for: na segunda, não muda nada.
 */
class PermissionCatalogSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $temDescricao = Schema::hasColumn('permissions', 'description');
        $agora = now();

        $existentes = DB::table('permissions')
            ->where('guard_name', self::GUARD)
            ->whereIn('name', Permissions::all())
            ->get()
            ->keyBy('name');

        $criadas = [];
        $acertadas = 0;

        foreach (Permissions::all() as $nome) {
            $descricao = Permissions::group($nome) . ' — ' . Permissions::label($nome);
            $existente = $existentes->get($nome);

            if (! $existente) {
                DB::table('permissions')->insert(array_filter([
                    'name' => $nome,
                    'guard_name' => self::GUARD,
                    'description' => $temDescricao ? $descricao : null,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ], fn ($valor) => $valor !== null));

                $criadas[] = $nome;

                continue;
            }

            if ($temDescricao && $existente->description !== $descricao) {
                DB::table('permissions')->where('id', $existente->id)->update([
                    'description' => $descricao,
                    'updated_at' => $agora,
                ]);

                $acertadas++;
            }
        }

        // O Spatie guarda a lista de permissões em cache por 24h.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($criadas) {
            $this->command?->info('Permissões criadas (sem setor — configure em Setores): ' . implode(', ', $criadas));
        }

        if ($acertadas) {
            $this->command?->info("Descrições acertadas: {$acertadas}.");
        }

        if (! $criadas && ! $acertadas) {
            $this->command?->info('Catálogo de permissões já em dia (' . count(Permissions::all()) . ').');
        }
    }
}
