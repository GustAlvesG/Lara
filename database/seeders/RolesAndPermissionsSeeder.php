<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Limpar o cache de permissões do Spatie (evita bugs de permissões fantasmas)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Criar Permissões Básicas
        $permissions_infoclube = [
            [ 'name' => 'create information', 'description' => 'Permite criar informações no InfoClube'],
            [ 'name' => 'edit information', 'description' => 'Permite editar informações no InfoClube'],
            [ 'name' => 'delete information', 'description' => 'Permite deletar informações no InfoClube'],
            [ 'name' => 'publish information', 'description' => 'Permite publicar informações no InfoClube'],
            [ 'name' => 'view information', 'description' => 'Permite visualizar informações no InfoClube'],
        ];

        $permissions_siv = [
            [ 'name' => 'search parking', 'description' => 'Permite buscar registro no SIV']
        ];

        $permission_smart_panel = [
            [ 'name' => 'manage smart panel', 'description' => 'Permite gerenciar o Smart Panel'],
        ];

        $permission_reserve = [
            [ 'name' => 'create reservations', 'description' => 'Permite criar reservas'],
            [ 'name' => 'view reservations', 'description' => 'Permite visualizar reservas'],
            [ 'name' => 'edit reservations', 'description' => 'Permite editar reservas'],
            [ 'name' => 'delete reservations', 'description' => 'Permite deletar reservas'],
            [ 'name' => 'manage reservations-configs', 'description' => 'Permite gerenciar configurações de reservas'],
        ];

        $permission_admin = [
            [ 'name' => 'manage users', 'description' => 'Permite gerenciar usuários'],
            [ 'name' => 'manage roles', 'description' => 'Permite gerenciar funções'],
            [ 'name' => 'manage permissions', 'description' => 'Permite gerenciar permissões'],
        ];

        $permission_home_assistant = [
            [ 'name' => 'manage home assistant', 'description' => 'Permite gerenciar o painel Home Assistant'],
        ];

        // Permissão própria (e não "todo mundo logado") para liberar o chat da
        // Lara aos poucos: começa com um grupo pequeno e abre depois.
        $permission_lara = [
            [ 'name' => 'use lara chat', 'description' => 'Permite usar o chat com a Lara (assistente de IA)'],
        ];

        // Autorização de ordem de compra (integração com o Questor). Enquanto o
        // módulo só simula a gravação, esta permissão é de fato "ver a fila e
        // conferir a integração" — o mesmo grupo que vai aprovar de verdade.
        $permission_questor = [
            [ 'name' => 'authorize purchase orders', 'description' => 'Permite ver e autorizar ordens de compra do Questor'],
        ];

        // Mapa de cotação. As ações são separadas de propósito: quem monta o
        // mapa nem sempre é quem liga para os fornecedores, e decidir de quem
        // comprar não é a mesma coisa que anotar o preço que o fornecedor
        // falou. `reabrir` tem permissão própria porque devolve à edição um
        // documento que já fundamentou uma compra.
        //
        // ATENÇÃO: estas permissões NÃO bastam para entrar no módulo. O acesso
        // é do setor **Contabilidade**, conferido pelo Gate `acessar-cotacao`
        // (ver App\Policies\CotacaoMapaPolicy) — inclusive para a role `admin`,
        // que recebe todas as permissões logo abaixo e mesmo assim não entra
        // sem o vínculo de setor.
        $permission_cotacao = [
            [ 'name' => 'cotacao.visualizar', 'description' => 'Permite ver os mapas de cotação'],
            [ 'name' => 'cotacao.criar', 'description' => 'Permite gerar mapa a partir de uma solicitação e gerenciar itens e colunas'],
            [ 'name' => 'cotacao.editar_precos', 'description' => 'Permite digitar preços na grade do mapa de cotação'],
            [ 'name' => 'cotacao.definir_vencedor', 'description' => 'Permite escolher o fornecedor vencedor de cada item e fechar o mapa'],
            [ 'name' => 'cotacao.exportar', 'description' => 'Permite exportar o mapa de cotação em XLSX'],
            [ 'name' => 'cotacao.reabrir', 'description' => 'Permite reabrir um mapa de cotação já fechado'],
        ];

        // A portaria registra a quilometragem pela API (token próprio); esta
        // permissão é para as telas: painel, histórico e cadastro de veículos.
        $permission_fleet = [
            [ 'name' => 'manage fleet', 'description' => 'Permite gerenciar a frota e a quilometragem dos veículos'],
        ];

        $allPermissions = array_merge(
            $permissions_infoclube,
            $permissions_siv,
            $permission_smart_panel,
            $permission_reserve,
            $permission_admin,
            $permission_home_assistant,
            $permission_lara,
            $permission_questor,
            $permission_cotacao,
            $permission_fleet
        );

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], ['description' => $permission['description']]);
        }

        // 3. Criar Roles e atribuir Permissões
        $role_admin = Role::firstOrCreate(['name' => 'admin']);
        $role_admin->givePermissionTo(Permission::all());

        $role_secretaria = Role::firstOrCreate(['name' => 'secretaria']);
        //Get all "name" from array of permissions
        $permissions_secretaria = array_merge($permissions_infoclube, $permissions_siv, $permission_reserve);
        foreach ( $permissions_secretaria as $key => $value ) {
            $permissions_secretaria[$key] = $value['name'];
        }
        $role_secretaria->givePermissionTo($permissions_secretaria);

        $role_comercial = Role::firstOrCreate(['name' => 'comercial']);
        $role_comercial->givePermissionTo('create reservations',
            'view reservations',
            'edit reservations',
            'delete reservations',
            'view information'
        );

        $role_default = Role::firstOrCreate(['name' => 'user']);
        $role_default->givePermissionTo('view information');

        $user = User::find(1);
        if ($user) {
            $user->assignRole('admin');
        }

        $user_test = User::find(71);
        if ($user_test) {
            $user_test->assignRole('user');
        }
        $this->command->info('Seed concluído: Roles, Permissions e Usuário Admin criados!');
    }
}
