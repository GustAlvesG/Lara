<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\CardTemplateFactory> */
    use HasFactory;

    protected $table = 'card_templates';

    protected $fillable = [
        'name',
        'front_image',
        'back_image',
        'layout',
        'is_active',
        'card_width_mm',
        'card_height_mm',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function frontImageUrl(): string
    {
        return asset('images/' . $this->front_image);
    }

    public function backImageUrl(): string
    {
        return asset('images/' . $this->back_image);
    }
}
