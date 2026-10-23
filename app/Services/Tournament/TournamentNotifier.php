<?php

namespace App\Services\Tournament;

use App\Models\Tournament\TournamentRegistration;
use App\Models\Tournament\TournamentTeamInvite;
use App\Services\EmailService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * E-mails das inscrições de torneio, todos no mesmo template
 * (`emails.tournament.status`), que muda título, cor e texto pelo tipo.
 *
 * Um e-mail por pessoa e torneio, listando as categorias daquela operação. O
 * endereço é o que veio do MultiClubes na inscrição (ou o do cadastro do
 * site). Envio é efeito colateral — falhou, registra e segue.
 */
class TournamentNotifier
{
    public const REGISTERED = 'tournament.registered';
    public const PAID = 'tournament.paid';
    public const CANCELLED = 'tournament.cancelled';
    public const INVITE = 'tournament.invite';

    public const TYPES = [self::REGISTERED, self::PAID, self::CANCELLED, self::INVITE];

    private const TITLES = [
        self::REGISTERED => 'Inscrição recebida',
        self::PAID => 'Inscrição confirmada',
        self::CANCELLED => 'Inscrição cancelada',
        self::INVITE => 'Convite para time',
    ];

    public function __construct(private ?EmailService $email = null)
    {
        $this->email ??= new EmailService();
    }

    /** @param  Collection<int, TournamentRegistration>  $registrations */
    public function notify(Collection $registrations, string $type, array $extra = []): int
    {
        $sent = 0;

        $groups = $registrations
            ->each(fn (TournamentRegistration $r) => $r->loadMissing('category.tournament.placeGroup', 'member'))
            ->groupBy(fn (TournamentRegistration $r) => $r->member_id . '-' . $r->category->tournament_id);

        foreach ($groups as $group) {
            try {
                $data = $this->build($group, $type, $extra);
            } catch (\Throwable $e) {
                Log::error('Falha ao montar o e-mail de inscrição de torneio.', [
                    'type' => $type,
                    'registration_ids' => $group->pluck('id')->all(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $sent += $this->email->sendScheduleMail($data) ? 1 : 0;
        }

        return $sent;
    }

    /** Convite do capitão: vai para o e-mail do MultiClubes do convidado. */
    public function notifyInvite(TournamentTeamInvite $invite): bool
    {
        try {
            $data = $this->buildInvite($invite);
        } catch (\Throwable $e) {
            Log::error('Falha ao montar o e-mail de convite de time.', ['invite_id' => $invite->id, 'error' => $e->getMessage()]);

            return false;
        }

        return $this->email->sendScheduleMail($data);
    }

    public function buildInvite(TournamentTeamInvite $invite): array
    {
        $invite->loadMissing('team.category.tournament.placeGroup', 'team.registrations', 'invitedBy');
        $category = $invite->team->category;
        $tournament = $category->tournament;

        return [
            'email' => $invite->email,
            'name' => $invite->name,
            'type' => self::INVITE,
            'subject' => self::TITLES[self::INVITE] . ' - ' . $tournament->title,
            'title' => self::TITLES[self::INVITE],
            'schedule_ids' => [$invite->id],
            'tournament' => $tournament->title,
            'place_group' => $tournament->placeGroup?->name,
            'dates' => $tournament->starts_on->format('d/m/Y') . ($tournament->ends_on->isSameDay($tournament->starts_on) ? '' : ' a ' . $tournament->ends_on->format('d/m/Y')),
            'inviter' => $invite->invitedBy?->name,
            'team' => $invite->team->name,
            'team_members' => $invite->team->registrations->pluck('name')->all(),
            'categories' => [[
                'name' => $category->name,
                'amount' => number_format((float) $category->price, 2, ',', '.'),
                'status' => 'Convite',
            ]],
            'total' => number_format((float) $category->price, 2, ',', '.'),
            'pay_until' => $tournament->registration_ends_at->format('d/m/Y H:i'),
            'issued_at' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }

    /** @param  Collection<int, TournamentRegistration>  $registrations  da mesma pessoa e torneio */
    public function build(Collection $registrations, string $type, array $extra = []): array
    {
        $first = $registrations->first();
        $tournament = $first->category->tournament;
        $money = fn ($value) => number_format((float) $value, 2, ',', '.');
        $title = self::TITLES[$type] ?? 'Inscrição em torneio';

        $pending = $registrations->where('status', TournamentRegistration::STATUS_PENDING_PAYMENT);

        return [
            'email' => $first->email ?: $first->member?->email,
            'name' => $first->name ?: $first->member?->name,
            'type' => $type,
            'subject' => $title . ' - ' . $tournament->title,
            'title' => $title,
            // EmailService registra estes ids no log quando o envio falha.
            'schedule_ids' => $registrations->pluck('id')->all(),
            'tournament' => $tournament->title,
            'place_group' => $tournament->placeGroup?->name,
            'dates' => $tournament->starts_on->format('d/m/Y') . ($tournament->ends_on->isSameDay($tournament->starts_on) ? '' : ' a ' . $tournament->ends_on->format('d/m/Y')),
            'categories' => $registrations->map(fn (TournamentRegistration $r) => [
                'name' => $r->category->name,
                'amount' => $money($r->amount),
                'status' => TournamentRegistration::STATUS_LABELS[$r->status] ?? $r->status,
            ])->values()->all(),
            'total' => $money($registrations->sum('amount')),
            'pending_total' => $pending->isNotEmpty() ? $money($pending->sum('amount')) : null,
            'pay_until' => $pending->isNotEmpty() ? $tournament->registration_ends_at->format('d/m/Y H:i') : null,
            'cancel_reason' => $type === self::CANCELLED ? $first->cancel_reason : null,
            'refunded' => ! empty($extra['refunded']) ? $money($extra['refunded']) : null,
            'issued_at' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }
}
