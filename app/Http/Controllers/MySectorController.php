<?php

namespace App\Http\Controllers;

use App\Authorization\AccessChangeRejected;
use App\Authorization\AccessManager;
use App\Models\AccessAuditLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * "Meu setor": o coordenador cuida da equipe dos setores que coordena, sem
 * precisar da tela de Usuários.
 *
 * Pode:
 *   - colocar no setor, como colaborador, quem já tem usuário;
 *   - cadastrar gente nova, que já nasce colaboradora do setor;
 *   - tirar colaborador do setor.
 *
 * Não pode — isso é da tela de Usuários/Setores, de quem tem acesso total:
 *   - promover a coordenador, nem tirar outro coordenador (nem a si mesmo);
 *   - mexer no que o setor alcança (permissões) ou no acesso total;
 *   - editar dados, senha ou status de quem já existe; excluir usuário;
 *   - dar permissão individual.
 *
 * O que o coordenador concede é o setor inteiro: quem entra passa a alcançar
 * tudo o que o setor alcança. Por isso cada ação fica na auditoria.
 */
class MySectorController extends Controller
{
    public function __construct(private readonly AccessManager $access)
    {
    }

    public function index(Request $request)
    {
        $sectors = $request->user()->coordinatorSectors()->orderBy('name')->get();

        abort_if($sectors->isEmpty(), 403, 'Você não coordena nenhum setor.');

        if ($sectors->count() === 1) {
            return redirect()->route('my-sector.show', $sectors->first());
        }

        return view('my-sector.index', compact('sectors'));
    }

    public function show(Request $request, Sector $sector)
    {
        $this->authorizeCoordinator($request->user(), $sector);

        $sector->load(['users' => fn ($q) => $q->orderBy('name'), 'permissions']);
        $candidates = User::whereNotIn('id', $sector->users->pluck('id'))
            ->where('status_id', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'matricula', 'email']);

        return view('my-sector.show', [
            'sector' => $sector,
            'candidates' => $candidates,
            'coordinated' => $request->user()->coordinatorSectors()->orderBy('name')->get(),
        ]);
    }

    public function addMember(Request $request, Sector $sector)
    {
        $this->authorizeCoordinator($request->user(), $sector);

        $data = $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::findOrFail($data['user_id']);

        if ($sector->users()->where('users.id', $user->id)->exists()) {
            return back()->with('error', $user->name . ' já está no setor.');
        }

        try {
            $this->access->setMembership($request->user(), $sector, $user, Sector::ROLE_COLLABORATOR);
        } catch (AccessChangeRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $user->name . ' entrou no setor como colaborador.');
    }

    public function createUser(Request $request, Sector $sector)
    {
        $this->authorizeCoordinator($request->user(), $sector);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255',
            'matricula' => 'nullable|string|max:5',
            'cpf'       => 'nullable|string|max:14',
            'password'  => 'required|string|min:8|confirmed',
        ]);

        // Antes de criar, procura a pessoa: cadastro em dobro é o erro mais
        // provável de quem cadastra pela primeira vez. Achou? A tela oferece
        // colocá-la no setor, em vez de criar outra conta.
        if ($existing = $this->findExisting($data)) {
            return back()->withInput()->with('existing_user', [
                'id' => $existing->id,
                'name' => $existing->name,
                'email' => $existing->email,
                'in_sector' => $sector->users()->where('users.id', $existing->id)->exists(),
                // Conta excluída não volta por aqui: quem restaura é a tela de
                // Usuários (ou a TI).
                'deleted' => $existing->trashed(),
            ]);
        }

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'matricula' => ($data['matricula'] ?? null) ?: null,
            'cpf'       => $this->digits($data['cpf'] ?? null) ?: null,
            'password'  => Hash::make($data['password']),
            'status_id' => 1,
        ]);

        AccessAuditLog::record(AccessAuditLog::USER_CREATED, $user->id, $sector->id, null, ['via' => 'meu-setor']);

        try {
            $this->access->setMembership($request->user(), $sector, $user, Sector::ROLE_COLLABORATOR);
        } catch (AccessChangeRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Usuário "' . $user->name . '" cadastrado e colocado no setor.');
    }

    public function removeMember(Request $request, Sector $sector, User $user)
    {
        $actor = $request->user();
        $this->authorizeCoordinator($actor, $sector);

        $role = $sector->users()->where('users.id', $user->id)->first()?->pivot->role;

        if ($role === null) {
            return back()->with('error', $user->name . ' não está no setor.');
        }

        if ($role === Sector::ROLE_COORDINATOR) {
            return back()->with('error', 'Coordenador só sai do setor pela tela de Setores.');
        }

        try {
            $this->access->setMembership($actor, $sector, $user, null);
        } catch (AccessChangeRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $user->name . ' saiu do setor.');
    }

    private function authorizeCoordinator(User $user, Sector $sector): void
    {
        abort_unless($user->isCoordinatorOf($sector), 403, 'Você não coordena este setor.');
    }

    /** Mesma pessoa por e-mail, matrícula ou CPF (só os dígitos: há CPF com e sem máscara). */
    private function findExisting(array $data): ?User
    {
        $cpf = $this->digits($data['cpf'] ?? null);

        return User::withTrashed()
            ->where(function ($q) use ($data, $cpf) {
                $q->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])]);

                if (filled($data['matricula'] ?? null)) {
                    $q->orWhere('matricula', $data['matricula']);
                }

                if ($cpf !== '') {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?", [$cpf]);
                }
            })
            ->first();
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }
}
