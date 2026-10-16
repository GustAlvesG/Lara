<?php

namespace App\Http\Controllers\Placar\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Placar\Web\Concerns\RedirecionaAposCadastro;
use App\Models\Placar\Competicao;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\CategoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Cadastro de jogos (modo planejado) — tela web. O ciclo de vida (iniciar,
 * eventos, encerrar) é operado pelo Node via API; aqui só o que precisa ser
 * decidido com antecedência: os times, a data, a competição e a escalação
 * (ver EscalacaoController).
 */
class JogoController extends Controller
{
    use RedirecionaAposCadastro;

    public function index(Request $request)
    {
        $jogos = Jogo::query()
            ->with(['modalidade', 'competicao', 'timeCasa', 'timeFora'])
            ->busca($request->query('busca'))
            ->when($request->filled('status'), fn ($q) => $q->statusEntre(explode(',', $request->query('status'))))
            ->daModalidade($request->query('modalidade'))
            ->when($request->filled('competicao_id'), fn ($q) => $q->where('competicao_id', $request->query('competicao_id')))
            ->when($request->boolean('criado_em_campo'), fn ($q) => $q->where('criado_em_campo', true))
            ->orderByDesc('data_hora')
            ->paginate(20)
            ->withQueryString();

        $modalidades = Modalidade::ativas()->orderBy('nome')->get();
        $competicoes = Competicao::ativas()->orderBy('nome')->get();

        return view('placar.jogos.index', compact('jogos', 'modalidades', 'competicoes'));
    }

    public function create()
    {
        $modalidades = Modalidade::ativas()->orderBy('nome')->get();
        $competicoes = Competicao::ativas()->orderBy('nome')->get();

        // Em ordem alfabética pelo nome que aparece na lista, não por
        // categoria: quem cadastra o jogo procura o time pelo nome, e
        // ordenar por categoria jogava "CF Adulto" e "CF Sub-15" para
        // pontas opostas do select.
        //
        // A ordenação é em PHP porque o nome exibido é resolvido no modelo
        // (`nome_exibicao` próprio, ou o da equipe + categoria) — não é
        // coluna que o banco possa ordenar. `modalidade` no eager load
        // porque cada opção mostra a dela.
        $times = Time::ativos()
            ->with(['equipe', 'modalidade'])
            ->get()
            ->sortBy(fn (Time $time) => $this->chaveAlfabetica($time->nomeExibicaoResolvido()))
            ->values();

        $categorias = $this->categoriasDosTimes($times);

        return view('placar.jogos.create', compact('modalidades', 'times', 'competicoes', 'categorias'));
    }

    /**
     * Categorias para o filtro do Novo Jogo, só as que têm time ativo, cada
     * uma com as modalidades em que existe (a tela esconde, por exemplo, o
     * Sub-11 se só houver Sub-11 de futsal e a modalidade for vôlei).
     *
     * Agrupadas pela chave do CategoriaService — a mesma comparação que o
     * store usa para barrar categorias diferentes —, então "Sub 15" antigo
     * e "Sub-15" aparecem como uma opção só. Ordem natural: Sub-9 antes de
     * Sub-11.
     *
     * @return \Illuminate\Support\Collection<int, array{chave: string, nome: string, modalidades: array<int, int>}>
     */
    private function categoriasDosTimes($times)
    {
        return $times
            ->groupBy(fn (Time $time) => CategoriaService::chave($time->categoria))
            ->map(fn ($doGrupo, $chave) => [
                'chave' => (string) $chave,
                'nome' => $doGrupo->first()->categoria,
                'modalidades' => $doGrupo->pluck('modalidade_id')->unique()->values()->all(),
            ])
            ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Chave de ordenação alfabética: sem acento e em minúsculas, para
     * "Ática" cair entre "Atibaia" e "Atlético" em vez de ir para o fim da
     * lista — que é onde uma comparação byte a byte a colocaria.
     */
    private function chaveAlfabetica(string $nome): string
    {
        return Str::lower(Str::ascii($nome));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'modalidade_id' => ['required', 'integer', 'exists:modalidades,id'],
            'time_casa_id' => ['required', 'integer', 'exists:times,id', 'different:time_fora_id'],
            'time_fora_id' => ['required', 'integer', 'exists:times,id'],
            'competicao_id' => ['nullable', 'integer', 'exists:competicoes,id'],
            'data_hora' => ['required', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
        ]);

        $times = [];

        foreach (['time_casa_id', 'time_fora_id'] as $campo) {
            $time = Time::find($data[$campo]);
            $times[$campo] = $time;

            if ($time && $time->modalidade_id !== (int) $data['modalidade_id']) {
                return back()->withInput()->withErrors([$campo => 'Este time não é da modalidade informada.']);
            }
        }

        [$casa, $fora] = [$times['time_casa_id'], $times['time_fora_id']];

        // Sub-15 não joga contra Adulto. Comparado pela chave normalizada
        // para não barrar dados antigos que gravaram "Sub 15" de um lado e
        // "Sub-15" do outro — são a mesma categoria.
        if ($casa && $fora && CategoriaService::chave($casa->categoria) !== CategoriaService::chave($fora->categoria)) {
            return back()->withInput()->withErrors([
                'time_fora_id' => "Os dois times precisam ser da mesma categoria: {$casa->categoria} x {$fora->categoria}.",
            ]);
        }

        $jogo = Jogo::create([
            ...$data,
            'status' => Jogo::STATUS_AGENDADO,
            'criado_em_campo' => false,
        ]);

        // "Criar outro" mantém o que uma rodada tem em comum: modalidade,
        // categoria, competição, data e local. Os times, nunca.
        return $this->aposCadastrar(
            $request, 'jogos',
            "Jogo {$casa->nomeExibicaoResolvido()} x {$fora->nomeExibicaoResolvido()} ({$casa->categoria}) cadastrado com sucesso.",
            manter: ['modalidade_id', 'categoria', 'competicao_id', 'data_hora', 'local'],
            link: ['url' => route('placar.jogos.escalacao.edit', $jogo), 'label' => 'Montar a escalação'],
        );
    }

    public function show(Jogo $jogo)
    {
        $jogo->load(['modalidade', 'competicao', 'timeCasa.equipe', 'timeFora.equipe', 'escalacoes']);

        return view('placar.jogos.show', compact('jogo'));
    }

    public function update(Request $request, Jogo $jogo)
    {
        $data = $request->validate([
            'competicao_id' => ['nullable', 'integer', 'exists:competicoes,id'],
            'data_hora' => ['required', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Jogo::STATUSES)],
        ]);

        $jogo->update($data);

        return redirect()->route('placar.jogos.show', $jogo)
            ->with('success', 'Jogo atualizado com sucesso.');
    }

    public function destroy(Jogo $jogo)
    {
        if ($jogo->eventos()->exists()) {
            return redirect()->route('placar.jogos.index')
                ->with('error', 'Não é possível excluir um jogo que já tem eventos registrados.');
        }

        $jogo->delete();

        return redirect()->route('placar.jogos.index')
            ->with('success', 'Jogo excluído com sucesso.');
    }
}
