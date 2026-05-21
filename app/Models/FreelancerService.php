<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreelancerService extends Model
{
    /** @use HasFactory<\Database\Factories\FreelancerServiceFactory> */
    use HasFactory;

    protected $table = 'freelancer_services';

    protected $fillable = [
        'freelancer_id',
        'function_freelancer_id',
        'start_date',
        'end_date',
        'price',
        'total_hours',
        'status_id'
    ];

    public function freelancer()
    {
        return $this->belongsTo(Freelancer::class);
    }

    public function functionFreelancer()
    {
        return $this->belongsTo(FunctionFreelancer::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
