<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'employee_code',
        'name',
        'cpf',
        'admission_date',
        'position',
        'department',
        'sector_id',
        'termination_date',
        'termination_reason',
    ];

    protected $casts = [
        'admission_date'    => 'date',
        'termination_date'  => 'date',
    ];

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Setor do funcionário. É o vínculo de verdade — `department` continua na
     * tabela como o texto cru da "Estrutura" que veio do espelho de ponto, útil
     * para conferir de onde o funcionário caiu neste setor, mas não é por ele
     * que o acesso é decidido.
     */
    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    /** Férias e afastamentos, do mais recente para o mais antigo. */
    public function absences()
    {
        return $this->hasMany(EmployeeAbsence::class)->orderByDesc('start_date');
    }

    /**
     * Usuário do sistema correspondente, casado pela matrícula
     * (`users.matricula` = `employees.employee_code`).
     *
     * Não há chave estrangeira entre os dois: o funcionário nasce da
     * importação do espelho de ponto e o usuário nasce do cadastro do painel —
     * um existe sem o outro, e a matrícula é o único documento que os dois
     * lados compartilham.
     *
     * Cuidado ao consultar: `users.matricula` é `varchar(5)`. Matrícula mais
     * longa que isso simplesmente não casa, e o funcionário fica sem usuário.
     */
    public function user()
    {
        return $this->hasOne(User::class, 'matricula', 'employee_code');
    }

    /** Desligado — tem data de rescisão registrada (mesmo que futura). */
    public function isTerminated(): bool
    {
        return $this->termination_date !== null;
    }

    /**
     * Ativo na data informada (hoje, por padrão): sem rescisão, ou com rescisão
     * ainda por vir. Espelha o escopo `active()` para uso em memória.
     */
    public function isActiveOn($date = null): bool
    {
        if ($this->termination_date === null) {
            return true;
        }

        $date = $date ? \Carbon\Carbon::parse($date) : now();

        return $this->termination_date->gt($date->startOfDay());
    }

    /**
     * Ausência (férias ou afastamento) vigente na data informada, se houver.
     * Período sem `end_date` é lido como em aberto — vale dali em diante.
     */
    public function absenceOn($date = null): ?EmployeeAbsence
    {
        $date = $date ? \Carbon\Carbon::parse($date)->startOfDay() : now()->startOfDay();

        return $this->absences
            ->first(fn (EmployeeAbsence $absence) => $absence->coversDate($date));
    }

    /**
     * Funcionários ativos na data informada (hoje, por padrão).
     *
     * Rescisão é informativa — não esconde ninguém das telas do Banco de Horas,
     * que continuam mostrando o histórico de quem saiu. Este escopo existe para
     * quem precisa da foto de quem está na casa: a tela do RH, quando o filtro
     * pede, e o módulo de consulta de funcionários ativos que vem depois.
     */
    public function scopeActive(Builder $query, $date = null): Builder
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        return $query->where(function (Builder $q) use ($date) {
            $q->whereNull('termination_date')
              ->orWhere('termination_date', '>', $date->startOfDay()->toDateString());
        });
    }

    /** Funcionários com rescisão já registrada e em vigor na data informada. */
    public function scopeTerminated(Builder $query, $date = null): Builder
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        return $query->whereNotNull('termination_date')
            ->where('termination_date', '<=', $date->startOfDay()->toDateString());
    }
}
