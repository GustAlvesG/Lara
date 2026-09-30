<?php

namespace App\Http\Controllers;

use App\Authorization\AccessChangeRejected;
use App\Authorization\AccessManager;
use App\Authorization\Permissions;
use App\Models\AccessAuditLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tela de Setores (permissão `setores.gerenciar`): dados do setor, membros,
 * o que o setor alcança (permissões do catálogo, para todos ou só para os
 * coordenadores) e a marca de acesso total.
 *
 * Toda mudança de acesso passa pelo AccessManager — auditoria e travas.
 */
class SectorController extends Controller
{
    public function __construct(private readonly AccessManager $access)
    {
    }

    public function index()
    {
        $sectors = Sector::withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('sector.index', compact('sectors'));
    }

    public function create()
    {
        return view('sector.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:sectors,name',
            'description' => 'nullable|string|max:500',
        ]);

        $sector = Sector::create([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        AccessAuditLog::record(AccessAuditLog::SECTOR_CREATED, null, $sector->id, null, ['name' => $sector->name]);

        return redirect()->route('sectors.show', $sector->id)
            ->with('success', 'Setor "' . $sector->name . '" criado. Agora escolha o que ele alcança.');
    }

    public function show(Sector $id)
    {
        $sector = $id->load(['users' => fn ($q) => $q->orderBy('name'), 'permissions']);

        $memberIds = $sector->users->pluck('id');
        $users = User::whereNotIn('id', $memberIds)->orderBy('name')->get(['id', 'name', 'matricula']);

        return view('sector.show', [
            'sector' => $sector,
            'users' => $users,
            'catalog' => Permissions::grouped(),
            'granted' => $sector->permissions
                ->mapWithKeys(fn ($p) => [$p->name => (bool) $p->pivot->coordinators_only])
                ->all(),
            'audit' => AccessAuditLog::with(['actor:id,name', 'user:id,name'])
                ->where('sector_id', $sector->id)
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(Request $request, Sector $id)
    {
        $sector = $id;

        $request->validate([
            'name'        => 'required|string|max:255|unique:sectors,name,' . $sector->id,
            'description' => 'nullable|string|max:500',
            'full_access' => 'nullable|boolean',
        ]);

        $sector->update([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        try {
            $this->access->setFullAccess($request->user(), $sector, $request->boolean('full_access'));
        } catch (AccessChangeRejected $e) {
            return redirect()->route('sectors.show', $sector->id)->with('error', $e->getMessage());
        }

        return redirect()->route('sectors.show', $sector->id)
            ->with('success', 'Setor "' . $sector->name . '" atualizado com sucesso.');
    }

    /**
     * permissions[nome] = 'all' | 'coordinators' | '' (sem). O formulário
     * manda todas as linhas do catálogo, então o que vier vazio sai do setor.
     */
    public function updatePermissions(Request $request, Sector $id)
    {
        $data = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => ['nullable', Rule::in(['all', 'coordinators'])],
        ]);

        $grants = [];
        foreach ($data['permissions'] ?? [] as $name => $who) {
            if ($who && Permissions::exists($name)) {
                $grants[$name] = $who === 'coordinators';
            }
        }

        try {
            $this->access->syncSectorPermissions($request->user(), $id, $grants);
        } catch (AccessChangeRejected $e) {
            return redirect()->route('sectors.show', $id->id)->with('error', $e->getMessage());
        }

        return redirect()->route('sectors.show', $id->id)->with('success', 'Permissões do setor atualizadas.');
    }

    public function destroy(Sector $id)
    {
        $sector = $id;

        if ($sector->users()->count() > 0) {
            return redirect()->route('sectors.index')
                ->with('error', 'Não é possível excluir o setor "' . $sector->name . '" pois possui membros vinculados.');
        }

        $sectorName = $sector->name;
        AccessAuditLog::record(AccessAuditLog::SECTOR_DELETED, null, $sector->id, null, [
            'name' => $sectorName,
            'permissions' => $sector->permissions()->pluck('name')->all(),
        ]);
        $sector->delete();

        return redirect()->route('sectors.index')
            ->with('success', 'Setor "' . $sectorName . '" excluído com sucesso.');
    }

    /** Coloca alguém no setor, ou troca o papel de quem já está. */
    public function addUser(Request $request, Sector $id)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => ['required', Rule::in([Sector::ROLE_COORDINATOR, Sector::ROLE_COLLABORATOR])],
        ]);

        try {
            $this->access->setMembership($request->user(), $id, User::findOrFail($data['user_id']), $data['role']);
        } catch (AccessChangeRejected $e) {
            return redirect()->route('sectors.show', $id->id)->with('error', $e->getMessage());
        }

        return redirect()->route('sectors.show', $id->id)
            ->with('success', 'Membro atualizado no setor.');
    }

    public function removeUser(Request $request, Sector $id, int $userId)
    {
        try {
            $this->access->setMembership($request->user(), $id, User::findOrFail($userId), null);
        } catch (AccessChangeRejected $e) {
            return redirect()->route('sectors.show', $id->id)->with('error', $e->getMessage());
        }

        return redirect()->route('sectors.show', $id->id)
            ->with('success', 'Usuário removido do setor com sucesso.');
    }

    /** Registro das mudanças de acesso — todos os setores e usuários. */
    public function audit(Request $request)
    {
        $logs = AccessAuditLog::with(['actor:id,name', 'user:id,name', 'sector:id,name'])
            ->latest('created_at')
            ->paginate(50);

        return view('sector.audit', compact('logs'));
    }
}
