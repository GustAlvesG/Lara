<?php

namespace App\Models;

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
    ];

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Cachês solicitados para este funcionário. */
    public function caches()
    {
        return $this->hasMany(EmployeeCache::class);
    }

    /**
     * Procura o funcionário pelo que ele sabe de cabeça: a matrícula ou o CPF.
     * O CPF é comparado só pelos dígitos — é gravado sem pontuação, mas se
     * digita com.
     *
     * É a identificação da tela de assinatura do cachê: não há senha nem PIN
     * porque o funcionário não é usuário do sistema. O que prova a assinatura é
     * o traço que ele desenha, guardado junto do horário que ele informou.
     */
    public static function findByCodeOrCpf(string $identifier): ?self
    {
        $identifier = trim($identifier);
        $digits = preg_replace('/\D/', '', $identifier);

        // O grupo mantém os OR juntos: sem ele, uma condição futura no fim da
        // consulta valeria só para o último ramo.
        return static::where(function ($query) use ($identifier, $digits) {
            $query->where('employee_code', $identifier);

            if ($digits !== '') {
                $query->orWhere('cpf', $digits)->orWhere('employee_code', $digits);
            }
        })->first();
    }
}
