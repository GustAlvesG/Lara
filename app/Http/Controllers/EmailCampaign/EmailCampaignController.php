<?php

namespace App\Http\Controllers\EmailCampaign;

use App\Exceptions\EmailCampaignException;
use App\Exceptions\SpreadsheetImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmailCampaignRequest;
use App\Models\EmailCampaign\EmailCampaign;
use App\Models\EmailCampaign\EmailCampaignRecipient;
use App\Services\EmailCampaign\CampaignImageStore;
use App\Services\EmailCampaign\CampaignRenderer;
use App\Services\EmailCampaign\CampaignSender;
use App\Services\EmailCampaign\EmailCampaignService;
use App\Services\EmailCampaign\RecipientImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Disparo de E-mails (permissão `disparo-emails`, no corte da rota).
 *
 * Esta tela monta a campanha (dados, HTML, lista) e muda a situação dela;
 * quem envia é o `email-campaigns:send` do scheduler, a cada minuto. Por isso
 * a pessoa pode fechar a janela depois de clicar em Enviar: o andamento
 * (`progress`) é só para acompanhar. Ver docs/funcionalidades/disparo-emails.md.
 */
class EmailCampaignController extends Controller
{
    public function __construct(
        private readonly EmailCampaignService $campaigns,
        private readonly CampaignRenderer $renderer,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status']);

        $query = EmailCampaign::with('author')
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->where('status', EmailCampaignRecipient::STATUS_SENT),
                'recipients as failed_count' => fn ($q) => $q->where('status', EmailCampaignRecipient::STATUS_FAILED),
            ])
            ->latest('id');

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('subject', 'like', "%{$term}%"));
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return view('email-campaigns.index', [
            'campaigns' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => EmailCampaign::STATUS_LABELS,
        ]);
    }

    public function create()
    {
        return view('email-campaigns.form', [
            'campaign' => new EmailCampaign([
                'from_address' => config('email_campaigns.from_addresses')[0] ?? null,
                'from_name' => config('mail.from.name'),
            ]),
            'fromAddresses' => config('email_campaigns.from_addresses'),
            'sample' => ['email' => 'fulano@exemplo.com.br', 'nome' => 'Fulano de Tal', 'primeiro_nome' => 'Fulano'],
        ]);
    }

    public function store(EmailCampaignRequest $request): RedirectResponse
    {
        $campaign = EmailCampaign::create($request->validated() + [
            'status' => EmailCampaign::STATUS_DRAFT,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('email-campaigns.show', $campaign)
            ->with('success', 'Campanha salva como rascunho. Agora importe a lista de destinatários.');
    }

    public function show(Request $request, EmailCampaign $campaign)
    {
        $filters = $request->only(['q', 'situacao']);

        $recipients = $campaign->recipients()->orderBy('id');

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $recipients->where(fn ($q) => $q->where('email', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        }

        if (filled($filters['situacao'] ?? null)) {
            $recipients->where('status', $filters['situacao']);
        }

        return view('email-campaigns.show', [
            'campaign' => $campaign->load('author'),
            'counts' => $campaign->counts(),
            'recipients' => $recipients->paginate(50)->withQueryString(),
            'filters' => $filters,
            'unknownVariables' => $this->renderer->unknownVariables($campaign),
            'perMinute' => (int) config('email_campaigns.per_minute'),
            'maxRecipients' => (int) config('email_campaigns.max_recipients'),
        ]);
    }

    public function edit(EmailCampaign $campaign)
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('email-campaigns.show', $campaign)
                ->with('error', 'A campanha só pode ser editada em rascunho ou pausada. Para mandar outra versão, use Duplicar.');
        }

        return view('email-campaigns.form', [
            'campaign' => $campaign,
            'fromAddresses' => $this->fromAddressesFor($campaign),
            'sample' => $this->renderer->sampleVariables($campaign),
        ]);
    }

    public function update(EmailCampaignRequest $request, EmailCampaign $campaign): RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('email-campaigns.show', $campaign)
                ->with('error', 'A campanha só pode ser editada em rascunho ou pausada.');
        }

        $campaign->update($request->validated());

        return redirect()->route('email-campaigns.show', $campaign)->with('success', 'Campanha atualizada.');
    }

    public function destroy(EmailCampaign $campaign): RedirectResponse
    {
        if (! $campaign->canDelete()) {
            return back()->with('error', 'Cancele ou espere terminar o envio antes de excluir a campanha.');
        }

        $campaign->delete();

        return redirect()->route('email-campaigns.index')->with('success', "Campanha \"{$campaign->name}\" excluída.");
    }

    /**
     * O e-mail como o primeiro destinatário vai ver, para o iframe da tela.
     * Sem script nenhum: o HTML é de quem escreveu, mas roda dentro do Lara.
     */
    public function preview(EmailCampaign $campaign)
    {
        $rendered = $this->renderer->render($campaign, $this->renderer->sampleVariables($campaign), embed: false);

        return response($rendered['html'])->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "script-src 'none'; object-src 'none'; frame-ancestors 'self'",
        ]);
    }

    public function progress(EmailCampaign $campaign): JsonResponse
    {
        return response()->json([
            'status' => $campaign->status,
            'status_label' => $campaign->statusLabel(),
            'active' => $campaign->isActive(),
            'counts' => $campaign->counts(),
            'last_error' => $campaign->last_error,
            'finished_at' => $campaign->finished_at?->format('d/m/Y H:i'),
        ]);
    }

    public function sendTest(Request $request, EmailCampaign $campaign, CampaignSender $sender): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email', 'max:255']], [], ['test_email' => 'e-mail de teste']);

        try {
            $sender->sendTest($campaign, $data['test_email']);
        } catch (Throwable $e) {
            Log::warning('Disparo de E-mails: e-mail de teste falhou.', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'O e-mail de teste não saiu: ' . $e->getMessage());
        }

        return back()->with('success', "E-mail de teste enviado para {$data['test_email']}, com os dados do primeiro destinatário da lista.");
    }

    public function start(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'when' => ['required', 'in:now,schedule'],
            'scheduled_at' => ['required_if:when,schedule', 'nullable', 'date', 'after:now'],
        ], [
            'scheduled_at.after' => 'Escolha uma data e hora no futuro.',
        ], ['scheduled_at' => 'data e hora do envio']);

        $scheduledAt = $data['when'] === 'schedule' ? Carbon::parse($data['scheduled_at']) : null;

        return $this->attempt($campaign, fn () => $this->campaigns->start($campaign, $scheduledAt), $scheduledAt
            ? 'Envio agendado para ' . $scheduledAt->format('d/m/Y \à\s H:i') . '. Pode fechar esta tela: o envio começa sozinho.'
            : 'Envio iniciado. Os e-mails saem aos poucos, a partir do próximo minuto. Pode fechar esta tela: o envio continua.');
    }

    public function pause(EmailCampaign $campaign): RedirectResponse
    {
        return $this->attempt($campaign, fn () => $this->campaigns->pause($campaign), 'Envio pausado. Quem ainda não recebeu fica na fila até você retomar.');
    }

    public function resume(EmailCampaign $campaign): RedirectResponse
    {
        return $this->attempt($campaign, fn () => $this->campaigns->resume($campaign), 'Envio retomado.');
    }

    public function cancel(EmailCampaign $campaign): RedirectResponse
    {
        return $this->attempt($campaign, fn () => $this->campaigns->cancel($campaign), 'Campanha cancelada. Quem ainda estava na fila não vai receber.');
    }

    public function unschedule(EmailCampaign $campaign): RedirectResponse
    {
        return $this->attempt($campaign, fn () => $this->campaigns->unschedule($campaign), 'Agendamento desfeito. A campanha voltou para rascunho.');
    }

    public function retryFailed(EmailCampaign $campaign): RedirectResponse
    {
        $count = 0;

        return $this->attempt($campaign, function () use ($campaign, &$count) {
            $count = $this->campaigns->retryFailed($campaign);
        }, fn () => "{$count} destinatário(s) voltaram para a fila de envio.");
    }

    public function duplicate(EmailCampaign $campaign): RedirectResponse
    {
        $copy = $this->campaigns->duplicate($campaign, Auth::id());

        return redirect()->route('email-campaigns.show', $copy)
            ->with('success', 'Campanha duplicada em rascunho, com a mesma lista. Ajuste o que precisar antes de enviar.');
    }

    public function importRecipients(Request $request, EmailCampaign $campaign, RecipientImporter $importer): RedirectResponse
    {
        // Pela extensão, não pelo conteúdo: CSV de outro sistema chega como
        // text/plain, octet-stream… O arquivo só é lido, nunca guardado, e o
        // leitor recusa o que não conseguir abrir.
        $request->validate([
            'list' => ['required', 'file', 'extensions:csv,txt,xlsx,xls', 'max:20480'],
            'mode' => ['required', 'in:append,replace'],
        ], [
            'list.extensions' => 'Envie a lista em .csv ou .xlsx.',
            'list.max' => 'A lista pode ter no máximo 20 MB.',
        ], ['list' => 'lista']);

        $file = $request->file('list');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');

        try {
            $result = $importer->import($campaign, $file->getRealPath(), $extension, $file->getClientOriginalName(), $request->input('mode') === 'replace');
        } catch (SpreadsheetImportException|EmailCampaignException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = "Lista importada: {$result['added']} destinatário(s) novo(s); a campanha tem {$result['total']} no total.";

        if ($result['duplicates']) {
            $message .= " {$result['duplicates']} repetido(s) ignorado(s).";
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

    public function clearRecipients(EmailCampaign $campaign): RedirectResponse
    {
        return $this->attempt($campaign, fn () => $this->campaigns->clearRecipients($campaign), 'Lista apagada.');
    }

    /** O resultado do envio por destinatário, para conferir ou reaproveitar a lista. */
    public function exportRecipients(EmailCampaign $campaign): StreamedResponse
    {
        $columns = array_keys(array_diff_key($campaign->columns ?? [], ['email' => true, 'nome' => true]));
        $name = 'campanha-' . $campaign->id . '-destinatarios.csv';

        return response()->streamDownload(function () use ($campaign, $columns) {
            $out = fopen('php://output', 'w');
            // BOM: o Excel abre o CSV em UTF-8 com acento certo.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['E-mail', 'Nome', 'Situação', 'Tentativas', 'Enviado em', 'Erro'], $columns), ';');

            foreach ($campaign->recipients()->orderBy('id')->lazy(1000) as $recipient) {
                fputcsv($out, array_merge([
                    $recipient->email,
                    $recipient->name,
                    $recipient->statusLabel(),
                    $recipient->attempts,
                    $recipient->sent_at?->format('d/m/Y H:i:s'),
                    $recipient->error,
                ], array_map(fn ($key) => $recipient->data[$key] ?? '', $columns)), ';');
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Lista de exemplo: a coluna E-mail é a única obrigatória. */
    public function listTemplate(string $format): StreamedResponse
    {
        $rows = [
            ['E-mail', 'Nome', 'Título'],
            ['fulano@exemplo.com.br', 'Fulano de Tal', '1001'],
            ['beltrana@exemplo.com.br', 'Beltrana Souza', '2002'],
        ];

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                foreach ($rows as $row) {
                    fputcsv($out, $row, ';');
                }
                fclose($out);
            }, 'lista-disparo-emails.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lista');
        $sheet->fromArray($rows, null, 'A1', true);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'lista-disparo-emails.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function uploadImage(Request $request, CampaignImageStore $images): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:' . implode(',', CampaignImageStore::EXTENSIONS), 'max:' . config('email_campaigns.image_max_kb')],
        ], [
            'image.mimes' => 'A imagem precisa ser PNG, JPG, GIF ou WEBP.',
            'image.max' => 'A imagem pode ter no máximo ' . config('email_campaigns.image_max_kb') . ' KB: ela vai dentro de cada e-mail.',
        ], ['image' => 'imagem']);

        $name = $images->store($request->file('image'));

        return response()->json(['url' => $images->url($name)]);
    }

    public function showImage(string $file, CampaignImageStore $images)
    {
        $path = $images->path($file);

        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * O remetente da campanha continua na lista mesmo que tenha saído da
     * configuração — senão editar só o texto obrigaria a trocar o remetente.
     *
     * @return list<string>
     */
    private function fromAddressesFor(EmailCampaign $campaign): array
    {
        return array_values(array_unique(array_filter(array_merge(
            config('email_campaigns.from_addresses'),
            [$campaign->from_address],
        ))));
    }

    private function attempt(EmailCampaign $campaign, callable $action, string|callable $success): RedirectResponse
    {
        try {
            $action();
        } catch (EmailCampaignException $e) {
            return redirect()->route('email-campaigns.show', $campaign)->with('error', $e->getMessage());
        }

        return redirect()->route('email-campaigns.show', $campaign)
            ->with('success', is_callable($success) ? $success() : $success);
    }
}
