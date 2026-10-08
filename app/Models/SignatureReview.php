<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma revisão interna de documento assinado — ver SignatureReviewService.
 *
 * `items` é o retrato dos itens do modelo NO MOMENTO da revisão, cada um com
 * `done`: uma revisão futura do modelo não reescreve o que foi conferido.
 *
 * @property int $id
 * @property string $result
 * @property array<int, array{key: string, label: string, done: bool}>|null $items
 * @property ?string $notes
 * @property bool $early
 * @property bool $own
 * @property ?int $reviewed_by
 * @property ?string $reviewed_by_name
 */
class SignatureReview extends Model
{
    public const RESULT_OK = 'ok';
    public const RESULT_ISSUES = 'issues';

    public const RESULT_LABELS = [
        self::RESULT_OK => 'Tudo em ordem',
        self::RESULT_ISSUES => 'Com pendência',
    ];

    protected $fillable = [
        'signature_document_id',
        'result',
        'items',
        'notes',
        'early',
        'own',
        'reviewed_by',
        'reviewed_by_name',
    ];

    protected $casts = [
        'signature_document_id' => 'integer',
        'items' => 'array',
        'early' => 'boolean',
        'own' => 'boolean',
        'reviewed_by' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureDocument, SignatureReview>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }

    public function resultLabel(): string
    {
        return self::RESULT_LABELS[$this->result] ?? $this->result;
    }
}
