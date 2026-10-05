<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * O papel timbrado dos documentos assinados: imagem de cabeçalho, imagem de
 * rodapé e a linha de texto do rodapé.
 *
 * Versionado por linha — nunca se edita uma linha existente. O documento
 * aponta para a que estava vigente no congelamento, e é com ela que o PDF
 * final é montado meses depois. Ver a migration.
 *
 * @property ?string $header_path
 * @property int $header_height_mm
 * @property ?string $footer_path
 * @property int $footer_height_mm
 * @property ?string $footer_text
 * @property bool $full_width
 * @property string $align
 */
class SignatureLayout extends Model
{
    public const ALIGNMENTS = [
        'left' => 'À esquerda',
        'center' => 'Centralizado',
        'right' => 'À direita',
    ];

    protected $fillable = [
        'header_path',
        'header_height_mm',
        'footer_path',
        'footer_height_mm',
        'footer_text',
        'full_width',
        'align',
        'created_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'header_height_mm' => 22,
        'footer_height_mm' => 16,
        'full_width' => false,
        'align' => 'left',
    ];

    protected $casts = [
        'header_height_mm' => 'integer',
        'footer_height_mm' => 'integer',
        'full_width' => 'boolean',
        'created_by' => 'integer',
    ];

    /** O papel timbrado vigente — o que os próximos congelamentos vão usar. */
    public static function current(): ?self
    {
        return static::orderByDesc('id')->first();
    }

    /**
     * Os bytes de uma das imagens, ou null quando não há (ou o arquivo sumiu
     * do disco — o documento ainda precisa sair, com o cabeçalho padrão).
     *
     * @param  'header'|'footer'  $which
     */
    public function imageBytes(string $which): ?string
    {
        $caminho = $which === 'header' ? $this->header_path : $this->footer_path;

        if (!$caminho) {
            return null;
        }

        $disk = Storage::disk(config('signature.disk'));

        return $disk->exists($caminho) ? $disk->get($caminho) : null;
    }
}
