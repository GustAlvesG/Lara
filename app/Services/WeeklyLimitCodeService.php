<?php

namespace App\Services;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Mail\WeeklyLimitCodeMail;
use App\Models\Freelancer;
use App\Models\FreelancerWeeklyLimitCode;
use App\Models\User;
use App\Models\FreelancerService as FreelancerServiceModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Liberação do limite de 7 dias à distância.
 *
 * O caminho normal é o coordenador do Comercial digitar o próprio PIN na tela.
 * Quando ele não está no local, pede-se um código: o sistema sorteia 6 dígitos,
 * manda para o e-mail cadastrado DELE, e ele dita o número para quem está
 * registrando o contrato. O código não aparece em nenhuma tela do sistema — é
 * isso que prova que quem liberou foi quem recebeu o e-mail.
 *
 * O código vale para um contrato só (coordenador + freelancer + data), uma vez
 * só, por poucos minutos e com um teto de tentativas.
 */
class WeeklyLimitCodeService
{
    private const CODE_LENGTH = 6;

    public function ttlMinutes(): int
    {
        return (int) config('freelancers.weekly_limit.code_ttl_minutes', 15);
    }

    public function maxAttempts(): int
    {
        return (int) config('freelancers.weekly_limit.code_max_attempts', 5);
    }

    /**
     * Sorteia, grava e envia um código. Pedir um código novo invalida o
     * anterior — senão dois e-mails com números diferentes ficariam valendo ao
     * mesmo tempo e o coordenador não saberia qual ditar.
     *
     * O envio é síncrono de propósito, como o e-mail da diretoria: uma falha de
     * SMTP precisa aparecer na tela de quem pediu, não sumir numa fila.
     *
     * @throws CoordinatorAuthorizationException
     */
    public function issue(
        User $coordinator,
        int $freelancerId,
        string $startDate,
        ?User $requestedBy = null,
    ): FreelancerWeeklyLimitCode {
        if (blank($coordinator->email)) {
            throw new CoordinatorAuthorizationException(
                'Este coordenador não tem e-mail cadastrado. Cadastre no painel (Usuários) ou use o PIN presencialmente.'
            );
        }

        $freelancer = Freelancer::find($freelancerId);

        if (!$freelancer) {
            throw new CoordinatorAuthorizationException('Freelancer não encontrado.');
        }

        // Invalida os pendentes antes de criar o novo.
        FreelancerWeeklyLimitCode::for($coordinator->id, $freelancerId, $startDate)
            ->whereNull('used_at')
            ->update(['expires_at' => now()->subSecond()]);

        $code = $this->randomCode();

        $record = FreelancerWeeklyLimitCode::create([
            'coordinator_id' => $coordinator->id,
            'freelancer_id' => $freelancerId,
            'start_date' => $startDate,
            'code_hash' => Hash::make($code),
            'sent_to' => $coordinator->email,
            'requested_by' => $requestedBy?->id,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ]);

        Mail::to($coordinator->email)->send(new WeeklyLimitCodeMail(
            coordinator: $coordinator,
            freelancer: $freelancer,
            startDate: Carbon::parse($startDate),
            servicesAfterSave: FreelancerServiceModel::countInWeeklyWindow($freelancerId, $startDate) + 1,
            code: $code,
            expiresAt: $record->expires_at,
            requestedBy: $requestedBy,
        ));

        return $record;
    }

    /** Há um código válido esperando para ser digitado? */
    public function hasPending(User $coordinator, int $freelancerId, $startDate): bool
    {
        return $this->pending($coordinator, $freelancerId, $startDate) !== null;
    }

    /**
     * Confere o código ditado e o queima. Cada tentativa errada é contada:
     * seis dígitos não sobrevivem a tentativas ilimitadas.
     */
    public function consume(User $coordinator, int $freelancerId, $startDate, ?string $code): bool
    {
        $pending = $this->pending($coordinator, $freelancerId, $startDate);

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

    private function pending(User $coordinator, int $freelancerId, $startDate): ?FreelancerWeeklyLimitCode
    {
        return FreelancerWeeklyLimitCode::for($coordinator->id, $freelancerId, $startDate)
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
     * E-mail parcialmente escondido, para a tela confirmar para onde o código
     * foi sem expor o endereço inteiro a quem está no balcão.
     */
    public static function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($user, 0, 1);
        $masked = $visible . str_repeat('*', max(mb_strlen($user) - 1, 1));

        return $domain ? $masked . '@' . $domain : $masked;
    }
}
