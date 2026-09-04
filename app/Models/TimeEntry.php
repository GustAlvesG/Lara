<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class TimeEntry extends Model
{
    /** @use HasFactory<\Database\Factories\TimeEntryFactory> */
    use HasFactory;

    protected $table = 'time_entries';

    protected $fillable = [
        'employee_id',
        'entry_date',
        'reference_time',
        'entry_times',
        'type',
        'amount_minutes',
        'balance_minutes',
        'due_date',
        'status_id',
        'written_off',
    ];

    protected $casts = [
        'written_off' => 'boolean',
        // Sem estes casts o Eloquent grava o Carbon com hora ('2026-06-01
        // 00:00:00') em bancos que não tipam a coluna, e um
        // `whereBetween('entry_date', ['2026-06-01', '2026-06-01'])` deixa de
        // encontrar o próprio dia — foi assim que a detecção de duplicatas
        // perdia colisões em arquivos de um dia só.
        'entry_date'  => 'date',
        'due_date'    => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function adjustmentsToAdjust()
    {
        return $this->hasMany(TimeAdjustment::class, 'entry_time_to_adjust_id');
    }

    public function adjustmentsAdjusted()
    {
        return $this->hasMany(TimeAdjustment::class, 'entry_time_adjusted_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}
