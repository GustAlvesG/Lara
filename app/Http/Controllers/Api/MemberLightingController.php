<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SelfServiceLightingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberLightingActivationRequest;
use App\Models\Member;
use App\Models\MemberLightingActivation;
use App\Models\Place;
use App\Services\HomeAssistant\SelfServiceLightingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Acionamento de luz de quadra pelo próprio sócio, do aplicativo de reservas.
 *
 * No fim de semana não há reserva de quadra — o uso é livre — e até aqui a luz
 * dependia de alguém do clube. Estes endpoints são a porta do sócio para isso,
 * com as regras em SelfServiceLightingService: janela de horário, uma quadra
 * por vez, duas horas.
 *
 * Autenticação dupla, como o resto do app de reservas: `api_token` (a
 * aplicação) e `login_token` (o sócio). Quem aciona é sempre o dono do
 * `Session`: nenhum endpoint aqui aceita um sócio vindo do corpo da
 * requisição.
 */
class MemberLightingController extends Controller
{
    public function __construct(private SelfServiceLightingService $lighting)
    {
    }

    /**
     * O que a tela precisa saber antes de qualquer escolha: está aberto? até
     * quando? o sócio já tem quadra acesa?
     *
     * É o único endpoint que a tela precisa consultar ao abrir — e o que ela
     * repete enquanto o contador regressivo corre.
     */
    public function availability(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $member = $this->member($request);

        if (! $member) {
            return response()->json(['error' => 'Sócio não encontrado.'], 404);
        }

        $today  = $this->lighting->windowFor($now->copy()->startOfDay());
        $open   = $this->lighting->openWindowAt($now);
        $active = $this->lighting->activeFor($member->id, $now);

        return response()->json([
            'now'              => $now->toIso8601String(),
            'open'             => (bool) $open,
            'window'           => $open?->toArray(),
            'today_window'     => $today?->toArray(),
            // Preenchida mesmo com a janela aberta: a tela usa para dizer
            // "próximo horário" depois que o sócio já acionou hoje.
            'next_window'      => $this->lighting->nextWindow($now)?->toArray(),
            // O seletor de duração da tela é montado com estes três: pedir
            // acima do teto é recusado, e o teto muda sem deploy do app.
            'max_minutes'      => $this->lighting->maxMinutes(),
            'min_minutes'      => $this->lighting->minimumMinutes(),
            'step_minutes'     => $this->lighting->stepMinutes(),
            // Quanto dá para pedir agora: o teto aparado no que resta da
            // janela. Zero (ou abaixo do mínimo) quer dizer "hoje acabou".
            'available_minutes' => $open ? $this->lighting->minutesToGrant($open, $now) : 0,
            'activation'       => $active ? $this->lighting->activationPayload($active, $now) : null,
        ]);
    }

    /**
     * Os grupos que têm ao menos uma quadra liberada.
     *
     * Primeiro passo do formulário da tela: o sócio escolhe a modalidade antes
     * da quadra. Grupo sem quadra liberada não aparece — um select com uma
     * opção que leva a uma lista vazia é um beco.
     */
    public function groups(): JsonResponse
    {
        $groups = $this->lighting->eligiblePlaces()
            ->filter(fn (Place $place) => $place->group !== null)
            ->groupBy('place_group_id')
            ->map(fn ($places) => [
                'id'     => $places->first()->group->id,
                'name'   => $places->first()->group->name,
                'icon'   => $places->first()->group->icon,
                'places' => $places->count(),
            ])
            ->sortBy('name')
            ->values();

        return response()->json(['groups' => $groups]);
    }

    /**
     * As quadras liberadas de um grupo, cada uma dizendo se a luz já está acesa
     * e até quando.
     *
     * Acesa **não** é indisponível: a quadra continua acionável, e acionar uma
     * já acesa é justamente como se prolonga a luz. O que o `lit_until` muda é
     * o texto do botão, não se ele existe.
     */
    public function places(Request $request, int $group): JsonResponse
    {
        $now = Carbon::now();

        $places = $this->lighting->eligiblePlaces()->where('place_group_id', $group);

        // Uma consulta para o grupo inteiro: a tela relê esta lista a cada
        // ação, e duas consultas por quadra viram tráfego à toa num endpoint
        // que já é o mais chamado da funcionalidade.
        $lit = $this->lighting->litUntilMany($places->pluck('id')->all(), $now);

        $places = $places
            ->map(fn (Place $place) => [
                'id'        => $place->id,
                'name'      => $place->name,
                'image'     => $place->image,
                'lit'       => isset($lit[$place->id]),
                'lit_until' => $lit[$place->id] ?? null,
            ])
            ->sortBy('name')
            ->values();

        return response()->json(['places' => $places]);
    }

    /**
     * Acende a luz da quadra para o sócio do `Session`, pelo tempo que ele
     * pediu — ou prolonga, quando é a quadra em que ele já está.
     *
     * Responde `201` no acionamento novo e `200` no prolongamento: a tela
     * precisa distinguir "acendeu" de "mais 40 minutos" para escolher o que
     * dizer, e o corpo é o mesmo nos dois casos.
     */
    public function activate(StoreMemberLightingActivationRequest $request, int $place): JsonResponse
    {
        $member = $this->member($request);

        if (! $member) {
            return response()->json(['error' => 'Sócio não encontrado.'], 404);
        }

        $target = Place::find($place);

        if (! $target) {
            return response()->json(['error' => 'Quadra não encontrada.'], 404);
        }

        $extending = $this->lighting->activeFor($member->id)?->place_id === $target->id;

        try {
            $activation = $this->lighting->activate(
                memberId: $member->id,
                place: $target,
                minutes: $request->minutes(),
                origin: 'App do sócio',
            );
        } catch (SelfServiceLightingException $e) {
            return response()->json($e->payload(), $e->status);
        }

        return response()->json([
            'extended'   => $extending,
            'activation' => $this->lighting->activationPayload($activation->load('place.group')),
        ], $extending ? 200 : 201);
    }

    /**
     * Devolve a quadra antes do prazo: apaga a luz e libera a cota.
     *
     * Sem parâmetro de qual acionamento encerrar — é sempre o vigente do
     * próprio sócio, porque ele só pode ter um.
     */
    public function release(Request $request): JsonResponse
    {
        $member = $this->member($request);

        if (! $member) {
            return response()->json(['error' => 'Sócio não encontrado.'], 404);
        }

        $active = $this->lighting->activeFor($member->id);

        if (! $active) {
            return response()->json([
                'error'  => 'Você não tem nenhuma quadra acesa.',
                'reason' => 'no_activation',
            ], 404);
        }

        $this->lighting->release($active);

        return response()->json([
            'activation' => $this->lighting->activationPayload($active->fresh()->load('place.group')),
        ]);
    }

    /**
     * Histórico do sócio, do mais recente para o mais antigo.
     *
     * Existe para o sócio conferir o próprio uso — e para a portaria responder
     * "quem acendeu a quadra 3 no sábado?" sem abrir o banco.
     */
    public function history(Request $request): JsonResponse
    {
        $member = $this->member($request);

        if (! $member) {
            return response()->json(['error' => 'Sócio não encontrado.'], 404);
        }

        $now = Carbon::now();

        $items = MemberLightingActivation::where('member_id', $member->id)
            ->with(['place.group'])
            ->orderByDesc('starts_at')
            ->limit(30)
            ->get()
            ->map(fn ($a) => $this->lighting->activationPayload($a, $now));

        return response()->json(['activations' => $items]);
    }

    /**
     * O sócio dono do `Session`.
     *
     * O JwtMiddleware põe o payload do token em `user`, e o que identifica o
     * sócio nele é o cpf (`username`). Nunca vem do corpo da requisição: senão
     * qualquer sócio logado acenderia a luz na cota de outro.
     */
    private function member(Request $request): ?Member
    {
        $cpf = data_get($request->input('user'), 'username');

        return $cpf ? Member::where('cpf', $cpf)->first() : null;
    }
}
