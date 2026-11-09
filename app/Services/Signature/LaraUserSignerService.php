<?php

namespace App\Services\Signature;

use App\Models\User;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Support\Cpf;
use Illuminate\Support\Collection;

/**
 * Signatário "usuário do Lara" (09/11/2026): o atendente escolhe um usuário
 * do sistema para assinar, e nome, CPF, e-mail e telefone saem do cadastro
 * dele — nunca do formulário.
 *
 * Decisões do usuário: qualquer usuário ATIVO com CPF válido (do usuário ou
 * do cadastro de funcionário) pode ser escolhido; ele assina como qualquer
 * signatário — QR do balcão (com a conferência do modelo) ou link remoto —,
 * sem a entrada por matrícula e PIN da Diretoria; e conta como "de fora da
 * diretoria" (a diretoria espera por ele). A parte do modelo pode já trazer
 * um usuário (`lara_user_id`).
 *
 * No signatário, o que o marca é `user_id` sem `director_area_id`
 * (`SignatureSigner::isLaraUser`).
 */
class LaraUserSignerService
{
    /** Quantos usuários a busca devolve. */
    public const SEARCH_LIMIT = 10;

    public function __construct(private DirectorAreaService $directors)
    {
    }

    /**
     * Por que este usuário não pode ser escolhido para assinar. null = pode.
     */
    public function ineligibilityReason(User $user): ?string
    {
        if ((int) $user->status_id !== 1) {
            return $user->name . ' está com o usuário inativo no Lara.';
        }

        if ($this->directors->cpfOf($user) === null) {
            return $user->name . ' está sem CPF válido no cadastro do Lara (usuário ou funcionário). '
                . 'Corrija o cadastro antes de escolhê-lo.';
        }

        return null;
    }

    /**
     * Busca de usuários ativos por nome, matrícula ou e-mail — o que a tela do
     * documento e a do modelo oferecem. Cada opção já diz se dá para escolher.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $usuarios = User::query()
            ->with('employee')
            ->where('status_id', 1)
            ->where(fn($q) => $q
                ->where('name', 'like', '%' . $term . '%')
                ->orWhere('matricula', $term)
                ->orWhere('email', 'like', '%' . $term . '%'))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return $usuarios->map(fn(User $user) => $this->option($user))->values()->all();
    }

    /**
     * As opções de usuários já escolhidos (rascunho reaberto, parte do modelo
     * que traz um usuário), por id — para o formulário nascer mostrando quem é.
     *
     * @param  iterable<int|string|null>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function optionsFor(iterable $ids): array
    {
        $ids = collect($ids)->filter(fn($id) => is_numeric($id) && (int) $id > 0)->map(fn($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return User::query()
            ->with('employee')
            ->whereIn('id', $ids->all())
            ->get()
            ->mapWithKeys(fn(User $user) => [$user->id => $this->option($user)])
            ->all();
    }

    /**
     * O signatário-usuário como ele é GRAVADO: os dados vêm do cadastro do
     * Lara. Recusa usuário inexistente, inativo ou sem CPF válido.
     *
     * @return array<string, mixed>
     *
     * @throws LaraUserSignerException
     */
    public function signerData(int $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            throw new LaraUserSignerException('O usuário do Lara escolhido não foi encontrado. Escolha de novo.');
        }

        if ($motivo = $this->ineligibilityReason($user)) {
            throw new LaraUserSignerException($motivo);
        }

        return [
            'name' => $user->name,
            'cpf' => $this->directors->cpfOf($user),
            'email' => $user->email,
            'phone' => $user->phone,
            'member_id' => null,
            'user_id' => $user->id,
            'director_area_id' => null,
            'director_area_name' => null,
            'director_role' => null,
        ];
    }

    /**
     * Nomes dos usuários por id (para a tela do modelo dizer quem a parte traz).
     *
     * @param  iterable<int|string|null>  $ids
     * @return Collection<int, string>
     */
    public function names(iterable $ids): Collection
    {
        $ids = collect($ids)->filter(fn($id) => is_numeric($id) && (int) $id > 0)->map(fn($id) => (int) $id)->unique()->values();

        return $ids->isEmpty() ? collect() : User::whereIn('id', $ids->all())->pluck('name', 'id');
    }

    /**
     * Uma opção como vai à tela: CPF só mascarado.
     *
     * @return array<string, mixed>
     */
    private function option(User $user): array
    {
        $cpf = $this->directors->cpfOf($user);

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'matricula' => $user->matricula,
            'cpf_masked' => $cpf ? Cpf::mask($cpf) : null,
            'email' => $user->email,
            'phone' => $user->phone,
            'blocked' => $this->ineligibilityReason($user),
        ];
    }
}
