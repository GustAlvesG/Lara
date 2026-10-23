<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\MemberAccountLog;
use App\Models\User;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Contas do site de locação, cuidadas pela equipe do clube (Reservas ›
 * Contas do site): "esqueci a senha", "meu login não entra", "meu e-mail
 * está errado".
 *
 *   - **Senha provisória**: o Lara gera, a tela mostra uma vez e a equipe
 *     passa para a pessoa, que troca depois pelo site. O site manda a senha
 *     já em SHA256 (é o que o login compara): a provisória é gravada como
 *     `Hash::make(sha256(provisória))`, igual a uma senha nova do site.
 *   - **E-mail e telefone**: corrigidos à mão ou trazidos do MultiClubes.
 *   - **Cadastro manual**: CPF + matrícula de sócio ativo no MultiClubes, com
 *     senha provisória.
 *   - **Duplicadas** (mesmo CPF; o login pega a de menor id): a equipe
 *     desativa a errada (exclusão lógica). Reservas e inscrições continuam
 *     ligadas a ela.
 *
 * Tudo vai para `member_account_logs`. Recusas como ValidationException.
 */
class SiteAccountService
{
    /** Sem os caracteres que se confundem ao ditar (0/O, 1/I/L). */
    private const PASSWORD_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const PASSWORD_LENGTH = 8;

    /** Tabelas que apontam para a conta, para mostrar o que está ligado a ela. */
    public const LINKED = [
        'schedules' => 'Reservas',
        'social_bookings' => 'Espaços sociais',
        'tournament_registrations' => 'Inscrições em torneios',
        'member_lighting_activations' => 'Iluminação',
    ];

    public function __construct(private MemberProfileLookup $lookup)
    {
    }

    /** Busca por CPF, matrícula, nome ou e-mail; inclui as desativadas. */
    public function search(?string $term): Builder
    {
        $term = trim((string) $term);
        $digits = Cpf::digits($term);

        return Member::withTrashed()
            ->when($term !== '', function (Builder $q) use ($term, $digits) {
                $q->where(function (Builder $w) use ($term, $digits) {
                    $w->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('title', $term);
                    if ($digits !== '') {
                        $w->orWhere('cpf', 'like', "{$digits}%");
                    }
                });
            })
            ->orderBy('name');
    }

    /** CPFs com mais de uma conta ativa. */
    public function duplicatedCpfs(): Collection
    {
        return Member::query()
            ->select('cpf', DB::raw('count(*) as total'))
            ->groupBy('cpf')
            ->havingRaw('count(*) > 1')
            ->pluck('total', 'cpf');
    }

    /**
     * O que explica "meu login não entra": as outras contas do CPF (e qual o
     * login usa), o cadastro no MultiClubes e as diferenças de dados.
     */
    public function diagnose(Member $member): array
    {
        $siblings = Member::withTrashed()->where('cpf', $member->cpf)->orderBy('id')->get();
        $loginAccount = $siblings->whereNull('deleted_at')->first();

        $profile = null;
        $mcError = null;
        try {
            $profile = $this->lookup->find((string) $member->cpf, (string) $member->title)
                ?? ($this->lookup->byCpf((string) $member->cpf)[0] ?? null);
        } catch (\Throwable $e) {
            $mcError = 'Não foi possível consultar o MultiClubes agora.';
        }

        return [
            'siblings' => $siblings,
            'login_account_id' => $loginAccount?->id,
            'profile' => $profile,
            'mc_error' => $mcError,
            'title_matches' => $profile && $profile->title === trim((string) $member->title),
            'legacy_password' => (bool) preg_match('/^[0-9a-f]{64}$/', (string) $member->password),
            'differences' => $profile ? $this->differences($member, $profile) : [],
            'linked' => $this->linked($siblings->pluck('id')->all()),
        ];
    }

    public function update(Member $member, ?string $email, ?string $phone, User $user): void
    {
        $before = ['email' => $member->email, 'telephone' => $member->telephone];
        $member->email = filled($email) ? trim($email) : null;
        $member->telephone = filled($phone) ? trim($phone) : null;
        $member->save();

        $this->log($member, $user, MemberAccountLog::ACTION_UPDATED, ['before' => $before, 'after' => ['email' => $member->email, 'telephone' => $member->telephone]]);
    }

    /** Copia nome, e-mail e telefone do MultiClubes (o que lá estiver preenchido). */
    public function pullFromMultiClubes(Member $member, User $user): void
    {
        $profile = $this->lookup->find((string) $member->cpf, (string) $member->title);

        if (! $profile) {
            $this->fail('member', 'Não há sócio de título ativo no MultiClubes com este CPF e esta matrícula.');
        }

        $before = ['name' => $member->name, 'email' => $member->email, 'telephone' => $member->telephone];
        $member->name = $profile->name ?: $member->name;
        $member->email = $profile->email ?? $member->email;
        $member->telephone = $profile->phone ?? $member->telephone;
        $member->save();

        $this->log($member, $user, MemberAccountLog::ACTION_PULLED, ['before' => $before, 'after' => ['name' => $member->name, 'email' => $member->email, 'telephone' => $member->telephone]]);
    }

    /** @return string a senha provisória, para mostrar uma vez */
    public function temporaryPassword(Member $member, User $user): string
    {
        if ($member->trashed()) {
            $this->fail('member', 'Esta conta está desativada: reative antes de gerar senha.');
        }

        $password = self::generatePassword();
        $member->password = self::siteHash($password);
        $member->save();

        $this->log($member, $user, MemberAccountLog::ACTION_PASSWORD);

        return $password;
    }

    /**
     * Cadastro manual: sócio ativo no MultiClubes, ainda sem conta.
     *
     * @return array{member: Member, password: string}
     */
    public function createManual(string $cpf, string $title, User $user): array
    {
        $cpf = Cpf::digits($cpf);
        $title = trim($title);

        if (! Cpf::isValid($cpf)) {
            $this->fail('cpf', 'CPF inválido.');
        }

        $existing = Member::withTrashed()->where('cpf', $cpf)->first();
        if ($existing) {
            $this->fail('cpf', 'Este CPF já tem conta no site' . ($existing->trashed() ? ' (desativada)' : '') . ': abra a conta para corrigir.');
        }

        $profile = $this->lookup->find($cpf, $title);
        if (! $profile) {
            $this->fail('title', 'Não há sócio de título ativo no MultiClubes com este CPF e esta matrícula.');
        }

        $password = self::generatePassword();

        $member = new Member();
        $member->forceFill([
            'name' => $profile->name,
            'cpf' => $cpf,
            'title' => $title,
            'birth_date' => $profile->birthDate?->toDateString(),
            'barcode' => $profile->barcode,
            'telephone' => $profile->phone,
            'email' => $profile->email,
            'password' => self::siteHash($password),
        ])->save();

        $this->log($member, $user, MemberAccountLog::ACTION_CREATED, ['title' => $title]);

        return ['member' => $member, 'password' => $password];
    }

    /** Desativa uma conta duplicada (só quando o CPF tem outra conta ativa). */
    public function deactivate(Member $member, User $user, string $reason): void
    {
        if ($member->trashed()) {
            return;
        }

        $others = Member::where('cpf', $member->cpf)->whereKeyNot($member->id)->count();
        if ($others === 0) {
            $this->fail('member', 'Só dá para desativar quando o CPF tem outra conta ativa: esta é a única, e a pessoa ficaria sem login.');
        }

        $member->delete();
        $this->log($member, $user, MemberAccountLog::ACTION_DEACTIVATED, ['reason' => $reason]);
    }

    public function restore(Member $member, User $user): void
    {
        if (! $member->trashed()) {
            return;
        }

        if (Member::where('cpf', $member->cpf)->exists()) {
            $this->fail('member', 'O CPF já tem uma conta ativa: desative aquela antes, para não voltar a duplicar.');
        }

        $member->restore();
        $this->log($member, $user, MemberAccountLog::ACTION_RESTORED);
    }

    /** Como o site grava a senha: o site manda o SHA256 da senha digitada. */
    public static function siteHash(string $plain): string
    {
        return Hash::make(hash('sha256', $plain));
    }

    public static function generatePassword(): string
    {
        $password = '';
        for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
            $password .= self::PASSWORD_ALPHABET[random_int(0, strlen(self::PASSWORD_ALPHABET) - 1)];
        }

        return $password;
    }

    /** @return array<string, array{site: ?string, mc: ?string}> */
    private function differences(Member $member, MemberProfile $profile): array
    {
        $pairs = [
            'name' => [$member->name, $profile->name],
            'email' => [$member->email, $profile->email],
            'telephone' => [$member->telephone, $profile->phone],
        ];

        $diff = [];
        foreach ($pairs as $field => [$site, $mc]) {
            if ($mc !== null && mb_strtolower(trim((string) $site)) !== mb_strtolower(trim((string) $mc))) {
                $diff[$field] = ['site' => $site, 'mc' => $mc];
            }
        }

        return $diff;
    }

    /**
     * @param  list<int>  $memberIds
     * @return array<int, array<string, int>> member_id => [rótulo => total]
     */
    private function linked(array $memberIds): array
    {
        $result = array_fill_keys($memberIds, []);

        foreach (self::LINKED as $table => $label) {
            if (! Schema::connection('mysql')->hasColumn($table, 'member_id')) {
                continue;
            }

            $counts = DB::connection('mysql')->table($table)
                ->whereIn('member_id', $memberIds)
                ->select('member_id', DB::raw('count(*) as total'))
                ->groupBy('member_id')
                ->pluck('total', 'member_id');

            foreach ($counts as $id => $total) {
                $result[$id][$label] = (int) $total;
            }
        }

        return $result;
    }

    private function log(Member $member, User $user, string $action, ?array $details = null): void
    {
        MemberAccountLog::create([
            'member_id' => $member->id,
            'user_id' => $user->id,
            'action' => $action,
            'details' => $details,
        ]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
