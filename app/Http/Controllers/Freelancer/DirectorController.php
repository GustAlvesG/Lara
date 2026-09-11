<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFreelancerDirectorRequest;
use App\Models\FreelancerDirector;
use App\Services\FreelancerDirectorService;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Cadastro da diretoria, operado pelo coordenador da Gerência: nome, e-mail
 * que recebe os códigos do lote e a imagem da assinatura que entra nos
 * contratos da redação 2 quando o lote é aprovado.
 *
 * Substitui as variáveis FREELANCER_DIRECTOR_* do `.env`. Toda gravação cria
 * um registro novo (ver `FreelancerDirector`), e a tela mostra o histórico.
 */
class DirectorController extends Controller
{
    /** Quantos cadastros anteriores a tela lista. */
    private const HISTORY_LIMIT = 15;

    public function __construct(private FreelancerDirectorService $directors)
    {
    }

    public function edit()
    {
        $history = FreelancerDirector::query()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT + 1)
            ->get();

        return view('freelancer.director.edit', [
            'current' => $history->first(),
            'history' => $history->slice(1)->values(),
            'maxKb' => UpdateFreelancerDirectorRequest::MAX_KB,
        ]);
    }

    public function update(UpdateFreelancerDirectorRequest $request)
    {
        try {
            $this->directors->register(
                $request->input('name'),
                $request->input('email'),
                $request->file('signature'),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('freelancer-director.edit')
            ->with('success', 'Cadastro da diretoria atualizado. Os próximos lotes vão para este e-mail e levam esta assinatura.');
    }

    /**
     * A imagem de um cadastro — atual ou antigo —, para a prévia da tela. Vem
     * do disco privado por esta rota, protegida pelo mesmo Gate da tela.
     */
    public function signatureImage(FreelancerDirector $director)
    {
        abort_unless($director->hasSignature(), 404);

        return Storage::disk(FreelancerDirector::DISK)->response($director->signature_path, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
