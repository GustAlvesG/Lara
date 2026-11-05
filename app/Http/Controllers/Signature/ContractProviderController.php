<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Models\SignatureContract;
use App\Models\SignatureContractParty;
use App\Services\Signature\SignatureFieldTypes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contratos → Prestadores (05/11/2026, pedido da apresentação do módulo).
 *
 * O cadastro de contratadas e fiadores (`signature_contract_parties`) já
 * existia, alimentado ao salvar contrato; agora tem tela própria para
 * cadastrar um prestador sem contrato, editar e inativar. Decisões do
 * usuário: quem acessa Contratos cuida do cadastro (sem permissão nova);
 * não se exclui, só se inativa; os mesmos campos da parte do contrato.
 *
 * O editor do contrato busca só os ativos. Mudar o prestador aqui não muda
 * contrato já redigido: a parte fica copiada dentro do contrato.
 */
class ContractProviderController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('manageProviders', SignatureContract::class);

        $busca = trim((string) $request->query('q', ''));
        $situacao = in_array($request->query('situacao'), ['ativos', 'inativos', 'todos'], true) ? $request->query('situacao') : 'ativos';

        $prestadores = SignatureContractParty::query()
            ->when($situacao === 'ativos', fn($q) => $q->where('active', true))
            ->when($situacao === 'inativos', fn($q) => $q->where('active', false))
            ->search($busca)
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('signature.contracts.providers.index', [
            'providers' => $prestadores,
            'busca' => $busca,
            'situacao' => $situacao,
        ]);
    }

    public function create()
    {
        $this->authorize('manageProviders', SignatureContract::class);

        return view('signature.contracts.providers.form', ['provider' => new SignatureContractParty(['kind' => 'pj'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('manageProviders', SignatureContract::class);

        $dados = $this->validated($request);

        $prestador = new SignatureContractParty();
        $this->fill($prestador, $dados, $request->user()->name);
        $prestador->created_by_name = $request->user()->name;
        $prestador->save();

        return redirect()->route('contract-providers.index')
            ->with('success', 'Prestador "' . $prestador->name . '" cadastrado. Ele já aparece na busca do contrato.');
    }

    public function edit(SignatureContractParty $signatureContractParty)
    {
        $this->authorize('manageProviders', SignatureContract::class);

        return view('signature.contracts.providers.form', [
            'provider' => $signatureContractParty,
            // Onde o prestador aparece (busca pelo documento, como a lista de Contratos).
            'contracts' => SignatureContract::query()
                ->where('parties_search', 'like', '%' . $signatureContractParty->document . '%')
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'number', 'title', 'status', 'sector_id', 'sector_name']),
        ]);
    }

    public function update(Request $request, SignatureContractParty $signatureContractParty)
    {
        $this->authorize('manageProviders', SignatureContract::class);

        $dados = $this->validated($request, $signatureContractParty);

        $this->fill($signatureContractParty, $dados, $request->user()->name);
        $signatureContractParty->save();

        return redirect()->route('contract-providers.index')
            ->with('success', 'Prestador "' . $signatureContractParty->name . '" atualizado. Os contratos já redigidos não mudam.');
    }

    /** Inativar (some da busca do contrato) ou reativar. Não se exclui (decisão do usuário). */
    public function toggle(Request $request, SignatureContractParty $signatureContractParty)
    {
        $this->authorize('manageProviders', SignatureContract::class);

        $signatureContractParty->forceFill([
            'active' => !$signatureContractParty->active,
            'updated_by_name' => $request->user()->name,
        ])->save();

        return back()->with('success', $signatureContractParty->active
            ? 'Prestador "' . $signatureContractParty->name . '" reativado.'
            : 'Prestador "' . $signatureContractParty->name . '" inativado: não aparece mais na busca do contrato.');
    }

    /**
     * As mesmas regras do contratado na criação do contrato
     * (ContractController::store), com o documento único no cadastro.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SignatureContractParty $atual = null): array
    {
        $pf = $request->input('kind') === 'pf';

        // Só os caracteres: é como o cadastro guarda (o CNPJ alfanumérico tem letras).
        $request->merge(['document' => strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $request->input('document')))]);

        return $request->validate([
            'kind' => ['required', 'in:pj,pf'],
            'gender' => ['nullable', 'in:m,f'],
            'name' => ['required', 'string', 'max:200'],
            'document' => [
                'required', 'string', 'max:20',
                Rule::unique('signature_contract_parties', 'document')->ignore($atual?->id),
                function ($attr, $valor, $fail) use ($pf) {
                    [, $erro] = SignatureFieldTypes::parse(['type' => $pf ? SignatureFieldTypes::CPF : SignatureFieldTypes::CNPJ], (string) $valor);

                    if ($erro !== null) {
                        $fail(($pf ? 'CPF' : 'CNPJ') . ' inválido.');
                    }
                },
            ],
            'address' => ['required', 'string', 'max:300'],
            'details' => ['nullable', 'string', 'max:500'],
            'rep_name' => ['exclude_if:kind,pf', 'required_if:kind,pj', 'nullable', 'string', 'max:200'],
            'rep_cpf' => ['exclude_if:kind,pf', 'required_if:kind,pj', 'nullable', 'string', 'max:14', function ($attr, $valor, $fail) {
                [, $erro] = SignatureFieldTypes::parse(['type' => SignatureFieldTypes::CPF], (string) $valor);

                if ($erro !== null) {
                    $fail('CPF do representante inválido.');
                }
            }],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'kind.required' => 'Diga se o prestador é empresa (CNPJ) ou pessoa física (CPF).',
            'name.required' => 'Informe o nome ou a razão social.',
            'document.required' => 'Informe o CNPJ ou o CPF.',
            'document.unique' => 'Já há um prestador com este documento no cadastro: busque por ele na lista.',
            'address.required' => 'Informe o endereço.',
            'rep_name.required_if' => 'Informe quem assina pela empresa.',
            'rep_cpf.required_if' => 'Informe o CPF de quem assina pela empresa.',
            'email.email' => 'E-mail inválido.',
        ]);
    }

    /** @param  array<string, mixed>  $dados */
    private function fill(SignatureContractParty $prestador, array $dados, ?string $userName): void
    {
        $pf = $dados['kind'] === 'pf';
        $limpa = fn($v) => trim((string) $v);

        $prestador->fill([
            'kind' => $dados['kind'],
            'document' => $dados['document'],
            'name' => $limpa($dados['name']),
            'data' => [
                'gender' => $pf && ($dados['gender'] ?? 'm') === 'f' ? 'f' : 'm',
                'address' => $limpa($dados['address']),
                'details' => $limpa($dados['details'] ?? ''),
                'rep_name' => $pf ? '' : $limpa($dados['rep_name'] ?? ''),
                // CPF só dígitos, como o ContractContent guarda.
                'rep_cpf' => $pf ? '' : (string) preg_replace('/\D/', '', (string) ($dados['rep_cpf'] ?? '')),
                'email' => mb_strtolower($limpa($dados['email'] ?? '')),
                'phone' => $limpa($dados['phone'] ?? ''),
            ],
            'updated_by_name' => $userName,
        ]);
    }
}
