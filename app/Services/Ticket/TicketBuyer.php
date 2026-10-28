<?php

namespace App\Services\Ticket;

use App\Models\Member;
use App\Models\Ticket\TicketOrder;
use App\Services\MultiClubes\MemberProfile;
use App\Support\Cpf;

/**
 * Quem compra: o sócio (conferido no MultiClubes; compra para as pessoas do
 * título dele) ou o não sócio sem conta (dados digitados no site ou no balcão).
 *
 * `cpf` nulo só na cortesia do balcão para quem não se identifica (o pedido
 * de cortesias em quantidade para um patrocinador, por exemplo).
 */
final class TicketBuyer
{
    private function __construct(
        public readonly string $kind,
        public readonly ?string $cpf,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?int $memberId = null,
        public readonly ?string $title = null,
    ) {
    }

    /** O sócio do login, com os dados do MultiClubes. */
    public static function member(MemberProfile $profile, ?Member $member = null): self
    {
        return new self(
            TicketOrder::BUYER_MEMBER,
            Cpf::digits($profile->cpf),
            $profile->name,
            $profile->email ?: $member?->email,
            $profile->phone,
            $member?->id,
            $profile->title,
        );
    }

    public static function guest(?string $cpf, string $name, ?string $email, ?string $phone): self
    {
        return new self(TicketOrder::BUYER_GUEST, Cpf::digits((string) $cpf) ?: null, trim($name), $email ? trim($email) : null, $phone ? trim($phone) : null);
    }

    public function isMember(): bool
    {
        return $this->kind === TicketOrder::BUYER_MEMBER;
    }
}
