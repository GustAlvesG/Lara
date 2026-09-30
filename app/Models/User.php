<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\DataInfo;
use App\Models\Information;
use App\Authorization\AccessResolver;
use App\Authorization\Permissions;
use App\Authorization\UserAccess;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission;


/**
 * Acesso: setores (com o papel em cada um), permissões individuais e setores
 * de acesso total — ver access() e App\Authorization\AccessResolver. As roles
 * do Spatie saíram; do pacote sobram só as tabelas de permissão.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;
    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

     protected $connection = 'mysql';

    /**
     * Setor cujo coordenador aprova os lotes de contratos antes da diretoria.
     * Criado pela migration `create_gerencia_sector`.
     */
    public const MANAGEMENT_SECTOR = 'Gerência';

    /**
     * O Financeiro. O coordenador dele é o nível 1 da ordem de compra e quem
     * reabre mapa de cotação fechado — regras de cargo. O acesso aos módulos
     * (Compras, Financeiro dos freelancers, Pagamentos) é permissão do setor,
     * editável na tela de Setores.
     * Criado pela migration `create_contabilidade_sector`.
     */
    public const ACCOUNTING_SECTOR = 'Contabilidade';

    /**
     * Setor que registra os contratos de freelancer. Coordenar o setor dá
     * poderes de cargo (validar contrato, assinar como contraparte no kiosk,
     * liberar o limite semanal); o acesso às telas vem das permissões do setor.
     */
    public const COMMERCIAL_SECTOR = 'Comercial';

    /**
     * Setor do terceiro nível da aprovação de ordem de compra. Estar nele não
     * dá poder de aprovar nada sozinho: quem decide uma ordem são os membros
     * ligados ao centro de custo dela, ou os que a Gerência escolher.
     * Criado pela migration `create_diretoria_sector`.
     */
    public const DIRECTORS_SECTOR = 'Diretoria';

    /**
     * Setor do módulo Placar Clube. O acesso é pelas permissões
     * `placar.cadastro` e `placar.scout`, que o setor recebe na matriz inicial.
     */
    public const SPORT_SECTOR = 'Esporte';

    /**
     * Setor que responde pelo Banco de Horas. O acesso de administrador é a
     * permissão `banco-horas.admin` (ver canManageCompTime()), que o setor
     * recebe na matriz inicial.
     */
    public const HR_SECTOR = 'RH';

    protected $fillable = [
        'name',
        'email',
        'password',
        'pin',
        'cpf',
        'matricula',
        'phone',
        'last_login_at',
        'status_id',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'pin',
        // Nunca serializar a senha de aprovação — ela atravessa a DMZ na ida,
        // e o que volta para o Next é sempre um usuário serializado.
        'approval_password',
        'remember_token',
    ];

    /** Cache da requisição para access(). */
    private ?UserAccess $resolvedAccess = null;

    /** Cache da requisição para canViewCompTime(). */
    private ?bool $compTimeVisibility = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'approval_password' => 'hashed',
            'last_login_at' => 'datetime', // Isso permite usar Carbon no campo
        ];
    }

    /** O usuário definiu um PIN de assinatura (Kiosk)? */
    public function hasPin(): bool
    {
        return filled($this->pin);
    }

    /** Confere o PIN informado contra o hash guardado. */
    public function checkPin(?string $pin): bool
    {
        return $this->hasPin() && filled($pin) && \Illuminate\Support\Facades\Hash::check($pin, $this->pin);
    }

    /**
     * O usuário definiu a senha de aprovação de ordem de compra?
     *
     * É outra senha, não a do painel — ver a migration
     * `add_approval_password_and_phone_to_users_table`. Sem ela definida, o
     * aprovador não entra no site externo, mesmo tendo login aqui dentro.
     */
    public function hasApprovalPassword(): bool
    {
        return filled($this->approval_password);
    }

    /** Confere a senha de aprovação informada contra o hash guardado. */
    public function checkApprovalPassword(?string $password): bool
    {
        return $this->hasApprovalPassword()
            && filled($password)
            && \Illuminate\Support\Facades\Hash::check($password, $this->approval_password);
    }

    /** Membro do setor Diretoria, em qualquer papel. */
    public function isDirector(): bool
    {
        return $this->belongsToSectorNamed(self::DIRECTORS_SECTOR);
    }

    /**
     * Coordenador da Contabilidade — o primeiro nível da aprovação de ordem de
     * compra. Como no caso da Gerência, é um cargo e não um nível de acesso: a
     * role `admin` não substitui.
     */
    public function isAccountingCoordinator(): bool
    {
        return $this->isCoordinatorOfSectorNamed(self::ACCOUNTING_SECTOR);
    }

    /**
     * Os membros do setor Diretoria — o universo de quem pode ser escolhido
     * como aprovador de um centro de custo. Ordenado por nome porque o destino
     * é sempre uma lista de seleção.
     *
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public static function directors()
    {
        return self::query()
            ->whereHas('sectors', fn($q) => $q->whereRaw('LOWER(sectors.name) = ?', [
                mb_strtolower(self::DIRECTORS_SECTOR),
            ]))
            ->orderBy('name');
    }

    //Relacionamento de um para muitos with data_info
    public function data_info()
    {
        return $this->hasMany(DataInfo::class, 'created_by');
    }

    //Relacionamento de um para muitos com information
    public function information()
    {
        return $this->hasMany(Information::class, 'created_by');
    }

    //Status Has ONE
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function schedulesCreated()
    {
        return $this->hasMany(Schedule::class, 'created_by_user', 'id');
    }

    public function schedulesUpdated()
    {
        return $this->hasMany(Schedule::class, 'updated_by_user', 'id');
    }

    public function sectors()
    {
        return $this->belongsToMany(Sector::class, 'user_sector')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isCoordinatorOf(Sector $sector): bool
    {
        return $this->sectors()
            ->wherePivot('sector_id', $sector->id)
            ->wherePivot('role', 'coordinator')
            ->exists();
    }

    /** Coordenador de ao menos um setor. */
    public function isCoordinator(): bool
    {
        return $this->sectors()->wherePivot('role', 'coordinator')->exists();
    }

    /**
     * Coordenador de um setor específico, identificado pelo nome (sem diferenciar
     * maiúsculas/acentuação de digitação). Usado pelo Kiosk, que só libera a
     * assinatura do coordenador para o setor Comercial.
     */
    public function isCoordinatorOfSectorNamed(string $name): bool
    {
        return $this->sectors()
            ->wherePivot('role', 'coordinator')
            ->whereRaw('LOWER(sectors.name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    /**
     * Coordenador do setor **Gerência** — quem aprova o lote de contratos antes
     * de ele seguir para a diretoria, e quem depois digita o PIN que o diretor
     * ditou.
     *
     * É um cargo, não um nível de acesso: a role `admin` do Spatie não vale
     * mais aqui. Administrar o sistema e responder pela aprovação do lote são
     * coisas diferentes, e quem responde pelo lote é uma pessoa só.
     */
    public function isManagementCoordinator(): bool
    {
        return $this->isCoordinatorOfSectorNamed(self::MANAGEMENT_SECTOR);
    }

    /**
     * Vinculado a um setor pelo nome, **em qualquer papel** — colaborador ou
     * coordenador. Diferente de isCoordinatorOfSectorNamed(), que só enxerga
     * quem responde pelo setor.
     */
    public function belongsToSectorNamed(string $name): bool
    {
        return $this->sectors()
            ->whereRaw('LOWER(sectors.name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    /**
     * Acesso efetivo desta requisição — setores de acesso total, permissões
     * dos setores e permissões individuais. Ver App\Authorization\AccessResolver.
     *
     * Memorizado por instância: o menu, as abas, o middleware e a policy
     * perguntam várias vezes na mesma requisição, e cada requisição reconfere
     * — tirar o vínculo no painel corta o acesso no clique seguinte.
     *
     * É o ponto que os testes interceptam quando mockam o User (ver
     * tests/Concerns/MocksPlacarUser): o model está preso à conexão mysql, e
     * a consulta de verdade não pode rodar na suíte.
     */
    public function access(): UserAccess
    {
        return $this->resolvedAccess ??= app(AccessResolver::class)->resolve($this);
    }

    /** Esquece o acesso calculado — para quem acabou de mudar o próprio vínculo. */
    public function forgetAccess(): void
    {
        $this->resolvedAccess = null;
        $this->compTimeVisibility = null;
    }

    /** Membro de um setor com acesso total (Gerência, Diretoria, TI). */
    public function hasFullAccess(): bool
    {
        return $this->access()->hasFullAccess();
    }

    /**
     * Alcança a permissão do catálogo? Prefira `can()` em rota e view: é o
     * mesmo teste, e passa pelo Gate::before do AppServiceProvider.
     */
    public function hasAccess(string $permission): bool
    {
        return $this->access()->allows($permission);
    }

    /**
     * Permissões individuais — dadas direto ao usuário, fora de setor. Mora
     * na `model_has_permissions` do Spatie; quem lê é o AccessResolver.
     */
    public function directPermissions()
    {
        return $this->morphToMany(Permission::class, 'model', 'model_has_permissions', 'model_id', 'permission_id');
    }

    /**
     * Banco de Horas em modo administrador: importar o espelho de ponto, ver
     * **todos** os funcionários e mexer no cadastro (férias, afastamento,
     * rescisão). É a permissão `banco-horas.admin` — o setor RH a recebe na
     * matriz inicial, e ela pode ser dada a uma pessoa específica na tela de
     * Usuários.
     */
    public function canManageCompTime(): bool
    {
        return $this->hasAccess(Permissions::BANCO_HORAS_ADMIN);
    }

    /**
     * Tem alguma coisa para ver no Banco de Horas.
     *
     * São três públicos e basta um: o RH (vê todos), o coordenador de qualquer
     * setor (vê a equipe dele) e quem tem matrícula (vê a própria ficha). Quem
     * não é nada disso abriria a tela vazia — ver
     * CompTimeService::accessFor(), que faz o recorte de verdade.
     *
     * Serve ao menu, pelo Gate `view-comp-time`: a navegação é renderizada
     * por praticamente toda tela do app e não pode chamar métodos que
     * consultam o banco direto.
     */
    public function canViewCompTime(): bool
    {
        return $this->compTimeVisibility ??= $this->canManageCompTime()
            || $this->isCoordinator()
            || filled($this->matricula);
    }

    /**
     * Funcionário do Banco de Horas correspondente a este usuário, casado pela
     * matrícula. É o vínculo que faz o colaborador comum enxergar o próprio
     * cartão de ponto — ver Employee::user() para o outro lado e para a
     * ressalva do `varchar(5)`.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'matricula', 'employee_code');
    }

    public function coordinatorSectors()
    {
        return $this->sectors()->wherePivot('role', 'coordinator');
    }

    public function collaboratorSectors()
    {
        return $this->sectors()->wherePivot('role', 'collaborator');
    }
}
