<?php

namespace App\Services\Lara;

use Illuminate\Support\Str;

/**
 * Um erro que a pessoa viu na tela e pediu para o HAL verificar.
 *
 * O texto vem do navegador e pode trazer o que não deve sair daqui: telas
 * antigas ainda põem $e->getMessage() no flash (SQL, caminhos, nomes de
 * classe) e há mensagens com CPF. Por isso a pergunta é montada no servidor, e
 * erro do sistema nunca leva texto: o tipo basta para o HAL orientar.
 *
 * Só `tipo` e `status` vão como dado estruturado para a IA. O texto vai dentro
 * da pergunta, que é o que a IA já trata como fala do usuário.
 */
final class ScreenError
{
    public const TIPO_VALIDACAO = 'validacao';
    public const TIPO_REGRA = 'regra';
    public const TIPO_PERMISSAO = 'permissao';
    public const TIPO_SESSAO = 'sessao';
    public const TIPO_NAO_ENCONTRADO = 'nao_encontrado';
    public const TIPO_LIMITE = 'limite';
    public const TIPO_SISTEMA = 'sistema';

    private const MAX_CHARS = 400;

    /** Sinais de mensagem técnica, que a pessoa não deveria nem estar vendo. */
    private const TECHNICAL = '/SQLSTATE|Illuminate\\\\|App\\\\|vendor[\\\\\/]|\.php\b|stack trace|Exception\b|Call to |Undefined |cURL error|Connection refused|Allowed memory/i';

    /** Mensagens padrão do framework: não dizem nada além do próprio status. */
    private const GENERIC = [
        'this action is unauthorized.', 'forbidden', 'not found', 'unauthenticated.', 'unauthorized',
        'csrf token mismatch.', 'page expired', 'too many attempts.', 'too many requests',
        'server error', 'service unavailable', 'the given data was invalid.',
    ];

    private const FRASES = [
        self::TIPO_PERMISSAO => 'Apareceu "acesso negado" (erro 403) nesta tela.',
        self::TIPO_SESSAO => 'Apareceu "sessão expirada" nesta tela.',
        self::TIPO_NAO_ENCONTRADO => 'Apareceu "página não encontrada" (erro 404) nesta tela.',
        self::TIPO_LIMITE => 'Apareceu "muitas tentativas" (erro 429) nesta tela.',
        self::TIPO_SISTEMA => 'Apareceu um erro do sistema nesta tela.',
        self::TIPO_VALIDACAO => 'Apareceu um erro no formulário desta tela.',
        self::TIPO_REGRA => 'Apareceu um erro nesta tela.',
    ];

    private function __construct(
        public readonly string $tipo,
        public readonly ?int $status,
        public readonly ?string $texto,
    ) {
    }

    public static function from(mixed $status, mixed $texto): self
    {
        $status = is_numeric($status) && $status >= 400 && $status <= 599 ? (int) $status : null;
        $texto = is_string($texto) ? self::clean($texto) : null;
        $tecnico = $texto !== null && preg_match(self::TECHNICAL, $texto) === 1;

        $tipo = $tecnico ? self::TIPO_SISTEMA : self::tipoDoStatus($status);

        if ($tecnico || $tipo === self::TIPO_SISTEMA || in_array(mb_strtolower((string) $texto), self::GENERIC, true)) {
            $texto = null;
        }

        return new self($tipo, $status, $texto);
    }

    /** O que aparece no chat como pergunta da pessoa e vai para a IA. */
    public function pergunta(): string
    {
        $inicio = $this->texto !== null
            ? 'Apareceu este erro na tela: "' . $this->texto . '".'
            : self::FRASES[$this->tipo];

        return $inicio . ' O que aconteceu e como resolvo?';
    }

    /** @return array{tipo: string, status: ?int} */
    public function contrato(): array
    {
        return ['tipo' => $this->tipo, 'status' => $this->status];
    }

    private static function tipoDoStatus(?int $status): string
    {
        return match (true) {
            $status === null => self::TIPO_REGRA,
            $status >= 500 => self::TIPO_SISTEMA,
            $status === 401, $status === 419 => self::TIPO_SESSAO,
            $status === 403 => self::TIPO_PERMISSAO,
            $status === 404 => self::TIPO_NAO_ENCONTRADO,
            $status === 422 => self::TIPO_VALIDACAO,
            $status === 429 => self::TIPO_LIMITE,
            default => self::TIPO_REGRA,
        };
    }

    private static function clean(string $texto): ?string
    {
        $texto = trim((string) preg_replace('/\s+/', ' ', strip_tags($texto)));
        // CPF com ou sem máscara: o motivo do erro não depende do número.
        $texto = (string) preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[CPF]', $texto);

        return $texto === '' ? null : Str::limit($texto, self::MAX_CHARS);
    }
}
