<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\Members\SiteAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reservas › Contas do site (permissão `reservas.contas-site`): a equipe
 * resolve "esqueci a senha", "meu login não entra" e "meu e-mail está
 * errado" das contas do site de locação. Regras em SiteAccountService.
 */
class SiteAccountController extends Controller
{
    public function __construct(private SiteAccountService $accounts)
    {
    }

    public function index(Request $request): View
    {
        $duplicated = $this->accounts->duplicatedCpfs();
        $onlyDuplicated = $request->boolean('duplicadas');
        $onlyAdmins = $request->boolean('administradores');

        $members = $this->accounts->search($request->input('q'))
            ->when($onlyDuplicated, fn ($q) => $q->whereIn('cpf', $duplicated->keys()))
            ->when($onlyAdmins, fn ($q) => $q->where('site_role', Member::ROLE_ADMIN))
            ->paginate(25)
            ->withQueryString();

        return view('site-accounts.index', [
            'members' => $members,
            'duplicated' => $duplicated,
            'onlyDuplicated' => $onlyDuplicated,
            'onlyAdmins' => $onlyAdmins,
            'searched' => filled($request->input('q')) || $onlyDuplicated || $onlyAdmins,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cpf' => 'required|string|max:20',
            'title' => 'required|string|max:20',
        ], [], ['cpf' => 'CPF', 'title' => 'matrícula']);

        $result = $this->accounts->createManual($data['cpf'], $data['title'], $request->user());

        return redirect()->route('site-accounts.show', $result['member']->id)
            ->with('success', 'Conta criada. Passe a senha provisória para a pessoa: ela entra com o CPF e troca a senha no site.')
            ->with('temporary_password', $result['password']);
    }

    public function show(int $member): View
    {
        $account = Member::withTrashed()->findOrFail($member);

        return view('site-accounts.show', [
            'member' => $account,
            'diagnosis' => $this->accounts->diagnose($account),
            'logs' => $account->accountLogs()->with('user')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, int $member): RedirectResponse
    {
        $account = Member::withTrashed()->findOrFail($member);

        $data = $request->validate([
            'email' => 'nullable|email|max:255',
            'telephone' => 'nullable|string|max:30',
        ], [], ['email' => 'e-mail', 'telephone' => 'telefone']);

        $this->accounts->update($account, $data['email'] ?? null, $data['telephone'] ?? null, $request->user());

        return $this->back($account, 'Dados corrigidos.');
    }

    public function pull(Request $request, int $member): RedirectResponse
    {
        $account = Member::withTrashed()->findOrFail($member);

        $this->accounts->pullFromMultiClubes($account, $request->user());

        return $this->back($account, 'Nome, e-mail e telefone copiados do MultiClubes.');
    }

    public function password(Request $request, int $member): RedirectResponse
    {
        $account = Member::withTrashed()->findOrFail($member);

        $password = $this->accounts->temporaryPassword($account, $request->user());

        return $this->back($account, 'Senha provisória gerada. Passe para a pessoa: ela entra com o CPF e troca a senha no site.')
            ->with('temporary_password', $password);
    }

    public function destroy(Request $request, int $member): RedirectResponse
    {
        $account = Member::withTrashed()->findOrFail($member);

        $data = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Diga por que está desativando.']);
        $this->accounts->deactivate($account, $request->user(), $data['reason']);

        return $this->back($account, 'Conta desativada. As reservas e inscrições dela continuam guardadas.');
    }

    public function restore(Request $request, int $member): RedirectResponse
    {
        $account = Member::withTrashed()->findOrFail($member);

        $this->accounts->restore($account, $request->user());

        return $this->back($account, 'Conta reativada.');
    }

    /** Perfil no site — permissão própria (`reservas.contas-site.perfil`, na rota). */
    public function role(Request $request, int $member): RedirectResponse
    {
        $account = Member::withTrashed()->findOrFail($member);

        $data = $request->validate([
            'site_role' => 'required|in:' . implode(',', array_keys(Member::ROLE_LABELS)),
        ], [], ['site_role' => 'perfil']);

        $this->accounts->setRole($account, $data['site_role'], $request->user());

        return $this->back($account, $data['site_role'] === Member::ROLE_ADMIN
            ? 'Perfil trocado para Administrador: a pessoa já abre os dashboards no site.'
            : 'Perfil trocado para Usuário: o acesso aos dashboards foi cortado.');
    }

    private function back(Member $member, string $message): RedirectResponse
    {
        return redirect()->route('site-accounts.show', $member->id)->with('success', $message);
    }
}
