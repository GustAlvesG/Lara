<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\EmployeeCacheException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SignEmployeeCacheRequest;
use App\Models\Employee;
use App\Models\EmployeeCache;
use App\Services\EmployeeCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Tela de assinatura do funcionário — o único ponto do fluxo **fora** da sessão
 * do painel.
 *
 * O funcionário não é usuário do sistema: ele vem da importação do ponto e não
 * tem login. Ele se identifica pela matrícula ou pelo CPF, vê apenas os cachês
 * que a gerência já aprovou para ele, informa o horário que de fato cumpriu e
 * assina desenhando o traço.
 *
 * O que sustenta a assinatura não é uma senha, e sim o conjunto: o traço
 * guardado, o horário que só ele poderia informar e — quando esse horário
 * difere do previsto — a reconferência do coordenador e da gerência antes de o
 * dinheiro sair.
 */
class CacheSignatureController extends Controller
{
    /** Quem está assinando nesta sessão do navegador. */
    private const SESSION_KEY = 'employee_cache_signer';

    public function __construct(private EmployeeCacheService $caches)
    {
    }

    public function index(Request $request)
    {
        if ($this->signer($request)) {
            return redirect()->route('employee-caches.sign.list');
        }

        return view('employee.cache.sign-login');
    }

    /** Identificação por matrícula ou CPF — sem senha, sem PIN. */
    public function identify(Request $request)
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:30'],
        ], [], ['identifier' => 'matrícula ou CPF']);

        $employee = Employee::findByCodeOrCpf($request->input('identifier'));

        if (!$employee) {
            return back()->withInput()->with('error', 'Não encontramos esse funcionário. Confira a matrícula ou o CPF.');
        }

        $request->session()->put(self::SESSION_KEY, $employee->id);

        return redirect()->route('employee-caches.sign.list');
    }

    public function list(Request $request)
    {
        $employee = $this->signerOrFail($request);

        return view('employee.cache.sign-list', [
            'employee' => $employee,
            'pending' => EmployeeCache::where('employee_id', $employee->id)
                ->awaitingSignature()
                ->with('functionFreelancer:id,name')
                ->orderBy('event_date')
                ->get(),
            // O que já foi assinado fica à vista para o funcionário conferir o
            // que informou e em que pé está o pagamento.
            'signed' => EmployeeCache::where('employee_id', $employee->id)
                ->whereNotNull('employee_signed_at')
                ->with('functionFreelancer:id,name')
                ->orderByDesc('event_date')
                ->limit(10)
                ->get(),
        ]);
    }

    public function show(Request $request, EmployeeCache $cache)
    {
        $employee = $this->signerOrFail($request);
        $this->assertOwnedBy($cache, $employee);

        abort_unless($cache->canBeSignedByEmployee(), 403);

        $cache->load('functionFreelancer.cacheRates');

        return view('employee.cache.sign', [
            'employee' => $employee,
            'cache' => $cache,
        ]);
    }

    public function sign(SignEmployeeCacheRequest $request, EmployeeCache $cache)
    {
        $employee = $this->signerOrFail($request);
        $this->assertOwnedBy($cache, $employee);

        try {
            $path = $this->storeSignatureImage($cache, $request->input('signature'));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', 'Não foi possível salvar a assinatura. Desenhe novamente.');
        }

        try {
            $this->caches->signByEmployee(
                $cache,
                $request->input('start_time'),
                $request->input('end_time'),
                $path,
            );
        } catch (EmployeeCacheException $e) {
            // Assinatura não gravada: a imagem não deve ficar órfã no disco.
            Storage::disk('public')->delete($path);

            return back()->withInput()->with('error', $e->getMessage());
        }

        $cache->refresh();

        return redirect()->route('employee-caches.sign.list')->with(
            'success',
            $cache->hasDivergence()
                ? 'Assinado. Como o horário ficou diferente do previsto, o cachê passa pela conferência do coordenador e da gerência antes do pagamento.'
                : 'Assinado. O cachê seguiu para o financeiro.'
        );
    }

    public function logout(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('employee-caches.sign');
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    private function signer(Request $request): ?Employee
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? Employee::find($id) : null;
    }

    private function signerOrFail(Request $request): Employee
    {
        $employee = $this->signer($request);

        abort_unless($employee !== null, 403, 'Identifique-se para assinar.');

        return $employee;
    }

    /** Ninguém assina o cachê de outro — nem trocando o id na URL. */
    private function assertOwnedBy(EmployeeCache $cache, Employee $employee): void
    {
        abort_unless($cache->employee_id === $employee->id, 403);
    }

    /**
     * Guarda o PNG do traço. O caminho volta para entrar no MESMO save do
     * carimbo da assinatura — em dois saves, uma falha no segundo deixaria o
     * cachê assinado sem o traço, e assinatura não se repete.
     */
    private function storeSignatureImage(EmployeeCache $cache, string $dataUrl): string
    {
        if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            throw new \InvalidArgumentException('Formato de assinatura inválido.');
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false) {
            throw new \InvalidArgumentException('Assinatura inválida.');
        }

        $path = 'signatures/cache_' . $cache->id . '_employee_' . now()->format('YmdHis') . '.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
