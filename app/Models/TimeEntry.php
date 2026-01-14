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


}
