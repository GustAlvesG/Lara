<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um arquivo anexado ao documento pelo atendente — identidade, comprovante.
 * Ver SignatureAttachmentService.
 *
 * O arquivo fica no disco privado do módulo e é servido só por rota. O nome
 * original é guardado para a tela, mas não vai à trilha: costuma trazer o
 * nome da pessoa.
 *
 * @property int $id
 * @property ?string $requirement_key
 * @property string $label
 * @property string $path
 * @property string $mime
 * @property int $bytes
 * @property string $sha256
 */
class SignatureAttachment extends Model
{
    /** O que se aceita, pelo tipo detectado no conteúdo — nunca pela extensão. */
    public const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    protected $fillable = [
        'signature_document_id',
        'requirement_key',
        'label',
        'original_name',
        'path',
        'mime',
        'bytes',
        'sha256',
        'uploaded_by',
        'uploaded_by_name',
    ];

    protected $casts = [
        'signature_document_id' => 'integer',
        'bytes' => 'integer',
        'uploaded_by' => 'integer',
    ];

    /**
     * @return BelongsTo<SignatureDocument, SignatureAttachment>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_document_id');
    }

    public function extension(): string
    {
        return self::MIME_EXTENSIONS[$this->mime] ?? 'bin';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** "PDF, 312 KB" — para a tela e o manifesto. */
    public function sizeLabel(): string
    {
        return mb_strtoupper($this->extension()) . ', '
            . number_format(max(1, (int) round($this->bytes / 1024)), 0, ',', '.') . ' KB';
    }
}
