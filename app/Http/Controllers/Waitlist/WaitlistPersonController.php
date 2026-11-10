<?php

namespace App\Http\Controllers\Waitlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaitlistPersonRequest;
use App\Models\Waitlist\WaitlistClass;
use App\Models\Waitlist\WaitlistEntry;
use App\Models\Waitlist\WaitlistPerson;
use App\Services\Waitlist\WaitlistService;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Os não sócios da fila de espera: busca, inclusão pelo CPF (quem já está
 * cadastrado é reaproveitado, não duplicado), ficha com as filas e as
 * ligações, e a inclusão em outras turmas.
 */
class WaitlistPersonController extends Controller
{
    public function __construct(private readonly WaitlistService $waitlist)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $query = WaitlistPerson::query()
            ->withCount(['entries as waiting_count' => fn (Builder $q) => $q->waiting()])
            ->orderBy('name');

        if ($search !== '') {
            $digits = Cpf::digits($search);

            $query->where(function (Builder $q) use ($search, $digits) {
                $q->where('name', 'like', "%{$search}%");

                if ($digits !== '') {
                    $q->orWhere('cpf', 'like', "%{$digits}%")
                        ->orWhereRaw(self::phoneDigitsSql('phone') . ' like ?', ["%{$digits}%"])
                        ->orWhereRaw(self::phoneDigitsSql('phone_alt') . ' like ?', ["%{$digits}%"]);
                }
            });
        }

        return view('waitlist.people.index', [
            'people' => $query->paginate(30)->withQueryString(),
            'search' => $search,
        ]);
    }

    /**
     * Incluir começa pelo CPF: quem já está cadastrado vai para a ficha (de
     * onde entra em outras turmas); quem não está ganha o formulário.
     */
    public function create(Request $request)
    {
        $cpf = Cpf::digits($request->query('cpf'));

        if ($cpf === '') {
            return view('waitlist.people.create', ['cpf' => null, 'classes' => collect()]);
        }

        if (!Cpf::isValid($cpf)) {
            return redirect()->route('waitlist.people.create')
                ->withErrors(['cpf' => 'O CPF informado não é válido.'])
                ->withInput(['cpf' => $request->query('cpf')]);
        }

        $existing = WaitlistPerson::where('cpf', $cpf)->first();
        if ($existing) {
            return redirect()->route('waitlist.people.show', $existing)
                ->with('success', "{$existing->name} já tem cadastro na fila de espera. Escolha abaixo as turmas em que a pessoa vai entrar.");
        }

        return view('waitlist.people.create', [
            'cpf' => $cpf,
            'classes' => WaitlistClass::active()->ordered()->get(),
        ]);
    }

    public function store(WaitlistPersonRequest $request)
    {
        $person = WaitlistPerson::create($request->personData() + ['created_by' => Auth::id()]);

        $result = $this->waitlist->enroll($person, $request->validated('classes', []), Auth::id());

        $message = $result['added']->isEmpty()
            ? "Cadastro de {$person->name} criado, ainda sem turma. Escolha abaixo as turmas."
            : "Cadastro de {$person->name} criado e incluído na fila de " . $result['added']->count() . ' ' . ($result['added']->count() === 1 ? 'turma' : 'turmas') . '.';

        return redirect()->route('waitlist.people.show', $person)->with('success', $message);
    }

    public function show(WaitlistPerson $person)
    {
        $person->load([
            'creator',
            'entries' => fn ($q) => $q->with(['waitlistClass', 'contacts.user', 'closer', 'creator'])->orderByDesc('joined_at'),
        ]);

        [$waiting, $closed] = $person->entries->partition(fn (WaitlistEntry $e) => $e->isWaiting());

        return view('waitlist.people.show', [
            'person' => $person,
            'waiting' => $waiting->sortBy([['joined_at', 'asc'], ['id', 'asc']])->values(),
            'closed' => $closed->sortByDesc('closed_at')->values(),
            'availableClasses' => WaitlistClass::active()->ordered()
                ->whereNotIn('id', $waiting->pluck('waitlist_class_id'))->get(),
        ]);
    }

    public function update(WaitlistPersonRequest $request, WaitlistPerson $person)
    {
        $person->update($request->personData());

        return redirect()->route('waitlist.people.show', $person)
            ->with('success', "Cadastro de {$person->name} atualizado.");
    }

    /**
     * Telefone sem pontuação, para "21999990000" achar "(21) 99999-0000".
     * `$column` entra cru no SQL: só nome de coluna escrito no código.
     */
    private static function phoneDigitsSql(string $column): string
    {
        return "REPLACE(REPLACE(" . Cpf::digitsSql($column) . ", '(', ''), ')', '')";
    }

    public function enroll(Request $request, WaitlistPerson $person)
    {
        $data = $request->validate([
            'classes' => ['required', 'array', 'min:1'],
            'classes.*' => ['integer', 'exists:waitlist_classes,id'],
        ], [
            'classes.required' => 'Escolha pelo menos uma turma.',
            'classes.min' => 'Escolha pelo menos uma turma.',
        ]);

        $result = $this->waitlist->enroll($person, $data['classes'], Auth::id());

        $parts = [];
        if ($result['added']->isNotEmpty()) {
            $parts[] = "{$person->name} entrou na fila de: " . $result['added']->map(fn ($e) => $e->waitlistClass->label())->implode('; ') . '.';
        }
        if ($result['skipped']->isNotEmpty()) {
            $parts[] = 'Já estava aguardando em: ' . $result['skipped']->map->label()->implode('; ') . '.';
        }

        return redirect()->route('waitlist.people.show', $person)->with('success', implode(' ', $parts));
    }
}
