<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Models\SignatureDocument;
use Illuminate\Http\Request;

/**
 * A página PÚBLICA de validação: `/validar/{codigo}`.
 *
 * Existe para responder a quem tem o papel na mão: este documento é mesmo do
 * clube, quem assinou, quando, e este arquivo aqui é o que foi assinado?
 *
 * O que ela mostra é deliberadamente pouco: título, data, situação e os
 * signatários com o **CPF mascarado**. Não mostra a foto, não mostra o traço,
 * não mostra dado de contato e não entrega o PDF — quem tem o código tem o
 * papel, e o papel já traz o conteúdo. Uma página pública que devolvesse o
 * documento inteiro transformaria um código de 12 caracteres em acesso a dado
 * pessoal alheio.
 *
 * A conferência de arquivo é feita POR HASH, no navegador de quem valida: o
 * PDF enviado é lido, hasheado e descartado — nunca é gravado em disco.
 */
class ValidationController extends Controller
{
    public function show(string $codigo)
    {
        $documento = $this->encontra($codigo);

        return view('signature.validate', [
            'codigo' => mb_strtoupper($codigo),
            'document' => $documento,
        ]);
    }

    /**
     * Confere o hash de um PDF enviado contra os que estão registrados.
     *
     * Três respostas possíveis, e as três importam: bate com o FINAL (a via
     * assinada), bate com o ORIGINAL (o documento antes das assinaturas — útil
     * para quem guardou a prévia) ou não bate com nenhum.
     */
    public function verify(Request $request, string $codigo)
    {
        $documento = $this->encontra($codigo);

        $request->validate([
            'documento' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ], [
            'documento.mimetypes' => 'Envie o arquivo PDF do documento.',
            'documento.max' => 'O arquivo é grande demais (limite de 20 MB).',
        ]);

        // O arquivo é lido do diretório temporário do PHP e nunca gravado:
        // validar não é receber documento de ninguém.
        $hash = hash_file('sha256', $request->file('documento')->getRealPath());

        $resultado = match (true) {
            $documento && $hash === $documento->final_sha256 => 'final',
            $documento && $hash === $documento->original_sha256 => 'original',
            default => 'divergente',
        };

        return view('signature.validate', [
            'codigo' => mb_strtoupper($codigo),
            'document' => $documento,
            'conferencia' => [
                'resultado' => $resultado,
                'hash' => $hash,
            ],
        ]);
    }

    /**
     * O documento do código, quando ele existe E já foi congelado.
     *
     * Um rascunho não é validável: ele ainda não tem hash, não foi assinado e
     * nem sequer deveria ter código. Devolver null aqui faz a página dizer
     * "não encontrado" — a mesma resposta de um código inventado, que é o que
     * impede a página de confirmar a existência de documentos alheios.
     */
    private function encontra(string $codigo): ?SignatureDocument
    {
        return SignatureDocument::with('signers')
            ->where('validation_code', mb_strtoupper(trim($codigo)))
            ->whereNotNull('frozen_at')
            ->first();
    }
}
