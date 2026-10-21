<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Models\SignatureDirectorArea;
use App\Models\SignatureDirectorAreaHolder;
use App\Models\User;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\Directors\DirectorException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Assinaturas → Diretoria: as áreas da diretoria e quem responde por cada uma
 * (titular e substitutos). É daqui que sai a lista "Diretor do clube" do
 * documento. Permissão `assinatura.diretoria` (rota).
 *
 * Mandato não se edita: "Encerrar" grava o fim, e pôr outro titular encerra o
 * anterior. Regras: DirectorAreaService.
 */
class DirectorAreaController extends Controller
{
    public function __construct(private DirectorAreaService $areas)
    {
    }

    public function index()
    {
        $areas = SignatureDirectorArea::with('holders')->orderByDesc('active')->orderBy('name')->get();

        return view('signature.directors.index', [
            'areas' => $areas,
            'orphans' => $areas->mapWithKeys(fn(SignatureDirectorArea $a) => [$a->id => $this->areas->orphanedPending($a)]),
            // Quem pode ser posto numa área: o setor Diretoria, ativo e com matrícula.
            'candidates' => User::directors()->where('status_id', 1)->whereNotNull('matricula')->get(['id', 'name', 'matricula']),
            'roles' => SignatureDirectorAreaHolder::ROLE_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'nome da área', 'description' => 'descrição']);

        try {
            $area = $this->areas->createArea($dados['name'], $dados['description'] ?? null, auth()->id());
        } catch (DirectorException $e) {
            return back()->withInput()->with('warning', $e->getMessage());
        }

        return redirect()->route('signature-directors.index')
            ->with('success', 'Área "' . $area->name . '" criada. Agora informe o titular e, se houver, os substitutos.');
    }

    public function update(Request $request, SignatureDirectorArea $signatureDirectorArea)
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ], [], ['name' => 'nome da área', 'description' => 'descrição']);

        try {
            $this->areas->updateArea(
                $signatureDirectorArea,
                $dados['name'],
                $dados['description'] ?? null,
                (bool) ($dados['active'] ?? false),
            );
        } catch (DirectorException $e) {
            return back()->withInput()->with('warning', $e->getMessage());
        }

        return redirect()->route('signature-directors.index')
            ->with('success', 'Área atualizada. Os documentos já emitidos continuam com o nome que tinham.');
    }

    public function assign(Request $request, SignatureDirectorArea $signatureDirectorArea)
    {
        $dados = $request->validate([
            'user_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(array_keys(SignatureDirectorAreaHolder::ROLE_LABELS))],
            'starts_on' => ['nullable', 'date'],
        ], [], ['user_id' => 'diretor', 'role' => 'papel', 'starts_on' => 'início']);

        $diretor = User::find($dados['user_id']);

        if (!$diretor) {
            return back()->with('warning', 'Usuário não encontrado.');
        }

        try {
            $mandato = $this->areas->assign(
                $signatureDirectorArea,
                $diretor,
                $dados['role'],
                isset($dados['starts_on']) ? Carbon::parse($dados['starts_on']) : null,
                auth()->id(),
                auth()->user()?->name,
            );
        } catch (DirectorException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()->route('signature-directors.index')
            ->with('success', $mandato->user_name . ' agora responde pela ' . $signatureDirectorArea->name
                . ' como ' . mb_strtolower($mandato->roleLabel()) . '.');
    }

    public function end(SignatureDirectorAreaHolder $signatureDirectorAreaHolder)
    {
        $this->areas->end($signatureDirectorAreaHolder, auth()->id(), auth()->user()?->name);

        return redirect()->route('signature-directors.index')
            ->with('success', 'Mandato de ' . $signatureDirectorAreaHolder->user_name . ' encerrado. '
                . 'Documentos pendentes da assinatura dele aparecem na área: cancele e emita de novo com quem assume.');
    }
}
