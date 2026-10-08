<?php

namespace App\Services\Signature\Govbr;

/**
 * O resultado da conferência de um PDF assinado pelo gov.br.
 *
 * Cada conferência é um item `{key, label, ok, detail}`:
 *
 *  - `ok = true`  passou;
 *  - `ok = false` reprovou — e basta um para o arquivo ser recusado;
 *  - `ok = null`  não foi conferido (a revogação, nesta versão). Não aprova
 *    nem reprova, mas aparece na tela, para ninguém supor que foi conferido.
 *
 * Vai para o banco como está (`signature_govbr_checks.result`), por isso só
 * carrega o que pode ser guardado: CPF sempre mascarado.
 *
 * @phpstan-type Check array{key: string, label: string, ok: ?bool, detail: string}
 */
final class GovbrValidationResult
{
    /**
     * @param  array<int, Check>  $checks       conferências do arquivo
     * @param  array<int, array<string, mixed>>  $signatures  uma entrada por assinatura, com as conferências dela
     * @param  ?string  $base  'original' ou 'final': sobre qual PDF do Lara a assinatura foi feita
     */
    public function __construct(
        public readonly array $checks,
        public readonly array $signatures,
        public readonly ?string $base,
    ) {
    }

    /** Válido quando nada reprovou — e há ao menos uma assinatura. */
    public function isValid(): bool
    {
        if ($this->signatures === []) {
            return false;
        }

        foreach ($this->allChecks() as $check) {
            if ($check['ok'] === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Os signatários do documento cuja assinatura passou em tudo.
     *
     * @return array<int, int>
     */
    public function signedSignerIds(): array
    {
        $ids = [];

        foreach ($this->signatures as $assinatura) {
            $passou = collect($assinatura['checks'])->every(fn(array $c) => $c['ok'] !== false);

            if ($passou && $assinatura['signer_id'] !== null) {
                $ids[] = $assinatura['signer_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * As chaves do que reprovou, para a trilha de auditoria.
     *
     * @return array<int, string>
     */
    public function failedKeys(): array
    {
        return collect($this->allChecks())
            ->filter(fn(array $c) => $c['ok'] === false)
            ->pluck('key')
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'base' => $this->base,
            'checks' => $this->checks,
            'signatures' => $this->signatures,
        ];
    }

    /** @return array<int, Check> */
    private function allChecks(): array
    {
        $todos = $this->checks;

        foreach ($this->signatures as $assinatura) {
            array_push($todos, ...$assinatura['checks']);
        }

        return $todos;
    }
}
