<?php

namespace App\Http\Controllers\EmailCampaign;

use App\Exceptions\EmailCampaignException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemberMailingRequest;
use App\Models\EmailCampaign\EmailCampaign;
use App\Services\EmailCampaign\MultiClubesRecipientSource;
use App\Services\EmailCampaign\RecipientImporter;
use App\Services\MultiClubes\MemberMailingDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lista de destinatários a partir dos sócios do MultiClubes (permissão
 * `disparo-emails.multiclubes`, além da `disparo-emails`, no corte da rota).
 *
 * A tela da campanha não consulta o MultiClubes ao abrir: os tipos de título
 * e a contagem vêm destas rotas, sob demanda. Se o MultiClubes não responder,
 * a campanha continua usável pelo .csv/.xlsx.
 */
class MultiClubesRecipientController extends Controller
{
    private const UNAVAILABLE = 'Não foi possível consultar o MultiClubes agora. Tente de novo em instantes ou importe a lista por planilha.';

    public function __construct(private readonly MemberMailingDirectory $directory)
    {
    }

    public function titleTypes(): JsonResponse
    {
        try {
            return response()->json(['types' => $this->directory->titleTypes()]);
        } catch (Throwable $e) {
            Log::warning('Disparo de E-mails: falha ao listar tipos de título no MultiClubes', ['error' => $e->getMessage()]);

            return response()->json(['message' => self::UNAVAILABLE], 503);
        }
    }

    public function count(MemberMailingRequest $request): JsonResponse
    {
        try {
            return response()->json($this->directory->count($request->filter()));
        } catch (Throwable $e) {
            Log::warning('Disparo de E-mails: falha ao contar sócios no MultiClubes', ['error' => $e->getMessage()]);

            return response()->json(['message' => self::UNAVAILABLE], 503);
        }
    }

    public function import(MemberMailingRequest $request, EmailCampaign $campaign, MultiClubesRecipientSource $source, RecipientImporter $importer): RedirectResponse
    {
        $filter = $request->filter();

        try {
            $list = $source->read($filter);
            $typeNames = $filter->titleTypes === []
                ? []
                : array_column($this->directory->titleTypes(), 'name', 'id');
        } catch (Throwable $e) {
            Log::warning('Disparo de E-mails: falha ao ler sócios do MultiClubes', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', self::UNAVAILABLE);
        }

        if ($list['rows'] === []) {
            $message = $list['members'] === 0
                ? 'Nenhum sócio do MultiClubes atende a esses filtros.'
                : "Os {$list['members']} sócio(s) desses filtros não têm e-mail no MultiClubes.";

            return back()->withInput()->with('error', $message);
        }

        try {
            $result = $importer->importList($campaign, $list, $filter->describe($typeNames), $request->input('mode') === 'replace');
        } catch (EmailCampaignException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = "Sócios do MultiClubes importados: {$result['added']} destinatário(s) novo(s); a campanha tem {$result['total']} no total.";

        if ($result['duplicates']) {
            $message .= " {$result['duplicates']} e-mail(s) repetido(s) ignorado(s) (o mesmo e-mail em mais de um sócio, ou já na lista).";
        }

        if ($list['without_email']) {
            $message .= " {$list['without_email']} sócio(s) sem e-mail no MultiClubes ficaram de fora.";
        }

        if ($result['over_limit']) {
            $message .= " {$result['over_limit']} ficaram de fora por passar do limite de " . config('email_campaigns.max_recipients') . ' por campanha.';
        }

        $redirect = redirect()->route('email-campaigns.show', $campaign)->with('success', $message);

        if ($result['invalid']) {
            $redirect->with('import_rejected', ['count' => $result['invalid'], 'samples' => $result['samples']]);
        }

        return $redirect;
    }
}
