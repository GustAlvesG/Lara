<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskLaraRequest;
use App\Models\LaraMessage;
use App\Services\Lara\LaraClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Chat interno com a Lara, o agente de IA que responde sobre o estatuto.
 *
 * A chamada à IA é síncrona: o funcionário pergunta e espera na tela. Isso é
 * proposital nesta primeira versão (é uma ferramenta interna, de poucos
 * usuários simultâneos), mas cobra um preço — cada pergunta segura um worker
 * do PHP-FPM por até 30s. Daí as duas defesas abaixo: `set_time_limit` para o
 * PHP não matar a requisição antes da hora, e uma trava por usuário para
 * ninguém enfileirar várias perguntas de 30s ao mesmo tempo.
 */
class LaraChatController extends Controller
{
    /**
     * A conversa vigente vive na sessão. Trocar esse uuid é o que o botão
     * "Nova conversa" faz do lado do portal — o histórico antigo continua no
     * banco, só sai da tela.
     */
    private const SESSION_KEY = 'lara.conversation_uuid';

    public function index(Request $request, LaraClient $lara): View
    {
        $conversationUuid = $this->conversationUuid($request);

        return view('lara.index', [
            'mensagens' => LaraMessage::daConversa($request->user()->id, $conversationUuid)->get(),
            // Duas perguntas diferentes: "ligamos o chat?" e "a VM respondeu?".
            // A tela explica cada caso de um jeito, porque a ação de quem vê é
            // outra — desligado é decisão nossa, fora do ar é chamado para a TI.
            'configurado' => $lara->enabled(),
            'disponivel' => $lara->healthy(),
        ]);
    }

    public function ask(AskLaraRequest $request, LaraClient $lara): JsonResponse
    {
        $user = $request->user();
        $timeout = (int) config('services.lara.timeout', 30);

        // O max_execution_time padrão do PHP-FPM é 30s. Sem esta linha o PHP
        // mataria a requisição junto com o timeout do cliente HTTP, e o
        // funcionário veria um erro 500 em vez da frase de fallback.
        set_time_limit($timeout + 30);

        $lock = Cache::lock("lara:ask:{$user->id}", $timeout + 10);

        if (!$lock->get()) {
            return response()->json([
                'message' => 'Sua pergunta anterior ainda está sendo respondida. Aguarde um instante.',
            ], 429);
        }

        try {
            $conversationUuid = $this->conversationUuid($request);
            $pergunta = $request->validated()['mensagem'];

            LaraMessage::create([
                'user_id' => $user->id,
                'conversation_uuid' => $conversationUuid,
                'role' => LaraMessage::ROLE_USER,
                'conteudo' => $pergunta,
            ]);

            $answer = $lara->ask($this->usuarioId($user->id), $pergunta);

            $mensagem = LaraMessage::create([
                'user_id' => $user->id,
                'conversation_uuid' => $conversationUuid,
                'role' => LaraMessage::ROLE_ASSISTANT,
                'conteudo' => $answer->texto,
                'status' => $answer->status,
                'latencia_ms' => $answer->latenciaMs,
                'erro' => $answer->erro,
            ]);

            return response()->json([
                'resposta' => $answer->texto,
                'status' => $answer->status,
                'horario' => $mensagem->created_at?->format('H:i'),
            ]);
        } finally {
            $lock->release();
        }
    }

    public function reset(Request $request, LaraClient $lara): JsonResponse
    {
        // A conversa nova começa mesmo que a IA não confirme a limpeza: o pior
        // caso é ela ainda lembrar do assunto anterior por um tempo, o que é
        // melhor que deixar o botão sem efeito.
        $lara->reset($this->usuarioId($request->user()->id));

        $request->session()->put(self::SESSION_KEY, (string) Str::uuid());

        return response()->json(['status' => 'ok']);
    }

    private function conversationUuid(Request $request): string
    {
        $uuid = $request->session()->get(self::SESSION_KEY);

        if (is_string($uuid) && $uuid !== '' && !$this->conversaExpirou($request->user()->id, $uuid)) {
            return $uuid;
        }

        $uuid = (string) Str::uuid();
        $request->session()->put(self::SESSION_KEY, $uuid);

        return $uuid;
    }

    /**
     * O histórico do lado da IA expira depois de 24h de inatividade. Manter a
     * conversa antiga na tela depois disso seria enganoso: o funcionário veria
     * o assunto anterior e a Lara não lembraria nada dele. Quando a memória lá
     * zera, a tela começa limpa aqui também.
     */
    private function conversaExpirou(int $userId, string $uuid): bool
    {
        $ultimaMensagem = LaraMessage::query()
            ->where('user_id', $userId)
            ->where('conversation_uuid', $uuid)
            ->max('created_at');

        // Conversa recém-aberta, ainda sem nenhuma mensagem: nada a expirar.
        if ($ultimaMensagem === null) {
            return false;
        }

        $ttl = (int) config('services.lara.history_ttl_hours', 24);

        return Carbon::parse($ultimaMensagem)->lt(now()->subHours($ttl));
    }

    /**
     * Identificador do funcionário do lado da IA — é a chave do histórico em
     * memória dela. O prefixo evita colidir com os ids de teste que o time da
     * IA usa (`func_123`) e deixa claro, olhando a VM, de onde veio a conversa.
     */
    private function usuarioId(int $userId): string
    {
        return 'portal_' . $userId;
    }
}
