<?php

namespace App\Services;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Mail\WeeklyLimitCodeMail;
use App\Models\Freelancer;
use App\Models\FreelancerWeeklyLimitCode;
use App\Models\User;
use App\Models\FreelancerService as FreelancerServiceModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Liberação do limite de 7 dias à distância.
 *
 * O caminho normal é o coordenador do Comercial digitar o próprio PIN na tela.
 * Quando nenhum deles está no local, pede-se um código: o sistema sorteia 6
 * dígitos e manda **o mesmo número para todos os coordenadores do Comercial**.
 * Quem estiver disponível confere o contrato no e-mail e dita o código para
 * quem está registrando. Não se escolhe destinatário — quem registra não
 * precisa saber quem está de plantão.
 *
 * O código não aparece em nenhuma tela do sistema: é isso que prova que a
 * liberação veio de quem tem acesso à caixa de um coordenador.
 *
 * Ele vale para um contrato só (freelancer + data), uma vez só, por poucos
 * minutos e com um teto de tentativas.
 */
class WeeklyLimitCodeService
{
    private const CODE_LENGTH = 6;

    /** Setor cujos coordenadores recebem o código. */
    public const COORDINATOR_SECTOR = 'Comercial';

    public function ttlMinutes(): int
    {
        return (int) config('freelancers.weekly_limit.code_ttl_minutes', 15);
    }

    public function maxAttempts(): int
    {
        return (int) config('freelancers.weekly_limit.code_max_attempts', 5);
    }

    /**
     * Coordenadores do Comercial que podem receber o código: ativos e com
     * e-mail cadastrado.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::where('status_id', 1)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('sectors', fn($query) => $query
                ->where('user_sector.role', 'coordinator')
                ->whereRaw('LOWER(sectors.name) = ?', [mb_strtolower(self::COORDINATOR_SECTOR)]))
            ->orderBy('name')
            ->get();
    }

    /**
     * Sorteia um código, grava e manda para todos os coordenadores do
     * Comercial. Pedir um código novo invalida o anterior — senão dois e-mails
     * com números diferentes ficariam valendo ao mesmo tempo e ninguém saberia
     * qual ditar.
     *
     * Cada coordenador recebe a própria mensagem, nominal: em cópia, um veria
     * o e-mail dos outros sem necessidade.
     *
     * O envio é síncrono de propósito, como o e-mail da diretoria: uma falha de
     * SMTP precisa aparecer na tela de quem pediu, não sumir numa fila. Basta
     * uma mensagem sair para o código servir — só quando NENHUMA sai é que o
     * pedido falha.
     *
     * @throws CoordinatorAuthorizationException
     */
    public function issue(
        int $freelancerId,
        string $startDate,
        ?User $requestedBy = null,
    ): FreelancerWeeklyLimitCode {
        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            throw new CoordinatorAuthorizationException(
                'Nenhum coordenador do setor ' . self::COORDINATOR_SECTOR . ' com e-mail cadastrado. '
                . 'Cadastre o e-mail no painel (Usuários) ou libere presencialmente com o PIN.'
            );
        }

        $freelancer = Freelancer::find($freelancerId);

        if (!$freelancer) {
            throw new CoordinatorAuthorizationException('Freelancer não encontrado.');
        }

        // Invalida os pendentes antes de criar o novo.
        FreelancerWeeklyLimitCode::for($freelancerId, $startDate)
            ->whereNull('used_at')
            ->update(['expires_at' => now()->subSecond()]);

        $code = $this->randomCode();
        $expiresAt = now()->addMinutes($this->ttlMinutes());
        $servicesAfterSave = FreelancerServiceModel::countInWeeklyWindow($freelancerId, $startDate) + 1;

        $delivered = $this->deliver($recipients, $freelancer, $startDate, $servicesAfterSave, $code, $expiresAt, $requestedBy);

        if ($delivered->isEmpty()) {
            throw new CoordinatorAuthorizationException(
                'Não foi possível enviar o e-mail com o código a nenhum coordenador do setor '
                . self::COORDINATOR_SECTOR . '. Verifique o servidor de e-mail.'
            );
        }

        return FreelancerWeeklyLimitCode::create([
            'coordinator_id' => null,
            'freelancer_id' => $freelancerId,
            'start_date' => $startDate,
            'code_hash' => Hash::make($code),
            'sent_to' => $delivered->implode(', '),
            'requested_by' => $requestedBy?->id,
            'expires_at' => $expiresAt,
        ]);
    }

    /** Há um código válido esperando para ser digitado? */
    public function hasPending(int $freelancerId, $startDate): bool
    {
        return $this->pending($freelancerId, $startDate) !== null;
    }

    /**
     * Confere o código ditado e o queima. Cada tentativa errada é contada:
     * seis dígitos não sobrevivem a tentativas ilimitadas.
     */
    public function consume(int $freelancerId, $startDate, ?string $code): bool
    {
        $pending = $this->pending($freelancerId, $startDate);

        if (!$pending) {
            return false;
        }

        if (!$pending->matches($code)) {
            $pending->increment('attempts');

            return false;
        }

        $pending->forceFill(['used_at' => now()])->save();

        return true;
    }

    /**
     * Envia a todos e devolve os endereços que aceitaram. Uma caixa recusada
     * não pode derrubar o pedido inteiro: os outros coordenadores continuam
     * podendo liberar com o mesmo código.
     *
     * @param  Collection<int, User>  $recipients
     * @return Collection<int, string>
     */
    private function deliver(
        Collection $recipients,
        Freelancer $freelancer,
        string $startDate,
        int $servicesAfterSave,
        string $code,
        Carbon $expiresAt,
        ?User $requestedBy,
    ): Collection {
        return $recipients->filter(function (User $coordinator) use (
            $freelancer, $startDate, $servicesAfterSave, $code, $expiresAt, $requestedBy
        ) {
            try {
                Mail::to($coordinator->email)->send(new WeeklyLimitCodeMail(
                    coordinator: $coordinator,
                    freelancer: $freelancer,
                    startDate: Carbon::parse($startDate),
                    servicesAfterSave: $servicesAfterSave,
                    code: $code,
                    expiresAt: $expiresAt,
                    requestedBy: $requestedBy,
                ));

                return true;
            } catch (\Throwable $e) {
                // Sem o código na mensagem: ele não pode ir parar no log.
                Log::warning('Falha ao enviar código de liberação do limite semanal.', [
                    'coordinator_id' => $coordinator->id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        })->map(fn(User $coordinator) => $coordinator->email)->values();
    }

    private function pending(int $freelancerId, $startDate): ?FreelancerWeeklyLimitCode
    {
        return FreelancerWeeklyLimitCode::for($freelancerId, $startDate)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', $this->maxAttempts())
            ->first();
    }

    private function randomCode(): string
    {
        return str_pad(
            (string) random_int(0, (10 ** self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Lista de e-mails parcialmente escondida, para a tela confirmar para onde
     * o código foi sem expor os endereços a quem está no balcão.
     */
    public static function maskEmails(string $emails): string
    {
        return collect(explode(',', $emails))
            ->map(fn(string $email) => self::maskEmail(trim($email)))
            ->implode(', ');
    }

    public static function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($user, 0, 1);
        $masked = $visible . str_repeat('*', max(mb_strlen($user) - 1, 1));

        return $domain ? $masked . '@' . $domain : $masked;
    }
}
