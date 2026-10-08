<?php

namespace App\Services\Signature;

use App\Exceptions\SignatureFormException;
use App\Exceptions\SignatureSessionException;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Os dados que entram no documento NO ATO da assinatura: as respostas que
 * quem assina dá no tablet e a data automática.
 *
 * O congelamento do atendente continua sendo o que fixa o texto
 * (`body_snapshot`) e os dados dele. O que este serviço faz é completar as
 * lacunas que o modelo deixou para a hora da assinatura — e, a cada vez que
 * completa, REFAZ o PDF original e o `original_sha256`.
 *
 * É isso que preserva a garantia central do módulo: o que a pessoa lê no
 * tablet é, byte a byte, o arquivo cujo hash ficou gravado. A ordem no tablet
 * é formulário → documento já com as respostas → leitura → assinatura; ninguém
 * assina um texto com lacuna, e ninguém assina um texto diferente do que leu.
 *
 * A janela fecha na PRIMEIRA assinatura (`signingDataIsOpen()`). Até ali a
 * pessoa pode voltar e corrigir uma resposta; dali em diante existe alguém que
 * assinou aquele texto, e ele não muda mais.
 *
 * Toda troca de hash vira evento na trilha, com o hash anterior e o novo.
 */
class SignatureSigningDataService
{
    public function __construct(
        private SignatureDocumentRenderer $renderer,
        private SignatureStateMachine $states,
    ) {
    }

    /**
     * O formulário que o tablet mostra, ou null quando não há o que perguntar
     * — o modelo não pergunta nada, ou a janela já fechou.
     *
     * As respostas anteriores voltam preenchidas: é a mesma pessoa, com o
     * mesmo documento na mão, corrigindo o que digitou.
     *
     * @return array{answered: bool, fields: array<int, array<string, mixed>>}|null
     */
    public function form(SignatureDocument $document): ?array
    {
        $campos = $document->signerFields();

        if ($campos === [] || !$document->signingDataIsOpen()) {
            return null;
        }

        $valores = $document->signing_data ?? [];

        return [
            'answered' => $document->signing_answered_at !== null,
            'fields' => array_map(fn(array $campo) => [
                'key' => $campo['key'],
                'question' => $campo['question'],
                'type' => $campo['type'],
                'required' => $campo['required'],
                'options' => $campo['options'],
                'value' => SignatureFieldTypes::inputValue($campo, $valores[$campo['key']] ?? null),
            ], $campos),
        ];
    }

    /**
     * Aplica a data da assinatura aos campos automáticos.
     *
     * Chamado quando o tablet abre o documento. Só refaz o PDF se a data
     * mudou — um documento congelado ontem e aberto hoje sai com a data de
     * hoje, e reabrir o mesmo documento no mesmo dia não gera hash novo.
     *
     * @param  array<string, mixed>  $context  contexto da auditoria
     */
    public function prepare(SignatureDocument $document, array $context = []): SignatureDocument
    {
        if (!$document->signingDataIsOpen()) {
            return $document;
        }

        $automaticos = $this->automaticValues($document);

        if ($automaticos === []) {
            return $document;
        }

        $atual = $document->signing_data ?? [];
        $novo = array_merge($atual, $automaticos);

        if ($novo == $atual) {
            return $document;
        }

        return $this->store($document, $novo, [], SignatureAuditEvent::EVENT_SIGNING_DATE_SET, $context, [
            'data' => now()->format('d/m/Y'),
        ]);
    }

    /**
     * Grava as respostas de quem assina e refaz o documento com elas.
     *
     * @param  array<string, mixed>  $answers  chave do campo => resposta crua do tablet
     * @param  array<string, mixed>  $context  contexto da auditoria
     *
     * @throws SignatureFormException
     * @throws SignatureSessionException
     */
    public function answer(SignatureDocument $document, array $answers, array $context = []): SignatureDocument
    {
        $campos = $document->signerFields();

        if ($campos === []) {
            throw new SignatureSessionException('Este documento não tem perguntas a responder.', 409);
        }

        if (!$document->signingDataIsOpen()) {
            throw new SignatureSessionException(
                'Este documento já recebeu uma assinatura e as respostas não podem mais ser alteradas.',
                409,
            );
        }

        $valores = [];
        $erros = [];

        foreach ($campos as $campo) {
            [$valor, $erro] = SignatureFieldTypes::parse($campo, $answers[$campo['key']] ?? null);

            if ($erro !== null) {
                $erros[$campo['key']] = $erro;
            } elseif ($valor === null && $campo['required']) {
                $erros[$campo['key']] = 'Esta resposta é obrigatória.';
            } elseif ($valor !== null) {
                $valores[$campo['key']] = $valor;
            }
        }

        if ($erros !== []) {
            throw new SignatureFormException($erros);
        }

        return $this->store(
            $document,
            // Reconstruído do zero: uma resposta apagada na correção não pode
            // sobreviver da tentativa anterior.
            array_merge($this->automaticValues($document), $valores),
            ['signing_answered_at' => now()],
            SignatureAuditEvent::EVENT_FORM_ANSWERED,
            $context,
            // As CHAVES, e não os valores: a trilha é impressa no manifesto, e
            // uma resposta pode ser um CPF ou um telefone.
            ['campos' => array_keys($valores)],
        );
    }

    /**
     * @return array<string, string>
     */
    private function automaticValues(SignatureDocument $document): array
    {
        $valores = [];

        foreach ($document->automaticFields() as $campo) {
            $valores[$campo['key']] = SignatureFieldTypes::automaticValue($campo['type'], now());
        }

        return $valores;
    }

    /**
     * Grava os dados, refaz o PDF original e registra a troca de hash.
     *
     * O arquivo é gravado ANTES da transação e devolvido ao que era se ela
     * falhar: disco não participa de rollback, e um PDF novo com o hash antigo
     * no banco seria um documento que não confere consigo mesmo.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $payload
     */
    private function store(
        SignatureDocument $document,
        array $data,
        array $attributes,
        string $event,
        array $context,
        array $payload,
    ): SignatureDocument {
        $disk = Storage::disk(config('signature.disk'));
        $caminho = $document->original_path;
        $hashAnterior = $document->original_sha256;
        $bytesAnteriores = $caminho && $disk->exists($caminho) ? $disk->get($caminho) : null;

        $document->forceFill(array_merge($attributes, ['signing_data' => $data]));

        $bytes = $this->renderer->pdf($document);
        $hash = hash('sha256', $bytes);

        $disk->put($caminho, $bytes);

        try {
            DB::transaction(function () use ($document, $hash, $hashAnterior, $event, $context, $payload) {
                $document->forceFill(['original_sha256' => $hash])->save();

                $this->states->note($document, $event, array_merge($context, [
                    'payload' => array_merge($payload, [
                        'hash_anterior' => $hashAnterior,
                        'hash_novo' => $hash,
                    ]),
                ]));
            });
        } catch (\Throwable $e) {
            if ($bytesAnteriores !== null) {
                $disk->put($caminho, $bytesAnteriores);
            }

            throw $e;
        }

        return $document;
    }
}
