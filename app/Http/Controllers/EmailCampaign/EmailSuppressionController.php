<?php

namespace App\Http\Controllers\EmailCampaign;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign\EmailSuppression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Não enviar para" do Disparo de E-mails: quem pediu para sair das
 * campanhas. Vale para todas, inclusive as que já estão enviando (o envio
 * confere na hora). Os pedidos chegam por resposta ao e-mail (o
 * "cancelar inscrição" do Gmail/Outlook aponta para o e-mail de resposta) e
 * quem cuida das campanhas os lança aqui.
 */
class EmailSuppressionController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->input('q'));

        $suppressions = EmailSuppression::with('author')
            ->when($term !== '', fn ($q) => $q->where('email', 'like', '%' . mb_strtolower($term) . '%'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('email-campaigns.suppressions', [
            'suppressions' => $suppressions,
            'filters' => ['q' => $term],
        ]);
    }

    /** Vários de uma vez: um por linha, ou separados por vírgula/ponto e vírgula. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'emails' => ['required', 'string', 'max:200000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['emails' => 'e-mails', 'reason' => 'motivo']);

        $addresses = preg_split('/[\s;,]+/', $data['emails'], -1, PREG_SPLIT_NO_EMPTY);
        $added = 0;
        $invalid = [];

        foreach (array_unique(array_map([EmailSuppression::class, 'normalize'], $addresses)) as $address) {
            if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $address;

                continue;
            }

            $suppression = EmailSuppression::firstOrCreate(
                ['email' => $address],
                ['reason' => $data['reason'] ?? null, 'created_by' => Auth::id()],
            );

            $added += (int) $suppression->wasRecentlyCreated;
        }

        $message = "{$added} e-mail(s) incluído(s) em \"Não enviar para\".";

        if ($invalid) {
            $message .= ' Ignorados por não serem e-mail: ' . implode(', ', array_slice($invalid, 0, 10)) . (count($invalid) > 10 ? '…' : '') . '.';
        }

        return redirect()->route('email-suppressions.index')->with($added || ! $invalid ? 'success' : 'error', $message);
    }

    public function destroy(EmailSuppression $suppression): RedirectResponse
    {
        $suppression->delete();

        return redirect()->route('email-suppressions.index')
            ->with('success', "{$suppression->email} voltou a receber as campanhas.");
    }
}
