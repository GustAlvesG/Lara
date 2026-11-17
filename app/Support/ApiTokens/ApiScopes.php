<?php

namespace App\Support\ApiTokens;

/**
 * Escopos dos tokens de integração: um por grupo de rotas de routes/api.php.
 *
 * A rota declara o escopo no middleware (`api_token:ia`) e o token só passa
 * se tiver aquele escopo. Assim um token do HAL não cadastra freelancer e um
 * do Home Assistant não lê avisos. O inventário rota → escopo está em
 * docs/funcionalidades/gestao-tokens.md.
 *
 * Escopo novo: constante + linha em CATALOG + o middleware nas rotas. Não
 * há tabela de escopos: o token guarda a lista em JSON.
 */
final class ApiScopes
{
    public const SITE = 'site';
    public const IA = 'ia';
    public const PORTARIA = 'portaria';
    public const HOME_ASSISTANT = 'home-assistant';
    public const TELEGRAM = 'telegram';
    public const WEBHOOK_WHATSAPP = 'webhook-whatsapp';

    /** escopo => [rótulo, o que libera] */
    private const CATALOG = [
        self::SITE => ['Site (Next.js)', 'Login e conta do sócio, reservas, espaços sociais, torneios, ingressos, iluminação, replay, dashboards e formulário de contato'],
        self::IA => ['HAL (IA)', 'Consultas da IA: informações do InfoClube, avisos e guias do usuário'],
        self::PORTARIA => ['Portaria', 'Monitor de Acesso e câmeras: placas autorizadas, registro de acessos e check-in de ingressos (company-access) e frota'],
        self::HOME_ASSISTANT => ['Home Assistant', 'Automação da iluminação e comando manual dos contatores'],
        self::TELEGRAM => ['Telegram', 'Bot do Telegram: contatos, login por matrícula e cadastro de freelancers'],
        self::WEBHOOK_WHATSAPP => ['Webhook WhatsApp (Poli)', 'Mensagens recebidas pela Poli: bot do WhatsApp e pedidos de acesso do Uber'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function exists(string $scope): bool
    {
        return isset(self::CATALOG[$scope]);
    }

    public static function label(string $scope): string
    {
        return self::CATALOG[$scope][0] ?? $scope;
    }

    public static function description(string $scope): string
    {
        return self::CATALOG[$scope][1] ?? '';
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function catalog(): array
    {
        return self::CATALOG;
    }
}
