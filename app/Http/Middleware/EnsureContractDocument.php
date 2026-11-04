<?php

namespace App\Http\Middleware;

use App\Models\SignatureDocument;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A aba Assinaturas do módulo Contratos (`contract-signatures.*`) só abre
 * documento que veio de contrato. Os outros documentos são de Assinaturas e
 * moram em `signature-documents.*` — pelo endereço de Contratos, 404.
 *
 * O QUEM é da SignatureDocumentPolicy (`contratos.assinatura` + setor do
 * contrato); isto só garante que a aba não vira porta para qualquer documento.
 */
class EnsureContractDocument
{
    public function handle(Request $request, Closure $next): Response
    {
        $document = $request->route('signatureDocument');

        if ($document instanceof SignatureDocument) {
            abort_unless($document->isContract(), 404);
        } elseif ($document !== null) {
            abort_unless(SignatureDocument::whereKey($document)->has('contract')->exists(), 404);
        }

        return $next($request);
    }
}
