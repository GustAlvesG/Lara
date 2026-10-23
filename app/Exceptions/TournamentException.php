<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Recusa de uma operação de torneio (inscrição, pagamento, desistência).
 *
 * Carrega um `reason` estável além da mensagem: o site decide o que mostrar
 * pelo código, nunca lendo o texto em português. Mesmo padrão de
 * SocialBookingException.
 */
class TournamentException extends RuntimeException
{
    /** Torneio inexistente, em rascunho ou cancelado. */
    public const TOURNAMENT_NOT_FOUND = 'tournament_not_found';

    /** Fora do período de inscrição (inscrever-se e pagar só dentro dele). */
    public const REGISTRATION_CLOSED = 'registration_closed';

    /** Sem sócio de título ativo no MultiClubes para o CPF e a matrícula. */
    public const MEMBER_NOT_FOUND = 'member_not_found';

    /** Categoria que não é deste torneio. */
    public const CATEGORY_NOT_FOUND = 'category_not_found';

    /** Sexo ou idade fora da categoria; o detalhe vem em `context.detail`. */
    public const INELIGIBLE = 'ineligible';

    /** A pessoa já tem inscrição ativa na categoria. */
    public const ALREADY_REGISTERED = 'already_registered';

    /** A categoria não tem mais vaga (conta as inscrições pagas). */
    public const CATEGORY_FULL = 'category_full';

    /** A inscrição não está na situação que a operação pede. */
    public const INVALID_STATUS = 'invalid_status';

    /** A inscrição não é do sócio da sessão. */
    public const NOT_OWNER = 'not_owner';

    /** Valor pago diferente do total das inscrições. */
    public const PAYMENT_MISMATCH = 'payment_mismatch';

    /** Desistência de inscrição paga é com o organizador, caso a caso. */
    public const PAID_CONTACT_ORGANIZER = 'paid_contact_organizer';

    /** A categoria não monta times desse jeito (ex.: capitão numa categoria por sorteio). */
    public const WRONG_FORMATION = 'wrong_formation';

    /** Time inexistente, cancelado ou de outra categoria. */
    public const TEAM_NOT_FOUND = 'team_not_found';

    /** Só o capitão do time pode fazer isso. */
    public const NOT_CAPTAIN = 'not_captain';

    /** O time já tem todas as vagas ocupadas (contando convites pendentes). */
    public const TEAM_FULL = 'team_full';

    /** A pessoa já está num time da categoria, ou já foi convidada para este. */
    public const ALREADY_IN_TEAM = 'already_in_team';

    /** É preciso estar inscrito na categoria (para criar time). */
    public const NOT_REGISTERED = 'not_registered';

    /** Convite inexistente, de outra pessoa ou que não está mais pendente. */
    public const INVITE_NOT_FOUND = 'invite_not_found';

    /** Categoria sem chaveamento divulgado (o organizador ainda não liberou). */
    public const BRACKET_NOT_PUBLISHED = 'bracket_not_published';

    /** Código de lançamento pelo site errado, vencido ou retirado. */
    public const INVALID_CODE = 'invalid_code';

    /** Jogo inexistente ou de outro torneio. */
    public const MATCH_NOT_FOUND = 'match_not_found';

    /** O resultado foi lançado no Lara: o site não altera (o Lara prevalece). */
    public const MATCH_LOCKED = 'match_locked';

    /** O jogo ainda não tem os dois times, ou é um "passa direto". */
    public const MATCH_NOT_READY = 'match_not_ready';

    /** Placar inválido (empate, set empatado, sets a mais ou a menos); detalhe em `context.detail`. */
    public const INVALID_SCORE = 'invalid_score';

    /** O vencedor mudaria, mas o jogo seguinte já tem resultado: corrija aquele antes. */
    public const NEXT_MATCH_PLAYED = 'next_match_played';

    /** O grupo já foi fechado (posições definidas): reabra no Lara para mudar resultado dele. */
    public const GROUP_CLOSED = 'group_closed';

    /** O torneio não está numa situação que aceita resultado (rascunho, cancelado; no site, também encerrado). */
    public const RESULTS_CLOSED = 'results_closed';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /** Corpo de resposta da API: mensagem para mostrar e código para decidir. */
    public function toArray(): array
    {
        return array_filter([
            'message' => $this->getMessage(),
            'reason' => $this->reason,
            'context' => $this->context ?: null,
        ], fn ($value) => $value !== null);
    }
}
