<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class Sector extends Model
{
    use HasFactory;

    public const ROLE_COORDINATOR = 'coordinator';
    public const ROLE_COLLABORATOR = 'collaborator';

    protected $fillable = ['name', 'description', 'full_access'];

    protected function casts(): array
    {
        return [
            'full_access' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_sector')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Permissões do catálogo concedidas ao setor. `coordinators_only` diz se
     * a permissão chega a todos os membros ou só a quem coordena — ver
     * App\Authorization\AccessResolver.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'sector_permission')
            ->withPivot('coordinators_only')
            ->withTimestamps();
    }

    /**
     * Funcionários do Banco de Horas lotados neste setor. É o outro lado de
     * Employee::sector() — o vínculo que substituiu a comparação por texto
     * entre `employees.department` e `sectors.name`.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
