<?php

namespace App\Services\Signature\MinorTerms;

use App\Models\SignatureKioskDevice;
use Illuminate\Support\Str;

/**
 * Pareamento do tablet de autoatendimento.
 *
 * Mesmo desenho do QR de assinatura: token de 64 caracteres em claro só na
 * resposta que desenha o QR, banco com o sha256, uso único e prazo curto. O
 * tablet que lê ganha um segundo segredo — o do cookie — que vale
 * `device_ttl_hours` e também só existe em hash no banco.
 *
 * No modo sem HTTPS (`signature.manual_code.enabled`) o pareamento aceita
 * também um código de 8 caracteres digitado, como a liberação do documento.
 */
class KioskDeviceService
{
    public const COOKIE = 'lara_minor_device';

    private const TOKEN_LENGTH = 64;

    /** O mesmo alfabeto do código digitado do módulo: sem 0/O e 1/I/L. */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 8;

    /**
     * Gera o QR de pareamento. Devolve o conteúdo do QR (e o código digitado,
     * no modo sem HTTPS) em claro — só nesta resposta.
     *
     * @return array{device: SignatureKioskDevice, payload: string, manual_code: ?string}
     */
    public function startPairing(?string $name, ?int $userId, ?string $userName): array
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $codigo = config('signature.manual_code.enabled') ? $this->generateCode() : null;

        $device = SignatureKioskDevice::create([
            'name' => $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 80) : null,
            'pairing_token_hash' => $this->hash($token),
            'pairing_code_hash' => $codigo === null ? null : $this->hash($codigo),
            'pairing_expires_at' => now()->addSeconds((int) config('signature.minor_terms.pairing_ttl_seconds', 300)),
            'paired_by' => $userId,
            'paired_by_name' => $userName,
        ]);

        return [
            'device' => $device,
            'payload' => SignatureKioskDevice::pairingPayload($token),
            'manual_code' => $codigo,
        ];
    }

    /**
     * O tablet leu o QR (ou digitou o código): pareia e devolve o segredo do
     * cookie, em claro só aqui.
     *
     * @return array{device: SignatureKioskDevice, cookie: string}
     *
     * @throws MinorTermException
     */
    public function pair(?string $payload, ?string $code, ?string $ip): array
    {
        $device = null;

        if ($payload !== null && $payload !== '') {
            $token = SignatureKioskDevice::tokenFromPairingPayload($payload);

            if ($token === null) {
                throw new MinorTermException('Este QR Code não é de pareamento do Lara.');
            }

            $device = SignatureKioskDevice::where('pairing_token_hash', $this->hash($token))->first();
        } elseif ($code !== null && config('signature.manual_code.enabled')) {
            $limpo = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

            if (strlen($limpo) === self::CODE_LENGTH) {
                $device = SignatureKioskDevice::where('pairing_code_hash', $this->hash($limpo))
                    ->whereNull('paired_at')
                    ->latest('id')
                    ->first();
            }
        }

        if (!$device || !$device->isAwaitingPairing()) {
            throw new MinorTermException('Código de pareamento inválido ou vencido. Gere outro no computador.');
        }

        $segredo = Str::random(self::TOKEN_LENGTH);

        $device->forceFill([
            'token_hash' => $this->hash($segredo),
            // Uso único: o QR não pareia um segundo aparelho.
            'pairing_token_hash' => null,
            'pairing_code_hash' => null,
            'paired_at' => now(),
            'expires_at' => now()->addHours($this->ttlHours()),
            'last_seen_at' => now(),
            'last_ip' => $ip,
        ])->save();

        return ['device' => $device->fresh(), 'cookie' => $segredo];
    }

    /** O tablet pareado e vivo deste cookie, ou null. */
    public function fromCookie(?string $cookie, ?string $ip = null): ?SignatureKioskDevice
    {
        if (!$cookie) {
            return null;
        }

        $device = SignatureKioskDevice::where('token_hash', $this->hash($cookie))->first();

        if (!$device || !$device->isActive()) {
            return null;
        }

        // Registro de presença sem escrever a cada batimento do tablet.
        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now(), 'last_ip' => $ip])->save();
        }

        return $device;
    }

    public function revoke(SignatureKioskDevice $device, ?string $userName): SignatureKioskDevice
    {
        $device->forceFill([
            'revoked_at' => now(),
            'revoked_by_name' => $userName,
            'pairing_token_hash' => null,
            'pairing_code_hash' => null,
        ])->save();

        return $device;
    }

    public function ttlHours(): int
    {
        return max(1, (int) config('signature.minor_terms.device_ttl_hours', 12));
    }

    private function generateCode(): string
    {
        $codigo = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $codigo .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $codigo;
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
