<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TicketException;
use App\Http\Controllers\Controller;
use App\Models\Ticket\TicketOrder;
use App\Services\Ticket\TicketGuestAccessService;
use App\Services\Ticket\TicketPresenter;
use App\Support\Cpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * "Meus ingressos" do não sócio no site (`/api/tickets/guest-access/*`, só
 * `api_token`): CPF + e-mail → código por e-mail → acesso temporário no header
 * `Ticket-Access-Token` → lista dos pedidos, cada um com o token dele para as
 * rotas `/guest-orders/{id}`. Regras em TicketGuestAccessService.
 */
class TicketGuestAccessController extends Controller
{
    public function __construct(private TicketGuestAccessService $access, private TicketPresenter $presenter)
    {
    }

    public function requestCode(Request $request): JsonResponse
    {
        $data = $this->identity($request);

        $this->access->requestCode($data['cpf'], $data['email'], $request->ip());

        return response()->json([
            'message' => 'Se houver compra com esse CPF e e-mail, enviamos um código para o e-mail.',
            'expires_in_minutes' => TicketGuestAccessService::CODE_MINUTES,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $this->identity($request, ['code' => 'required|string|max:12']);

        try {
            $access = $this->access->verify($data['cpf'], $data['email'], $data['code']);
        } catch (TicketException $e) {
            return response()->json($e->toArray(), $e->status);
        }

        return response()->json([
            'access_token' => $access['access_token'],
            'expires_at' => $access['expires_at']->toIso8601String(),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $identity = $this->access->resolve($request->header('Ticket-Access-Token'));

        if (! $identity) {
            $e = new TicketException(TicketException::ACCESS_EXPIRED, 'Seu acesso expirou. Informe CPF e e-mail de novo para receber um código.', 401);

            return response()->json($e->toArray(), $e->status);
        }

        [$cpf, $email] = $identity;
        $orders = $this->access->list($cpf, $email);

        return response()->json([
            'buyer' => [
                'name' => $orders->first()?->buyer_name,
                'cpf' => Cpf::mask($cpf),
                'email' => $email,
            ],
            // Cada pedido com o `access_token` dele: o site segue pelas rotas /guest-orders/{id}.
            'orders' => $orders->map(fn (TicketOrder $order) => $this->presenter->order($order, withToken: true))->values(),
        ]);
    }

    private function identity(Request $request, array $extra = []): array
    {
        $data = Validator::make($request->all(), [
            'cpf' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ] + $extra, [], ['cpf' => 'CPF', 'email' => 'e-mail', 'code' => 'código'])->validate();

        if (! Cpf::isValid(Cpf::digits($data['cpf']))) {
            abort(response()->json(['message' => 'CPF inválido.', 'errors' => ['cpf' => ['CPF inválido.']]], 422));
        }

        return $data;
    }
}
