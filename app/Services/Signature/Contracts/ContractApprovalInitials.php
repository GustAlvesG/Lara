<?php

namespace App\Services\Signature\Contracts;

use App\Exceptions\ContractException;
use App\Models\SignatureContract;
use App\Models\SignatureEvidence;
use App\Support\PngTrimmer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * O visto (rubrica) que o gerente desenha ao aprovar o contrato (pedido do
 * usuário, 04/11/2026). Obrigatório para aprovar, no computador e no tablet.
 *
 * Vai na versão aprovada (`signature_contract_versions.approval_initials_path`)
 * e é copiado para o documento de assinatura gerado, que o carimba no pé de
 * TODAS as páginas do documento, na faixa do visto, ao lado das caixas dos
 * signatários (SignatureDocumentRenderer::stampInitials). Já sai preenchido no
 * PDF original: a aprovação vem antes do documento existir.
 *
 * Mesma régua do visto do tablet: PNG, um mínimo de pontos desenhados
 * (`signature.evidence.min_initials_points`), recortado ao traço.
 */
class ContractApprovalInitials
{
    /**
     * Valida e grava o visto. Devolve o caminho no disco do módulo.
     *
     * @param  mixed  $strokes  os traços do desenho (para contar os pontos)
     *
     * @throws ContractException
     */
    public function store(SignatureContract $contract, ?string $dataUrl, mixed $strokes): string
    {
        $dataUrl = (string) $dataUrl;

        if ($dataUrl === '' || SignatureEvidence::countPoints(is_array($strokes) ? $strokes : null)
            < (int) config('signature.evidence.min_initials_points', 8)) {
            throw new ContractException('Faça o seu visto no quadro antes de aprovar: ele vai em todas as páginas do contrato.');
        }

        if (!preg_match('#^data:image/png;base64,#i', $dataUrl)) {
            throw new ContractException('Não foi possível ler o visto. Desenhe de novo.');
        }

        $binario = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        $limite = (int) config('signature.evidence.max_signature_kb', 2048) * 1024;

        if ($binario === false || $binario === '' || strlen($binario) > $limite
            || !str_starts_with($binario, "\x89PNG\r\n\x1a\n") || !@getimagesizefromstring($binario)) {
            throw new ContractException('Não foi possível ler o visto. Desenhe de novo.');
        }

        $caminho = 'signature/contracts/' . $contract->id . '/visto-aprovacao-' . Str::uuid() . '.png';

        Storage::disk(config('signature.disk'))->put($caminho, PngTrimmer::trim($binario));

        return $caminho;
    }

    /** Apaga o visto de uma aprovação que não aconteceu. */
    public function discard(?string $path): void
    {
        if ($path) {
            Storage::disk(config('signature.disk'))->delete($path);
        }
    }
}
