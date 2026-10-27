<?php

namespace App\Services\Ticket;

use App\Exceptions\TicketException;
use App\Models\Ticket\TicketGuestAccessCode;
use App\Models\Ticket\TicketOrder;
use App\Support\Cpf;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * "Meus ingressos" do não sócio no site, sem conta:
 *
 *   1. CPF + e-mail da compra → código de 6 dígitos no e-mail (só se houver
 *      pedido de não sócio com os dois; a resposta ao site é sempre a mesma);
 *   2. código certo → acesso temporário (token cifrado com CPF, e-mail e
 *      validade), que lista os pedidos — cada um com o token dele, para o
 *      site seguir pelas rotas `/guest-orders/{id}` que já existem.
 *
 * O QR é a entrada no evento: por isso a pessoa prova que lê o e-mail antes
 * de ver qualquer ingresso. O código fica só como HMAC; erra
 * `MAX_ATTEMPTS` vezes, precisa pedir outro.
 */
class TicketGuestAccessService
{
    public const CODE_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const MAX_CODES_PER_HOUR = 5;
    public const ACCESS_HOURS = 2;

    public function __construct(private TicketNotifier $notifier)
    {
    }

    /**
     * Manda o código, se houver pedido. Não diz ao chamador se mandou: quem
     * tenta CPFs não descobre quem comprou.
     */
    public function requestCode(string $cpf, string $email, ?string $ip = null): void
    {
        [$cpf, $email] = $this->normalize($cpf, $email);

        $latest = $this->orders($cpf, $email)->first();
        if (! $latest) {
            return;
        }

        $recent = TicketGuestAccessCode::where('cpf', $cpf)->where('email', $email)
            ->where('created_at', '>=', Carbon::now()->subHour())->count();
        if ($recent >= self::MAX_CODES_PER_HOUR) {
            return;
        }

        // Só o último código vale.
        TicketGuestAccessCode::where('cpf', $cpf)->where('email', $email)->whereNull('used_at')->update(['used_at' => Carbon::now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        TicketGuestAccessCode::create([
            'cpf' => $cpf,
            'email' => $email,
            'code_hash' => $this->hash($code),
            'expires_at' => Carbon::now()->addMinutes(self::CODE_MINUTES),
            'ip' => $ip,
        ]);

        $this->notifier->accessCode($latest->buyer_email, $latest->buyer_name, $code, self::CODE_MINUTES);
    }

    /**
     * Confere o código e devolve o acesso temporário.
     *
     * @return array{access_token: string, expires_at: Carbon}
     */
    public function verify(string $cpf, string $email, string $code): array
    {
        [$cpf, $email] = $this->normalize($cpf, $email);
        $code = preg_replace('/\D/', '', $code);

        $row = TicketGuestAccessCode::where('cpf', $cpf)->where('email', $email)->whereNull('used_at')->latest('id')->first();

        if (! $row || $row->expires_at->isPast() || $row->attempts >= self::MAX_ATTEMPTS) {
            throw new TicketException(TicketException::ACCESS_CODE_EXPIRED, 'Este código não vale mais. Peça um novo.', 422);
        }

        if (! hash_equals($row->code_hash, $this->hash($code))) {
            $row->increment('attempts');
            $left = max(0, self::MAX_ATTEMPTS - $row->attempts);

            throw new TicketException(
                $left > 0 ? TicketException::ACCESS_CODE_INVALID : TicketException::ACCESS_CODE_EXPIRED,
                $left > 0 ? "Código incorreto. Você tem mais {$left} tentativa(s)." : 'Código incorreto. Peça um novo código.',
                422,
                ['attempts_left' => $left],
            );
        }

        $row->update(['used_at' => Carbon::now()]);
        $expires = Carbon::now()->addHours(self::ACCESS_HOURS);

        return [
            'access_token' => Crypt::encryptString(json_encode(['cpf' => $cpf, 'email' => $email, 'exp' => $expires->getTimestamp()])),
            'expires_at' => $expires,
        ];
    }

    /**
     * CPF e e-mail do acesso, ou null (token ausente, adulterado ou vencido).
     *
     * @return array{0: string, 1: string}|null
     */
    public function resolve(?string $token): ?array
    {
        if (blank($token)) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || empty($data['cpf']) || empty($data['email']) || (int) ($data['exp'] ?? 0) < Carbon::now()->getTimestamp()) {
            return null;
        }

        return [(string) $data['cpf'], (string) $data['email']];
    }

    /** Os pedidos de não sócio com esse CPF e e-mail, do mais novo ao mais antigo. */
    public function orders(string $cpf, string $email): Builder
    {
        return TicketOrder::where('buyer_kind', TicketOrder::BUYER_GUEST)
            ->where('buyer_cpf', $cpf)
            ->whereRaw('LOWER(buyer_email) = ?', [$email])
            ->orderByDesc('id');
    }

    /** @return Collection<int, TicketOrder> */
    public function list(string $cpf, string $email): Collection
    {
        return $this->orders($cpf, $email)->limit(50)->get();
    }

    /** @return array{0: string, 1: string} */
    private function normalize(string $cpf, string $email): array
    {
        return [Cpf::digits($cpf), mb_strtolower(trim($email))];
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
