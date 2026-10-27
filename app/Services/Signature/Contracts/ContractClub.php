<?php

namespace App\Services\Signature\Contracts;

use App\Models\SignatureDirectorArea;

/**
 * O lado do clube no contrato: o estabelecimento (um dos dois CNPJs) e o
 * presidente, que sempre representa o clube (decisão do usuário, 27/10/2026).
 *
 * O presidente é o TITULAR vigente da área da diretoria configurada em
 * `signature.contracts.president_area` (Assinaturas → Diretoria): trocar o
 * mandato lá troca o nome nos contratos novos, sem mexer em código.
 */
class ContractClub
{
    /** @var array{name: string, area_id: int, area_name: string, user_id: int}|false|null */
    private array|false|null $president = null;

    /**
     * @return array{label: string, name: string, cnpj: string, state_registration: ?string, address: string}|null
     */
    public function establishment(?string $key): ?array
    {
        $todos = config('signature.contracts.establishments', []);

        return $key !== null && isset($todos[$key]) ? $todos[$key] : null;
    }

    /** @return array<string, string> chave => rótulo, para a escolha na tela */
    public function establishmentOptions(): array
    {
        return collect(config('signature.contracts.establishments', []))
            ->map(fn(array $e) => $e['label'])
            ->all();
    }

    /**
     * O presidente vigente, ou null se a área não existe ou está sem titular.
     *
     * @return array{name: string, area_id: int, area_name: string, user_id: int}|null
     */
    public function president(): ?array
    {
        if ($this->president === null) {
            $area = SignatureDirectorArea::where('active', true)
                ->where('name', (string) config('signature.contracts.president_area'))
                ->first();

            $titular = $area?->primaryHolder();

            $this->president = $titular
                ? [
                    'name' => (string) $titular->user_name,
                    'area_id' => $area->id,
                    'area_name' => $area->name,
                    'user_id' => (int) $titular->user_id,
                ]
                : false;
        }

        return $this->president ?: null;
    }
}
