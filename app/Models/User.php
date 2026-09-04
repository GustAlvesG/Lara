<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\DataInfo;
use App\Models\Information;
use Spatie\Permission\Traits\HasRoles; // Importe o trait
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles; // Use o trait HasRoles
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
     * Setor que responde pelo financeiro dos freelancers.
     * Criado pela migration `create_contabilidade_sector`.
     */
    public const ACCOUNTING_SECTOR = 'Contabilidade';

    /**
     * Setor que registra os contratos de freelancer e acompanha o trâmite deles
     * até o pagamento. Coordenar o setor dá poderes extras (assinar como
     * contraparte, liberar o limite semanal); **estar** nele, em qualquer papel,
     * dá a tela de acompanhamento.
     */
    public const COMMERCIAL_SECTOR = 'Comercial';

    /**
     * Setor do terceiro nível da aprovação de ordem de compra. Estar nele não
     * dá poder de aprovar nada sozinho: quem decide uma ordem são os membros
     * ligados ao centro de custo dela, ou os que a Gerência escolher.
     * Criado pela migration `create_diretoria_sector`.
     */
    public const DIRECTORS_SECTOR = 'Diretoria';

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

    /** Cache da requisição para canManageFreelancerPayments(). */
    private ?bool $freelancerPaymentsAccess = null;

    /** Cache da requisição para canTrackFreelancerBatches(). */
    private ?bool $freelancerTrackingAccess = null;

    /** Cache da requisição para canAccessCotacao(). */
    private ?bool $cotacaoAccess = null;

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
     * Financeiro dos freelancers (aba Financeiro e baixa de pagamento): quem
     * está no setor **Contabilidade** ou no setor **Gerência**, em qualquer
     * papel.
     *
     * Como a aprovação do lote, é atribuição de setor e não nível de acesso —
     * a role `admin` não vale aqui. Quem administra o sistema não paga
     * freelancer por consequência disso; entra no setor quem de fato paga.
     *
     * Memorizado por instância porque a barra de abas, o menu e o middleware
     * perguntam a mesma coisa na mesma requisição. Cada requisição reconfere,
     * então tirar o vínculo no painel corta o acesso na hora.
     */
    public function canManageFreelancerPayments(): bool
    {
        return $this->freelancerPaymentsAccess ??= $this->belongsToSectorNamed(self::ACCOUNTING_SECTOR)
            || $this->belongsToSectorNamed(self::MANAGEMENT_SECTOR);
    }

    /**
     * Acompanhamento dos lotes (aba própria, só leitura): quem está no setor
     * **Comercial**, em qualquer papel. É o setor que registra os contratos e
     * responde ao freelancer por onde o pagamento dele parou — sem precisar,
     * para isso, aprovar ou pagar coisa nenhuma.
     */
    public function canTrackFreelancerBatches(): bool
    {
        return $this->freelancerTrackingAccess ??= $this->belongsToSectorNamed(self::COMMERCIAL_SECTOR)
            || $this->canManageFreelancerPayments();
    }

    /**
     * Mapa de cotação: quem está no setor **Contabilidade**, em qualquer papel.
     *
     * Como o financeiro dos freelancers, é atribuição de setor e não nível de
     * acesso — **a role `admin` não vale aqui**. Quem administra o sistema não
     * cota compra por consequência disso; entra no setor quem de fato cota.
     *
     * O setor é a porta; o que cada um faz lá dentro (digitar preço, escolher
     * vencedor, exportar) continua sendo decidido pelas permissões `cotacao.*`.
     *
     * Memorizado por instância porque o menu, a policy e cada ação da grade
     * perguntam a mesma coisa na mesma requisição. Cada requisição reconfere,
     * então tirar o vínculo no painel corta o acesso na hora.
     */
    public function canAccessCotacao(): bool
    {
        return $this->cotacaoAccess ??= $this->belongsToSectorNamed(self::ACCOUNTING_SECTOR);
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
