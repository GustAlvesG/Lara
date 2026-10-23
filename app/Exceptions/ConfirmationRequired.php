<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Regra que, na interface web do Lara, não recusa: só avisa e pede
 * confirmação. A tela volta com os avisos e um botão "Confirmar mesmo assim",
 * que reenvia o mesmo formulário com `force=1` (ver partials/alerts).
 *
 * O serviço junta os avisos e chama `ConfirmationRequired::unless($force, $avisos)`
 * antes de gravar. O site nunca recebe esta exceção: lá as regras recusam
 * (TournamentException).
 */
class ConfirmationRequired extends RuntimeException
{
    /** @param  list<string>  $warnings */
    public function __construct(public readonly array $warnings)
    {
        parent::__construct(implode(' ', $warnings));
    }

    /** @param  list<string>  $warnings */
    public static function unless(bool $force, array $warnings): void
    {
        $warnings = array_values(array_filter($warnings));

        if (! $force && $warnings !== []) {
            throw new self($warnings);
        }
    }

    /** Não é erro: não vai para o log. */
    public function report(): void
    {
    }

    public function render(Request $request): RedirectResponse
    {
        $input = $request->except(['_token', '_method', 'force']);

        return back()->withInput($input)->with('confirmation', [
            'warnings' => $this->warnings,
            'action' => $request->url(),
            'method' => $request->method(),
            'input' => $input,
        ]);
    }
}
