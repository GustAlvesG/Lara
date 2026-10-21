<?php

namespace App\Http\Controllers;

use App\Authorization\AccessChangeRejected;
use App\Authorization\AccessManager;
use App\Authorization\Permissions;
use App\Models\AccessAuditLog;
use App\Models\Sector;
use App\Models\User;
use App\Services\Employees\EmployeeSectorSync;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Tela de Usuários (permissão `usuarios.gerenciar`).
 *
 * O acesso de cada pessoa tem três partes, e a tela de edição mostra as três
 * separadas: os setores (com o papel em cada um), as permissões individuais e,
 * só para leitura, o acesso efetivo — cada permissão e de onde ela veio. É a
 * aba que responde "por que fulano leva 403".
 *
 * Toda mudança de setor e de permissão passa pelo AccessManager: é ele quem
 * registra na auditoria e impede que alguém tranque o sistema.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly AccessManager $access,
        private readonly EmployeeSectorSync $employeeSectors,
    ) {
    }

    public function index()
    {
        $users = User::with(['sectors', 'directPermissions'])->get()->sortBy('name');

        return view('user.index', compact('users'));
    }

    public function create()
    {
        $sectors = Sector::orderBy('name')->get();

        return view('user.create', compact('sectors'));
    }

    public function store(Request $request)
    {
        $this->normalizeCpf($request);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users,email',
            'matricula'  => 'nullable|string|max:5|unique:users,matricula',
            'cpf'        => $this->cpfRules(),
            'password'   => 'required|string|min:8|confirmed',
            'pin'        => 'nullable|digits:6',
            'status'     => 'nullable|in:1,2',
            'sectors'    => 'nullable|array',
            'sectors.*'  => ['nullable', Rule::in([Sector::ROLE_COORDINATOR, Sector::ROLE_COLLABORATOR])],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'matricula' => ($data['matricula'] ?? null) ?: null,
            'cpf'       => ($data['cpf'] ?? null) ?: null,
            'password'  => Hash::make($data['password']),
            // Cast 'hashed' no model aplica o hash automaticamente.
            'pin'       => $request->filled('pin') ? $data['pin'] : null,
            'status_id' => $data['status'] ?? 1,
        ]);

        AccessAuditLog::record(AccessAuditLog::USER_CREATED, $user->id, null, null, ['via' => 'usuarios']);

        try {
            $this->access->syncUserSectors($request->user(), $user, array_filter($data['sectors'] ?? []));
        } catch (AccessChangeRejected $e) {
            return redirect()->route('users.edit', $user->id)->with('error', $e->getMessage());
        }

        // Matrícula de funcionário: o setor dele vale para o usuário também.
        $joined = $this->employeeSectors->syncUser($user);

        return redirect()->route('users.edit', $user->id)
            ->with('success', trim('Usuário "' . $user->name . '" criado. ' . $joined . ' Confira o acesso efetivo abaixo.'));
    }

    public function edit(User $id)
    {
        $user = $id->load(['sectors', 'directPermissions']);
        $sectors = Sector::orderBy('name')->get();

        return view('user.edit', [
            'user' => $user,
            'sectors' => $sectors,
            'catalog' => Permissions::grouped(),
            'direct' => $user->directPermissions->pluck('name')->all(),
            'effective' => $user->access(),
            'audit' => AccessAuditLog::with(['actor:id,name', 'sector:id,name'])
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(Request $request, User $id)
    {
        $user = $id;

        $this->normalizeCpf($request);

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'matricula' => 'nullable|string|max:5|unique:users,matricula,' . $user->id,
            'cpf'       => $this->cpfRules($user),
            'status'    => 'required|in:1,2',
            'password'  => 'nullable|string|min:8|confirmed',
            'pin'       => 'nullable|digits:6',
        ]);

        $user->name      = $request->input('name');
        $user->email     = $request->input('email');
        $user->matricula = $request->input('matricula') ?: null;
        $user->cpf       = $request->input('cpf') ?: null;
        $user->status_id = $request->input('status');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        // PIN em branco mantém o atual; enviar 6 dígitos redefine (cast 'hashed').
        if ($request->filled('pin')) {
            $user->pin = $request->input('pin');
        }

        $matriculaChanged = $user->isDirty('matricula');

        $user->save();

        $joined = $matriculaChanged ? $this->employeeSectors->syncUser($user) : null;

        return redirect()->route('users.edit', $user->id)
            ->with('success', trim('Dados de "' . $user->name . '" atualizados. ' . $joined));
    }

    /**
     * CPF do usuário, gravado só com dígitos. É o que a Assinatura Diretoria
     * imprime no documento do diretor (e o que o gov.br confere); sem ele, vale
     * o CPF do cadastro de funcionário pela matrícula.
     */
    private function normalizeCpf(Request $request): void
    {
        if ($request->has('cpf')) {
            $request->merge(['cpf' => Cpf::digits($request->input('cpf')) ?: null]);
        }
    }

    private function cpfRules(?User $user = null): array
    {
        return [
            'nullable',
            'digits:11',
            Rule::unique('users', 'cpf')->ignore($user?->id),
            function ($attribute, $value, $fail) {
                if (!Cpf::isValid($value)) {
                    $fail('CPF inválido.');
                }
            },
        ];
    }

    /** Setores do usuário: sectors[sector_id] = coordinator | collaborator | '' (fora). */
    public function updateSectors(Request $request, User $id)
    {
        $data = $request->validate([
            'sectors'   => 'nullable|array',
            'sectors.*' => ['nullable', Rule::in([Sector::ROLE_COORDINATOR, Sector::ROLE_COLLABORATOR])],
        ]);

        // Todo setor listado na tela vem no formulário; o vazio quer dizer "fora".
        $roles = collect($data['sectors'] ?? [])
            ->mapWithKeys(fn ($role, $sectorId) => [(int) $sectorId => $role ?: null])
            ->all();

        try {
            $this->access->syncUserSectors($request->user(), $id, $roles);
        } catch (AccessChangeRejected $e) {
            return redirect()->route('users.edit', $id->id)->with('error', $e->getMessage());
        }

        return redirect()->route('users.edit', $id->id)->with('success', 'Setores atualizados.');
    }

    public function updatePermissions(Request $request, User $id)
    {
        $data = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);

        try {
            $this->access->syncUserPermissions($request->user(), $id, $data['permissions'] ?? []);
        } catch (AccessChangeRejected $e) {
            return redirect()->route('users.edit', $id->id)->with('error', $e->getMessage());
        }

        return redirect()->route('users.edit', $id->id)->with('success', 'Permissões individuais atualizadas.');
    }

    public function destroy(Request $request, User $id)
    {
        $user = $id;

        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'Você não pode excluir sua própria conta.');
        }

        // Excluir é soft delete: o vínculo com o setor continuaria contando para
        // a trava "setor de acesso total não fica vazio". Tirar dos setores
        // antes, pelo AccessManager, aplica a trava e deixa o registro.
        try {
            $this->access->syncUserSectors(
                $request->user(),
                $user,
                $user->sectors()->pluck('sectors.id')->mapWithKeys(fn ($sid) => [$sid => null])->all()
            );
        } catch (AccessChangeRejected $e) {
            return redirect()->route('users.index')->with('error', $e->getMessage());
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuário "' . $userName . '" excluído com sucesso.');
    }
}
