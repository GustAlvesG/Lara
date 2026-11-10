<?php

namespace App\Models\Waitlist;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma ligação da secretaria para quem está na fila. Só registro: nenhum
 * resultado muda a posição da pessoa. Para tirá-la da fila (matriculou,
 * desistiu), a secretaria usa as ações da inscrição.
 */
class WaitlistContact extends Model
{
    public const RESULTS = [
        'no_answer' => 'Não atendeu',
        'message' => 'Deixou recado',
        'callback' => 'Pediu para ligar depois',
        'not_now' => 'Não quer a vaga agora (continua na fila)',
        'accepted' => 'Aceitou a vaga',
        'wrong_number' => 'Telefone errado',
    ];

    protected $table = 'waitlist_contacts';

    protected $fillable = ['waitlist_entry_id', 'result', 'notes', 'user_id'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(WaitlistEntry::class, 'waitlist_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }
}
