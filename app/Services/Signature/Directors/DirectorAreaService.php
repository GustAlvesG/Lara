<?php

namespace App\Services\Signature\Directors;

use App\Models\SignatureDirectorArea;
use App\Models\SignatureDirectorAreaHolder;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * As áreas da diretoria e quem responde por elas — e a ponte entre isso e o
 * signatário do documento.
 *
 * Quem pode responder por uma área: usuário ATIVO, com matrícula, do setor
 * Diretoria (`User::isDirector`). A matrícula é o login do tablet; o setor é
 * o mesmo critério da aprovação de compras.
 *
 * Mandato não se edita: trocar o titular encerra o vigente e abre outro.
 */
class DirectorAreaService
{
    public function createArea(string $name, ?string $description, ?int $userId = null): SignatureDirectorArea
    {
        $name = trim($name);

        if (SignatureDirectorArea::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new DirectorException('Já existe uma área com este nome.');
        }

        return SignatureDirectorArea::create([
            'name' => $name,
            'description' => $description !== null ? trim($description) ?: null : null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Renomear não mexe nos documentos: cada signatário guarda o nome da área
     * como estava na criação do documento.
     */
    public function updateArea(SignatureDirectorArea $area, string $name, ?string $description, bool $active): SignatureDirectorArea
    {
        $name = trim($name);

        $repetida = SignatureDirectorArea::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $area->id)
            ->exists();

        if ($repetida) {
            throw new DirectorException('Já existe outra área com este nome.');
        }

        $area->forceFill([
            'name' => $name,
            'description' => $description !== null ? trim($description) ?: null : null,
            'active' => $active,
        ])->save();

        return $area;
    }

    /**
     * Põe um diretor numa área. Titular novo encerra o titular vigente — uma
     * área tem um titular por vez; substitutos, quantos houver.
     */
    public function assign(
        SignatureDirectorArea $area,
        User $director,
        string $role,
        ?Carbon $startsOn = null,
        ?int $actorId = null,
        ?string $actorName = null,
    ): SignatureDirectorAreaHolder {
        if (!array_key_exists($role, SignatureDirectorAreaHolder::ROLE_LABELS)) {
            throw new DirectorException('Escolha titular ou substituto.');
        }

        if ($motivo = $this->ineligibilityReason($director)) {
            throw new DirectorException($motivo);
        }

        $jaEsta = $area->currentHolders()->where('user_id', $director->id)->first();

        if ($jaEsta) {
            throw new DirectorException($director->name . ' já responde por esta área como '
                . mb_strtolower($jaEsta->roleLabel()) . '. Encerre o mandato atual antes de trocar o papel.');
        }

        return DB::transaction(function () use ($area, $director, $role, $startsOn, $actorId, $actorName) {
            if ($role === SignatureDirectorAreaHolder::ROLE_PRIMARY) {
                foreach ($area->currentHolders()->where('role', SignatureDirectorAreaHolder::ROLE_PRIMARY)->get() as $anterior) {
                    $this->end($anterior, $actorId, $actorName);
                }
            }

            return SignatureDirectorAreaHolder::create([
                'signature_director_area_id' => $area->id,
                'user_id' => $director->id,
                'user_name' => $director->name,
                'role' => $role,
                'starts_on' => ($startsOn ?? now())->toDateString(),
                'set_by' => $actorId,
                'set_by_name' => $actorName,
            ]);
        });
    }

    public function end(SignatureDirectorAreaHolder $holder, ?int $actorId = null, ?string $actorName = null): SignatureDirectorAreaHolder
    {
        if (!$holder->isCurrent()) {
            return $holder;
        }

        $holder->forceFill([
            'ended_at' => now(),
            'ended_by' => $actorId,
            'ended_by_name' => $actorName,
        ])->save();

        return $holder;
    }

    /**
     * Por que este usuário não pode responder por uma área (nem assinar como
     * diretor). null = pode.
     */
    public function ineligibilityReason(User $user): ?string
    {
        if ((int) $user->status_id !== 1) {
            return $user->name . ' está com o usuário inativo no Lara.';
        }

        if (blank($user->matricula)) {
            return $user->name . ' não tem matrícula no Lara — é com ela que o diretor entra no tablet.';
        }

        if (!$user->isDirector()) {
            return $user->name . ' não é do setor ' . User::DIRECTORS_SECTOR . '. Vincule-o em Setores antes.';
        }

        return null;
    }

    /**
     * O CPF do diretor: o do usuário e, sem ele, o do cadastro de funcionário
     * pela matrícula. Null quando nenhum dos dois tem um CPF válido — e aí ele
     * não pode ser escolhido: o documento imprime o CPF, e o gov.br confere
     * por ele.
     */
    public function cpfOf(User $user): ?string
    {
        foreach ([fn() => $user->cpf, fn() => $user->employee?->cpf] as $fonte) {
            $cpf = Cpf::digits((string) $fonte());

            if (Cpf::isValid($cpf)) {
                return $cpf;
            }
        }

        return null;
    }

    /**
     * Quem pode ser escolhido para assinar, por área ativa — o que a tela do
     * documento oferece. Cada opção já diz se dá para escolher (e por quê não).
     *
     * @return array<int, array{area_id: int, area: string, holders: array<int, array<string, mixed>>}>
     */
    public function options(): array
    {
        $areas = SignatureDirectorArea::where('active', true)
            ->with('currentHolders')
            ->orderBy('name')
            ->get()
            ->filter(fn(SignatureDirectorArea $area) => $area->currentHolders->isNotEmpty());

        if ($areas->isEmpty()) {
            return [];
        }

        $usuarios = User::whereIn('id', $areas->flatMap->currentHolders->pluck('user_id')->unique()->values())
            ->get()
            ->keyBy('id');

        return $areas->map(fn(SignatureDirectorArea $area) => [
            'area_id' => $area->id,
            'area' => $area->name,
            'holders' => $area->currentHolders->map(function (SignatureDirectorAreaHolder $holder) use ($usuarios) {
                $user = $usuarios->get($holder->user_id);
                $cpf = $user ? $this->cpfOf($user) : null;
                $motivo = !$user
                    ? 'usuário não encontrado'
                    : ($this->ineligibilityReason($user) ?? ($cpf === null ? 'sem CPF no cadastro' : null));

                return [
                    'user_id' => $holder->user_id,
                    'name' => $user?->name ?? $holder->user_name,
                    'role' => $holder->role,
                    'role_label' => $holder->roleLabel(),
                    'cpf_masked' => $cpf ? Cpf::mask($cpf) : null,
                    'email' => $user?->email,
                    'phone' => $user?->phone,
                    'blocked' => $motivo,
                ];
            })->values()->all(),
        ])->values()->all();
    }

    /**
     * O signatário-diretor como ele é GRAVADO: os dados vêm do cadastro do
     * Lara, nunca do formulário. Recusa quem não é mandato vigente da área.
     *
     * @return array<string, mixed>
     */
    public function signerData(int $areaId, int $userId): array
    {
        $area = SignatureDirectorArea::find($areaId);

        if (!$area || !$area->active) {
            throw new DirectorException('A área da diretoria escolhida não existe ou foi desativada.');
        }

        $mandato = $area->currentHolders()->where('user_id', $userId)->first();

        if (!$mandato) {
            throw new DirectorException('Quem foi escolhido não responde mais pela ' . $area->name . '. Escolha de novo.');
        }

        $user = User::find($userId);

        if (!$user) {
            throw new DirectorException('O usuário do diretor escolhido não foi encontrado.');
        }

        if ($motivo = $this->ineligibilityReason($user)) {
            throw new DirectorException($motivo);
        }

        $cpf = $this->cpfOf($user);

        if ($cpf === null) {
            throw new DirectorException($user->name . ' está sem CPF válido no cadastro do Lara (usuário ou funcionário). '
                . 'Corrija o cadastro antes de escolhê-lo.');
        }

        return [
            'name' => $user->name,
            'cpf' => $cpf,
            'email' => $user->email,
            'phone' => $user->phone,
            'member_id' => null,
            'user_id' => $user->id,
            'director_area_id' => $area->id,
            'director_area_name' => $area->name,
            'director_role' => $mandato->role,
        ];
    }

    /**
     * Assinaturas de diretor ainda pendentes cujo diretor não responde mais
     * pela área — o que a tela da área mostra depois de uma troca de mandato.
     * O documento congelado não troca de signatário: cancela-se e emite-se
     * outro.
     *
     * @return Collection<int, SignatureSigner>
     */
    public function orphanedPending(SignatureDirectorArea $area): Collection
    {
        $vigentes = $area->currentHolders()->pluck('user_id')->all();

        return SignatureSigner::with('document')
            ->where('director_area_id', $area->id)
            ->where('status', SignatureSigner::STATUS_PENDING)
            ->whereNotIn('user_id', $vigentes === [] ? [0] : $vigentes)
            ->whereHas('document', fn($q) => $q->whereIn('status', SignatureDocument::STATUS_OPEN))
            ->orderByDesc('id')
            ->get();
    }
}
