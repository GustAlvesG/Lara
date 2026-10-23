<?php

namespace App\Services\Tournament;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TournamentException;
use App\Models\Member;
use App\Models\SchedulePayment;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentPayment;
use App\Models\Tournament\TournamentRegistration;
use App\Models\User;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Services\RedeItauService;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inscrição individual nos torneios e o pagamento dela.
 *
 *   inscrever → `pending_payment` → (pagamento eRede ou baixa manual) → `paid`
 *   saídas: `withdrawn` (o sócio desiste antes de pagar) e `cancelled` (o
 *   organizador cancela, com ou sem estorno)
 *
 * Regras (docs/funcionalidades/torneios.md):
 *   - só sócio de título ativo no MultiClubes, com sexo e idade (na data de
 *     início do torneio) aceitos pela categoria;
 *   - inscrever-se e pagar só dentro do período de inscrição;
 *   - uma inscrição ativa por pessoa e categoria;
 *   - vagas (`max_registrations`) contam as inscrições pagas: quem paga
 *     primeiro garante a vaga;
 *   - categoria de preço zero já nasce paga;
 *   - desistência de inscrição paga e estorno são do organizador, caso a caso.
 *
 * No balcão do Lara (`$override`), sexo, idade, prazo, situação do torneio e
 * vagas não recusam: viram aviso (ConfirmationRequired) e, confirmado, a
 * inscrição entra com quem liberou e o porquê. Só a inscrição repetida
 * continua recusada. A baixa manual também passa da vaga, com aviso.
 *
 * A trava da linha da categoria (`lockForUpdate`) serializa as inscrições e
 * pagamentos concorrentes de uma mesma categoria, para a vaga e a regra de
 * uma inscrição por pessoa não escaparem por corrida.
 *
 * E-mail é efeito colateral, disparado fora da transação.
 */
class TournamentRegistrationService
{
    public function __construct(
        private MemberProfileLookup $profiles,
        private TournamentNotifier $notifier,
        private RedeItauService $rede,
        private TournamentTeamService $teams,
    ) {
    }

    /** Os dados do sócio no MultiClubes; recusa se não for sócio de título ativo. */
    public function profileFor(Member $member): MemberProfile
    {
        $profile = $this->profiles->find(Cpf::digits($member->cpf), (string) $member->title);

        if (! $profile) {
            throw new TournamentException(TournamentException::MEMBER_NOT_FOUND, 'Não encontramos seu cadastro de sócio ativo. Procure a secretaria.');
        }

        return $profile;
    }

    /**
     * Situação de cada categoria para a pessoa: pode se inscrever, por que
     * não, e se já tem inscrição.
     *
     * @return list<array{category_id: int, eligible: bool, reason: ?string, registration: ?TournamentRegistration}>
     */
    public function eligibility(Tournament $tournament, MemberProfile $profile, Member $member): array
    {
        $active = TournamentRegistration::active()
            ->where('member_id', $member->id)
            ->whereIn('tournament_category_id', $tournament->categories->pluck('id'))
            ->get()
            ->keyBy('tournament_category_id');

        return $tournament->categories->map(function (TournamentCategory $category) use ($tournament, $profile, $active) {
            $reason = $category->ineligibilityReason($profile->gender, $profile->birthDate, $tournament->starts_on);

            return [
                'category_id' => $category->id,
                'eligible' => $reason === null,
                'reason' => $reason,
                'registration' => $active->get($category->id),
            ];
        })->values()->all();
    }

    /**
     * Inscreve a pessoa nas categorias escolhidas, numa transação: ou entram
     * todas, ou nenhuma.
     *
     * `$override` (só pelo balcão): [User, motivo ou null]. Sexo, idade,
     * prazo, situação do torneio e vagas viram aviso; sem `$force`, a
     * inscrição para em ConfirmationRequired. Confirmada, grava quem liberou
     * e o motivo (o digitado, ou os avisos).
     *
     * @param  list<int>  $categoryIds
     * @param  array{0: User, 1: ?string}|null  $override
     * @return Collection<int, TournamentRegistration>
     */
    public function register(Tournament $tournament, Member $member, MemberProfile $profile, array $categoryIds, ?array $override = null, bool $force = false): Collection
    {
        $warnings = [];

        if ($override === null) {
            $this->assertRegistrationOpen($tournament);
        } elseif (! $tournament->registrationOpen()) {
            $warnings[] = self::closedWarning($tournament);
        }

        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        if ($categoryIds === []) {
            throw new TournamentException(TournamentException::CATEGORY_NOT_FOUND, 'Escolha ao menos uma categoria.');
        }

        $registrations = DB::transaction(function () use ($tournament, $member, $profile, $categoryIds, $override, $force, $warnings) {
            $categories = TournamentCategory::where('tournament_id', $tournament->id)
                ->whereIn('id', $categoryIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($categories->count() !== count($categoryIds)) {
                throw new TournamentException(TournamentException::CATEGORY_NOT_FOUND, 'Categoria não encontrada neste torneio.', 404);
            }

            foreach ($categories as $category) {
                $this->assertNotRegistered($category, $member);

                $reason = $category->ineligibilityReason($profile->gender, $profile->birthDate, $tournament->starts_on);

                if ($reason !== null && $override === null) {
                    throw new TournamentException(
                        TournamentException::INELIGIBLE,
                        "Você não se encaixa na categoria {$category->name}: " . self::ineligibilityText($reason) . '.',
                        422,
                        ['category_id' => $category->id, 'detail' => $reason],
                    );
                }
                if ($reason !== null) {
                    $warnings[] = "{$profile->name} não se encaixa na categoria {$category->name}: " . self::ineligibilityText($reason) . '.';
                }

                if ($override === null) {
                    $this->assertVacancy($category, 1);
                } else {
                    $warnings[] = $this->vacancyWarning($category, 1);
                }
            }

            $warnings = array_values(array_filter($warnings));
            ConfirmationRequired::unless($force, $warnings);

            $released = $override !== null && $warnings !== [];
            $releaseReason = ! $released ? null
                : mb_substr(filled($override[1] ?? null) ? trim($override[1]) : 'Liberada no balcão: ' . implode(' ', $warnings), 0, 500);

            $created = new EloquentCollection();

            foreach ($categories as $category) {
                $free = (float) $category->price <= 0;

                $created->push(TournamentRegistration::create([
                    'tournament_category_id' => $category->id,
                    'member_id' => $member->id,
                    'cpf' => $profile->cpf,
                    'title' => $profile->title,
                    'name' => $profile->name,
                    'email' => $profile->email ?? $member->email,
                    'birth_date' => $profile->birthDate,
                    'gender' => $profile->gender,
                    'amount' => $category->price,
                    'status' => $free ? TournamentRegistration::STATUS_PAID : TournamentRegistration::STATUS_PENDING_PAYMENT,
                    'paid_at' => $free ? Carbon::now() : null,
                    'eligibility_override_by' => $released ? $override[0]->id : null,
                    'eligibility_override_reason' => $releaseReason,
                ]));
            }

            // Preço zero já nasce paga: na categoria individual, já vira time.
            $this->teams->afterPaid($created->where('status', TournamentRegistration::STATUS_PAID)->pluck('id'));

            return $created;
        });

        $this->notifier->notify($registrations, TournamentNotifier::REGISTERED);

        return $registrations;
    }

    /**
     * Pagamento pela eRede, avisado pelo site. Mesmas garantias do
     * `POST /api/schedule/payment`: as inscrições são do sócio da sessão, o
     * mesmo tid reenviado devolve o pagamento já gravado, e o valor tem de ser
     * exatamente o total das inscrições.
     *
     * @param  list<int>  $registrationIds
     * @param  array{payment_method: string, paid_amount: float|string, payment_integration_id: string, paid_at?: ?string}  $data
     * @return array{payment: TournamentPayment, created: bool}
     */
    public function pay(Member $member, array $registrationIds, array $data): array
    {
        $registrationIds = array_values(array_unique(array_map('intval', $registrationIds)));

        $existing = $this->existingPayment($member, $registrationIds, $data['payment_integration_id']);
        if ($existing) {
            return ['payment' => $existing, 'created' => false];
        }

        $payment = DB::transaction(function () use ($member, $registrationIds, $data) {
            $registrations = $this->lockPayable($registrationIds, $member->id);
            foreach ($registrations->pluck('category.tournament')->unique('id') as $tournament) {
                $this->assertRegistrationOpen($tournament);
            }
            $this->assertAmount($registrations, (float) $data['paid_amount']);

            $payment = TournamentPayment::create([
                'member_id' => $member->id,
                'payment_method' => $data['payment_method'],
                'paid_amount' => round((float) $data['paid_amount'], 2),
                'paid_at' => ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now(),
                'payment_integration_id' => $data['payment_integration_id'],
                'status' => TournamentPayment::STATUS_PAID,
            ]);

            $this->markPaid($registrations, $payment);

            return $payment;
        });

        $this->notifier->notify($payment->registrations()->get(), TournamentNotifier::PAID);

        return ['payment' => $payment, 'created' => true];
    }

    /**
     * Baixa manual: o organizador registra o que foi pago fora da eRede. Vale
     * para inscrições pendentes de uma mesma pessoa; o valor é o total delas.
     * Registra um fato já acontecido, então não exige o período de inscrição
     * aberto. Passar da vaga é aviso: sem `$force`, para em ConfirmationRequired.
     *
     * @param  list<int>  $registrationIds
     * @param  array{payment_method: string, paid_at?: ?string, manual_notes?: ?string}  $data
     */
    public function recordManual(array $registrationIds, array $data, User $user, bool $force = false): TournamentPayment
    {
        if (! array_key_exists($data['payment_method'] ?? '', SchedulePayment::MANUAL_METHODS)) {
            throw new InvalidArgumentException('Escolha a forma em que o pagamento foi recebido.');
        }

        $registrationIds = array_values(array_unique(array_map('intval', $registrationIds)));

        $payment = DB::transaction(function () use ($registrationIds, $data, $user, $force) {
            $memberIds = TournamentRegistration::whereIn('id', $registrationIds)->distinct()->pluck('member_id');

            if ($memberIds->count() !== 1) {
                throw new TournamentException(TournamentException::NOT_OWNER, 'A baixa manual vale para inscrições de uma mesma pessoa.');
            }

            $registrations = $this->lockPayable($registrationIds, (int) $memberIds->first(), $force);

            $payment = new TournamentPayment([
                'member_id' => $memberIds->first(),
                'payment_method' => $data['payment_method'],
                'paid_amount' => round((float) $registrations->sum('amount'), 2),
                'paid_at' => ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now(),
                'status' => TournamentPayment::STATUS_PAID,
            ]);
            $payment->is_manual = true;
            $payment->recorded_by = $user->id;
            $payment->manual_notes = $data['manual_notes'] ?? null;
            $payment->save();

            $this->markPaid($registrations, $payment);

            return $payment;
        });

        $this->notifier->notify($payment->registrations()->get(), TournamentNotifier::PAID);

        return $payment;
    }

    /** O sócio desiste pelo site. Só antes de pagar: depois, é com o organizador. */
    public function withdraw(TournamentRegistration $registration, Member $member): TournamentRegistration
    {
        if ($registration->member_id !== $member->id) {
            throw new TournamentException(TournamentException::NOT_OWNER, 'Inscrição não encontrada.', 404);
        }

        if ($registration->status === TournamentRegistration::STATUS_PAID) {
            throw new TournamentException(TournamentException::PAID_CONTACT_ORGANIZER, 'Sua inscrição já está paga. Para desistir, fale com a organização do torneio.');
        }

        if ($registration->status !== TournamentRegistration::STATUS_PENDING_PAYMENT) {
            throw new TournamentException(TournamentException::INVALID_STATUS, 'Esta inscrição não está mais ativa.');
        }

        DB::transaction(function () use ($registration) {
            $registration->update(['status' => TournamentRegistration::STATUS_WITHDRAWN, 'withdrawn_at' => Carbon::now()]);
            $this->teams->detach($registration);
        });

        return $registration;
    }

    /**
     * O organizador cancela uma inscrição ativa, caso a caso. Com `$refund`,
     * devolve pela eRede o valor desta inscrição — antes de cancelar: se a
     * eRede recusar, nada muda. Baixa manual não tem estorno pelo Lara.
     *
     * @return float o valor estornado (0 sem estorno)
     */
    public function cancel(TournamentRegistration $registration, User $user, string $reason, bool $refund = false): float
    {
        if (! $registration->isActive()) {
            throw new TournamentException(TournamentException::INVALID_STATUS, 'Esta inscrição já não está ativa.');
        }

        $refunded = 0.0;
        $payment = $registration->payment;

        if ($refund) {
            if (! $payment || $registration->status !== TournamentRegistration::STATUS_PAID) {
                throw new InvalidArgumentException('Esta inscrição não tem pagamento para estornar.');
            }
            $refunded = $this->refund($payment, (float) $registration->amount, $user);
        }

        DB::transaction(function () use ($registration, $user, $reason, $refunded) {
            $registration->update([
                'status' => TournamentRegistration::STATUS_CANCELLED,
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
                // O estorno desta inscrição (o pagamento pode cobrir várias): relatório por categoria.
                'refunded_amount' => $refunded,
            ]);
            $this->teams->detach($registration);
        });

        $this->notifier->notify(collect([$registration->fresh()]), TournamentNotifier::CANCELLED, ['refunded' => $refunded]);

        return $refunded;
    }

    public static function ineligibilityText(string $reason): string
    {
        return match ($reason) {
            TournamentCategory::INELIGIBLE_GENDER => 'a categoria é de outro sexo',
            TournamentCategory::INELIGIBLE_GENDER_UNKNOWN => 'o sexo não está no cadastro do clube',
            TournamentCategory::INELIGIBLE_BIRTH_DATE_UNKNOWN => 'a data de nascimento não está no cadastro do clube',
            TournamentCategory::INELIGIBLE_TOO_YOUNG => 'idade abaixo da mínima na data do torneio',
            TournamentCategory::INELIGIBLE_TOO_OLD => 'idade acima da máxima na data do torneio',
            default => 'não atende às regras da categoria',
        };
    }

    private function refund(TournamentPayment $payment, float $amount, User $user): float
    {
        if ($payment->is_manual) {
            throw new InvalidArgumentException('Pagamento registrado por baixa manual (fora da eRede): não há estorno pelo Lara. Se houver devolução, ela é feita fora do sistema.');
        }

        $remaining = round((float) $payment->paid_amount - (float) $payment->refunded_amount, 2);
        $amount = round(min($amount, $remaining), 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Não há valor disponível para estorno neste pagamento.');
        }

        $this->rede->refund($payment->payment_integration_id, $amount);

        $payment->refunded_amount = round((float) $payment->refunded_amount + $amount, 2);
        $payment->refunded_by = $user->id;
        $payment->refunded_at = Carbon::now();
        $payment->status = $payment->refunded_amount >= (float) $payment->paid_amount
            ? TournamentPayment::STATUS_REFUNDED
            : TournamentPayment::STATUS_PARTIALLY_REFUNDED;
        $payment->save();

        return $amount;
    }

    private function assertRegistrationOpen(Tournament $tournament): void
    {
        if (! $tournament->registrationOpen()) {
            throw new TournamentException(TournamentException::REGISTRATION_CLOSED, 'As inscrições deste torneio não estão abertas.');
        }
    }

    private function assertNotRegistered(TournamentCategory $category, Member $member): void
    {
        $exists = TournamentRegistration::active()
            ->where('tournament_category_id', $category->id)
            ->where('member_id', $member->id)
            ->exists();

        if ($exists) {
            throw new TournamentException(TournamentException::ALREADY_REGISTERED, "Você já está inscrito na categoria {$category->name}.", 409, ['category_id' => $category->id]);
        }
    }

    /** `$taking` vagas a mais cabem? Conta as inscrições já pagas. */
    private function assertVacancy(TournamentCategory $category, int $taking): void
    {
        if ($this->vacancyWarning($category, $taking) !== null) {
            throw new TournamentException(TournamentException::CATEGORY_FULL, "A categoria {$category->name} não tem mais vagas.", 409, ['category_id' => $category->id]);
        }
    }

    /** O aviso de vaga estourada, ou null se cabe. */
    private function vacancyWarning(TournamentCategory $category, int $taking): ?string
    {
        if ($category->max_registrations === null) {
            return null;
        }

        $paid = TournamentRegistration::where('tournament_category_id', $category->id)
            ->where('status', TournamentRegistration::STATUS_PAID)
            ->count();

        return $paid + $taking > $category->max_registrations
            ? "A categoria {$category->name} já tem {$paid} de {$category->max_registrations} vaga(s) ocupada(s) por inscrição paga: esta passa do limite."
            : null;
    }

    /** Por que o balcão está fora da janela de inscrição do site. */
    private static function closedWarning(Tournament $tournament): string
    {
        $why = match (true) {
            $tournament->status !== Tournament::STATUS_PUBLISHED => 'o torneio está ' . mb_strtolower(Tournament::STATUS_LABELS[$tournament->status] ?? $tournament->status),
            $tournament->registration_starts_at !== null && now()->lt($tournament->registration_starts_at) => 'o período de inscrição ainda não começou',
            default => 'o período de inscrição já acabou',
        };

        return "As inscrições pelo site estão fechadas ({$why}). Fora do período o site não aceita o pagamento: se houver valor, dê baixa manual.";
    }

    /**
     * As inscrições pendentes da pessoa, com as categorias travadas, e a vaga
     * conferida por categoria. Com `$force` informado (baixa manual), vaga
     * estourada vira aviso: ConfirmationRequired enquanto `$force` for false.
     *
     * @return Collection<int, TournamentRegistration>
     */
    private function lockPayable(array $registrationIds, int $memberId, ?bool $force = null): Collection
    {
        if ($registrationIds === []) {
            throw new TournamentException(TournamentException::INVALID_STATUS, 'Escolha as inscrições a pagar.');
        }

        $registrations = TournamentRegistration::with('category.tournament')
            ->whereIn('id', $registrationIds)
            ->lockForUpdate()
            ->get();

        if ($registrations->count() !== count($registrationIds) || $registrations->contains(fn ($r) => $r->member_id !== $memberId)) {
            throw new TournamentException(TournamentException::NOT_OWNER, 'Uma ou mais inscrições não pertencem a este sócio.', 403);
        }

        if ($registrations->contains(fn ($r) => $r->status !== TournamentRegistration::STATUS_PENDING_PAYMENT)) {
            throw new TournamentException(TournamentException::INVALID_STATUS, 'Uma ou mais inscrições não estão aguardando pagamento.', 409);
        }

        $categories = TournamentCategory::whereIn('id', $registrations->pluck('tournament_category_id')->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $warnings = [];
        foreach ($registrations->groupBy('tournament_category_id') as $categoryId => $group) {
            if ($force === null) {
                $this->assertVacancy($categories[$categoryId], $group->count());
            } else {
                $warnings[] = $this->vacancyWarning($categories[$categoryId], $group->count());
            }
        }
        ConfirmationRequired::unless((bool) $force, $warnings);

        return $registrations;
    }

    private function assertAmount(Collection $registrations, float $paid): void
    {
        $total = round((float) $registrations->sum('amount'), 2);

        if (abs(round($paid, 2) - $total) > 0.001) {
            throw new TournamentException(
                TournamentException::PAYMENT_MISMATCH,
                'O valor pago não confere com o total das inscrições.',
                422,
                ['expected' => $total],
            );
        }
    }

    private function markPaid(Collection $registrations, TournamentPayment $payment): void
    {
        TournamentRegistration::whereIn('id', $registrations->pluck('id'))->update([
            'status' => TournamentRegistration::STATUS_PAID,
            'tournament_payment_id' => $payment->id,
            'paid_at' => $payment->paid_at,
        ]);

        $this->teams->afterPaid($registrations->pluck('id'));
    }

    /** Reenvio do mesmo tid para as mesmas inscrições: devolve o que já foi gravado. */
    private function existingPayment(Member $member, array $registrationIds, string $tid): ?TournamentPayment
    {
        $payment = TournamentPayment::where('payment_integration_id', $tid)
            ->where('member_id', $member->id)
            ->first();

        if (! $payment) {
            return null;
        }

        $linked = $payment->registrations()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $asked = collect($registrationIds)->sort()->values()->all();

        if ($linked !== $asked) {
            throw new TournamentException(TournamentException::INVALID_STATUS, 'Este pagamento já foi usado para outras inscrições.', 409);
        }

        return $payment;
    }
}
