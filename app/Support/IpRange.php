<?php

namespace App\Support;

/**
 * IP solto ou faixa CIDR (IPv4), numa lista vinda do `.env`.
 *
 * Serve à faixa da rede do clube no tablet de assinatura
 * (`SIGNATURE_ALLOWED_IPS`) e ao servidor do site externo na API da
 * assinatura remota (`SIGNATURE_REMOTE_ALLOWED_IPS`).
 */
class IpRange
{
    /**
     * @param  array<int, string>  $ranges
     */
    public static function inAny(?string $ip, array $ranges): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        foreach ($ranges as $range) {
            if (self::matches($ip, (string) $range)) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$rede, $bits] = explode('/', $range, 2);

        $ipLong = ip2long($ip);
        $redeLong = ip2long($rede);

        if ($ipLong === false || $redeLong === false) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits < 0 || $bits > 32) {
            return false;
        }

        // /0 pega tudo; o shift de 32 é indefinido em PHP, por isso o caso à parte.
        $mascara = $bits === 0 ? 0 : -1 << (32 - $bits);

        return ($ipLong & $mascara) === ($redeLong & $mascara);
    }
}
