<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * As quatro permissões do módulo de assinatura.
 *
 * São quatro, e não uma, porque são quatro acessos com peso diferente:
 *
 *  - escrever o TEXTO de um termo é um ato jurídico, e quem atende no balcão
 *    não precisa disso;
 *  - liberar um documento para assinatura é a operação do dia a dia;
 *  - ler um documento assinado é consulta, e muita gente precisa;
 *  - ver a FOTO e o traço de uma pessoa é dado pessoal sensível, e essa é a
 *    permissão que deve ser a mais rara das quatro.
 *
 * Todas nascem só no papel `admin`. Quem mais precisar recebe pela tela de
 * permissões — o mesmo caminho de `manage freelancer payments`.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'name' => 'manage signature templates',
            'description' => 'Permite criar e revisar os modelos de documento da assinatura eletrônica',
        ],
        [
            'name' => 'manage signature documents',
            'description' => 'Permite criar documentos e liberá-los para assinatura no tablet',
        ],
        [
            'name' => 'view signed documents',
            'description' => 'Permite consultar documentos assinados e baixar o PDF',
        ],
        [
            'name' => 'view signature evidences',
            'description' => 'Permite ver as evidências da assinatura (foto do signatário e traço)',
        ],
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::where('name', 'admin')->first();

        foreach (self::PERMISSIONS as $permission) {
            $created = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['description' => $permission['description']],
            );

            $admin?->givePermissionTo($created);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', array_column(self::PERMISSIONS, 'name'))->delete();
    }
};
