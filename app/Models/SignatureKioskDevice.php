<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tablet de autoatendimento pareado — o do Termo de Menores.
 *
 * O pareamento é o que impede que qualquer aparelho da rede gere termos: o
 * autoatendimento é rota pública, e só responde com o cookie de um tablet
 * pareado e dentro do prazo. Um usuário com permissão gera o QR de pareamento
 * no computador, o tablet lê, e fica pareado por
 * `signature.minor_terms.device_ttl_hours` (12 h).
 *
 * O banco guarda só hashes — do QR, do código digitado e do cookie.
 *
 * @property int $id
 * @property ?Carbon $pairing_expires_at
 * @property ?Carbon $paired_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $revoked_at
 */
class SignatureKioskDevice extends Model
{
    /** Prefixo do QR de pareamento. O tablet ignora QR que não começa com ele. */
    public const PAIRING_PREFIX = 'LARA-PAIR:v1:';

    protected $fillable = [
        'name',
        'pairing_token_hash',
        'pairing_code_hash',
        'pairing_expires_at',
        'token_hash',
        'paired_at',
        'expires_at',
        'paired_by',
        'paired_by_name',
        'revoked_at',
        'revoked_by_name',
        'last_seen_at',
        'last_ip',
    ];

    protected $hidden = [
        'pairing_token_hash',
        'pairing_code_hash',
        'token_hash',
    ];

    protected $casts = [
        'pairing_expires_at' => 'datetime',
        'paired_at' => 'datetime',
        'expires_at' => 'datetime',
        'paired_by' => 'integer',
        'revoked_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /** Pareado, não revogado e dentro do prazo. */
    public function isActive(): bool
    {
        return $this->paired_at !== null
            && $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /** O QR de pareamento ainda pode ser lido? */
    public function isAwaitingPairing(): bool
    {
        return $this->paired_at === null
            && $this->revoked_at === null
            && $this->pairing_expires_at !== null
            && $this->pairing_expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'Revogado',
            $this->isActive() => 'Pareado',
            $this->isAwaitingPairing() => 'Aguardando leitura do QR',
            $this->paired_at !== null => 'Vencido',
            default => 'QR não lido',
        };
    }

    public function label(): string
    {
        return $this->name ?: 'Tablet #' . $this->id;
    }

    public static function pairingPayload(string $token): string
    {
        return self::PAIRING_PREFIX . $token;
    }

    /** O token de um QR de pareamento lido pela câmera; null fora do formato. */
    public static function tokenFromPairingPayload(?string $payload): ?string
    {
        $payload = trim((string) $payload);

        if (!str_starts_with($payload, self::PAIRING_PREFIX)) {
            return null;
        }

        $token = substr($payload, strlen(self::PAIRING_PREFIX));

        return preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1 ? $token : null;
    }
}
