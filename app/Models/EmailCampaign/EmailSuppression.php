<?php

namespace App\Models\EmailCampaign;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Não enviar para": endereço que pediu para sair das campanhas. Vale para
 * todas, inclusive as já em andamento — o envio confere na hora de mandar.
 * O e-mail é gravado sempre em minúsculas (ver normalize()).
 */
class EmailSuppression extends Model
{
    protected $fillable = ['email', 'reason', 'created_by'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
