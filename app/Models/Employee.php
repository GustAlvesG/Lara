<?php

namespace App\Models;

use App\Support\Cpf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Funcionário do clube — cadastro do módulo Funcionários (RH).
 *
 * Não é usuário do painel: tem matrícula, CPF e setor, e só vira "o mesmo"
 * que um usuário quando as matrículas casam (ver user()). É quem se
 * identifica no pedido de carro de aplicativo e quem dirige a frota; para os
 * dois, só vale o funcionário **apto** — ver scopeEligible() e blockReason().
 */
class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /** De onde veio o cadastro (coluna `source`, só auditoria). */
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SPREADSHEET = 'planilha';

    public const SOURCES = [
        self::SOURCE_IMPORT => 'Espelho de ponto',
        self::SOURCE_MANUAL => 'Cadastro manual',
        self::SOURCE_SPREADSHEET => 'Planilha do RH',
    ];

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
        'source',
    ];

    protected $casts = [
        'admission_date'    => 'date',
        'termination_date'  => 'date',
    ];

    /**
     * CPF gravado sempre só com dígitos. O espelho de ponto mandava com e sem
     * máscara, e comparar texto cru errava em silêncio — a migration
     * `prepare_employees_for_registry` acertou o legado, e daqui em diante
     * ninguém grava de outro jeito. Para exibir, use Cpf::format().
     */
    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Cpf::digits($value),
        );
    }

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
     * Por que o funcionário NÃO pode pedir carro de aplicativo nem dirigir a
     * frota na data informada (hoje, por padrão) — ou null se pode.
     *
     * Bloqueia: cadastro excluído, rescisão já em vigor, e férias ou
     * afastamento vigentes (decisão do RH: quem está fora da casa não usa
     * nada da casa). O texto vai para a portaria e para a tela, então é
     * frase pronta.
     */
    public function blockReason($date = null): ?string
    {
        $date = ($date ? \Carbon\Carbon::parse($date) : now())->startOfDay();

        if ($this->trashed()) {
            return 'Cadastro excluído';
        }

        if (!$this->isActiveOn($date)) {
            return 'Desligado em ' . $this->termination_date->format('d/m/Y');
        }

        if ($absence = $this->absenceOn($date)) {
            return $absence->type_label . ($absence->end_date
                ? ' até ' . $absence->end_date->format('d/m/Y')
                : ' (sem data de retorno)');
        }

        return null;
    }

    public function isEligible($date = null): bool
    {
        return $this->blockReason($date) === null;
    }

    /**
     * Aptos na data informada: não excluídos (o SoftDeletes já cuida), sem
     * rescisão em vigor e sem férias ou afastamento vigente. Mesma regra de
     * blockReason(), em SQL — é o que o Uber e a Frota consultam.
     */
    public function scopeEligible(Builder $query, $date = null): Builder
    {
        return $query->active($date)
            ->whereDoesntHave('absences', fn (Builder $q) => $q->current($date));
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
              ->orWhereDate('termination_date', '>', $date->startOfDay()->toDateString());
        });
    }

    /** Funcionários com rescisão já registrada e em vigor na data informada. */
    public function scopeTerminated(Builder $query, $date = null): Builder
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        return $query->whereNotNull('termination_date')
            ->whereDate('termination_date', '<=', $date->startOfDay()->toDateString());
    }
}
