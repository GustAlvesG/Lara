<?php

namespace App\Http\Controllers\PoliBot;

use App\Http\Controllers\Controller;
use App\Models\BotFlow;
use App\Models\PoliMessage;
use App\Services\PoliBot\BotEngine;
use App\Services\PoliBot\FlowDefinition;
use App\Services\PoliBot\FlowRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tela de fluxos do bot do WhatsApp: lista, edição, ativação.
 *
 * Gravar é publicar: o fluxo salvo ativo passa a valer na próxima mensagem.
 * Por isso fluxo inválido não grava (nem inativo — o editor mantém o
 * trabalho na tela até corrigir), e cada gravação vira uma versão.
 */
class FlowController extends Controller
{
    public function __construct(private readonly FlowRepository $fluxos) {}

    public function index(BotEngine $bot): View
    {
        $desde = now()->subDays(7);

        $contar = fn ($q) => $q->where('created_at', '>=', $desde)->count();

        return view('poli-bot.index', [
            'fluxos' => BotFlow::orderByDesc('active')->orderBy('name')->get()->map(fn (BotFlow $f) => [
                'model' => $f,
                'erros' => $f->flow()->errors(),
                'passos' => count($f->definition['steps'] ?? []),
                'gatilho' => $this->descreverGatilho($f->flow()),
                'versao' => $f->versions()->first(),
            ]),
            'modo' => $bot->mode(),
            'numeros' => [
                'conversas' => PoliMessage::where('direction', 'IN')->where('created_at', '>=', $desde)->distinct()->count('contact_uuid'),
                'recebidas' => $contar(PoliMessage::where('direction', 'IN')),
                'respostas' => $contar(PoliMessage::where('direction', 'OUT')->where('type', '!=', 'ACTION')),
                'transbordos' => $contar(PoliMessage::where('type', 'ACTION')->where('texto', 'like', 'handoff%')),
                'pedidos_uber' => $contar(PoliMessage::where('type', 'ACTION')->where('texto', 'like', 'uber_request%')),
                'falhas' => $contar(PoliMessage::where('ack', 'FAILED')),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->editor(null);
    }

    public function edit(BotFlow $flow): View
    {
        return $this->editor($flow);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('bot_flows', 'slug')],
            'active' => ['boolean'],
            'definition' => ['required', 'array'],
        ], [
            'slug.regex' => 'O identificador aceita só letras minúsculas, números e hífen (ex.: carro-de-aplicativo).',
            'slug.unique' => 'Já existe um fluxo com este identificador.',
        ]);

        return $this->gravar(null, $dados['slug'], $dados, $request);
    }

    public function update(Request $request, BotFlow $flow): JsonResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'active' => ['boolean'],
            'definition' => ['required', 'array'],
        ]);

        // O slug não muda depois de criado: é por ele que os outros fluxos
        // (goto_flow) e as conversas em andamento (bot_sessions) o acham.
        return $this->gravar($flow, $flow->slug, $dados, $request);
    }

    public function toggle(Request $request, BotFlow $flow): JsonResponse|RedirectResponse
    {
        $ativar = !$flow->active;

        if ($ativar) {
            $erros = $this->fluxos->errors($flow->slug, $flow->definition ?? [], true, $flow->id);

            if ($erros !== []) {
                return $this->recusa($request, $erros);
            }
        }

        $flow->update(['active' => $ativar]);
        $this->fluxos->registrarVersao($flow, $request->user());

        $mensagem = $ativar ? "Fluxo \"{$flow->name}\" ativado." : "Fluxo \"{$flow->name}\" desativado.";

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'active' => $ativar, 'message' => $mensagem])
            : back()->with('success', $mensagem);
    }

    public function destroy(Request $request, BotFlow $flow): JsonResponse|RedirectResponse
    {
        $quemUsa = $this->fluxos->referencedBy($flow);

        if ($quemUsa !== []) {
            return $this->recusa($request, [
                'Este fluxo é chamado por: ' . implode(', ', $quemUsa) . '. Tire essas referências antes de apagar.',
            ]);
        }

        $nome = $flow->name;
        $flow->delete();

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'redirect' => route('poli-bot.index')])
            : redirect()->route('poli-bot.index')->with('success', "Fluxo \"{$nome}\" apagado.");
    }

    /* ------------------------------------------------------------------ */

    private function gravar(?BotFlow $fluxo, string $slug, array $dados, Request $request): JsonResponse
    {
        $definicao = FlowDefinition::clean($dados['definition']);
        $ativo = (bool) ($dados['active'] ?? false);

        $erros = $this->fluxos->errors($slug, $definicao, $ativo, $fluxo?->id);

        if ($erros !== []) {
            return response()->json(['message' => 'O fluxo tem problemas e não foi salvo.', 'errors' => ['definition' => $erros]], 422);
        }

        $salvo = $this->fluxos->save($fluxo, $slug, $dados['name'], $ativo, $definicao, $request->user());

        return response()->json([
            'ok' => true,
            'message' => $fluxo ? 'Fluxo salvo.' : 'Fluxo criado.',
            'redirect' => $fluxo ? null : route('poli-bot.flows.edit', $salvo),
            'definition' => $salvo->definition,
            'version' => $this->versaoParaTela($salvo->versions()->first()),
        ]);
    }

    private function recusa(Request $request, array $erros): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $erros[0], 'errors' => ['definition' => $erros]], 422)
            : back()->withErrors(['definition' => $erros]);
    }

    private function editor(?BotFlow $fluxo): View
    {
        return view('poli-bot.editor', [
            'fluxo' => $fluxo,
            'dados' => [
                'id' => $fluxo?->id,
                'name' => $fluxo?->name ?? '',
                'slug' => $fluxo?->slug ?? '',
                'active' => $fluxo?->active ?? false,
                'definition' => $fluxo?->definition ?? self::esqueleto(),
            ],
            'versoes' => $fluxo
                ? $fluxo->versions()->limit(30)->get()->map(fn ($v) => $this->versaoParaTela($v))->all()
                : [],
            'outrosFluxos' => BotFlow::when($fluxo, fn ($q) => $q->where('id', '!=', $fluxo->id))
                ->orderBy('name')->get(['slug', 'name', 'active'])->all(),
        ]);
    }

    private function versaoParaTela($versao): ?array
    {
        return $versao ? [
            'id' => $versao->id,
            'name' => $versao->name,
            'active' => $versao->active,
            'definition' => $versao->definition,
            'user' => $versao->user_name,
            'at' => $versao->created_at?->format('d/m/Y H:i'),
        ] : null;
    }

    private function descreverGatilho(FlowDefinition $f): string
    {
        return match (true) {
            $f->opensOnAnyMessage() => 'Qualquer primeira mensagem',
            $f->triggerTexts() !== [] => 'Palavras: ' . implode(', ', $f->triggerTexts()),
            $f->reachedOnlyByGoto() => 'Só chamado por outro fluxo',
            default => 'Sem gatilho',
        };
    }

    /** Fluxo novo: um passo, para a tela nunca começar vazia. */
    private static function esqueleto(): array
    {
        return [
            'start' => 'inicio',
            'triggers' => ['any' => false, 'texts' => [], 'only_goto' => false],
            'timeout_minutes' => 15,
            'max_attempts' => 3,
            'steps' => [
                'inicio' => [
                    'say' => ['type' => 'text', 'text' => 'Olá, {contato}! Como posso ajudar?'],
                ],
            ],
        ];
    }
}
