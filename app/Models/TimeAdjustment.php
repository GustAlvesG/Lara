<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\TimeAdjustmentFactory> */
    use HasFactory;

    protected $table = 'time_adjustments';

    protected $fillable = [
        'entry_time_to_adjust_id',
        'entry_time_adjusted_id',
        'amount_minutes',
        'before_adjustment_minutes',
        'after_adjustment_minutes',
        'reason',
    ];
}
