<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_sector')
            ->withPivot('role')
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
