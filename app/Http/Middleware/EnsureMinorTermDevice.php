<?php

namespace App\Http\Middleware;

use App\Services\Signature\MinorTerms\KioskDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `signature_minor_device`: o autoatendimento do Termo de Menores só
 * responde a um tablet pareado e dentro do prazo (cookie `lara_minor_device`).
 *
 * Sem isso, a rota pública geraria documento para qualquer aparelho da rede.
 * Recusa com 401 e `device_unpaired`, e o tablet volta à tela de pareamento.
 */
class EnsureMinorTermDevice
{
    public const ATTRIBUTE = 'signature_kiosk_device';

    public function __construct(private KioskDeviceService $devices)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->devices->fromCookie($request->cookie(KioskDeviceService::COOKIE), $request->ip());

        if (!$device) {
            return response()->json([
                'error' => 'Este tablet não está pareado (ou o pareamento venceu). Peça a um responsável do Lara que pareie de novo.',
                'device_unpaired' => true,
            ], 401);
        }

        $request->attributes->set(self::ATTRIBUTE, $device);

        return $next($request);
    }
}
