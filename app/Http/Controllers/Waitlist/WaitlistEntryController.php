<?php

namespace App\Http\Controllers\Waitlist;

use App\Http\Controllers\Controller;
use App\Models\Waitlist\WaitlistContact;
use App\Models\Waitlist\WaitlistEntry;
use App\Services\Waitlist\WaitlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Ações sobre uma inscrição (pessoa × turma): registrar a ligação, tirar da
 * fila e reativar. Todas voltam para a ficha da pessoa, no cartão da turma.
 */
class WaitlistEntryController extends Controller
{
    public function __construct(private readonly WaitlistService $waitlist)
    {
    }

    public function contact(Request $request, WaitlistEntry $entry)
    {
        $data = $request->validate([
            'result' => ['required', Rule::in(array_keys(WaitlistContact::RESULTS))],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'result.required' => 'Escolha o resultado da ligação.',
        ]);

        $this->waitlist->registerContact($entry, $data['result'], $data['notes'] ?? null, Auth::id());

        $message = 'Ligação registrada. A posição na fila não muda.';
        if ($data['result'] === 'accepted') {
            $message = 'Ligação registrada. Depois de fazer a matrícula, marque "Matriculado" nesta turma.';
        }

        return $this->backToEntry($entry, $message);
    }

    public function close(Request $request, WaitlistEntry $entry)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(WaitlistEntry::CLOSING_STATUSES)],
            'close_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $others = $this->waitlist->close($entry, $data['status'], $data['close_reason'] ?? null, Auth::id());

        $entry->refresh();
        $message = "Inscrição encerrada: {$entry->statusLabel()}.";
        if ($others->isNotEmpty()) {
            $message .= ' A pessoa continua aguardando em: ' . $others->map(fn ($e) => $e->waitlistClass->label())->implode('; ')
                . '. Se ela não quiser mais essas vagas, encerre também.';
        }

        return $this->backToEntry($entry, $message);
    }

    public function reopen(WaitlistEntry $entry)
    {
        $this->waitlist->reopen($entry);

        return $this->backToEntry($entry, 'Inscrição reativada, na posição original (a data de entrada não muda).');
    }

    private function backToEntry(WaitlistEntry $entry, string $message)
    {
        return redirect()->to(route('waitlist.people.show', $entry->waitlist_person_id) . '#fila-' . $entry->id)
            ->with('success', $message);
    }
}
