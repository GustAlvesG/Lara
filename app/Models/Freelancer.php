<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Freelancer extends Model
{
    /** @use HasFactory<\Database\Factories\FreelancerFactory> */
    use HasFactory;

    protected $table = 'freelancers';

    /**
     * Campos que precisam estar preenchidos para o freelancer poder ter um
     * contrato gerado. `name` e `cpf` são sempre exigidos no cadastro, então
     * não entram aqui; `email` é opcional e não sai no contrato.
     *
     * @var array<int, string>
     */
    public const CONTRACT_REQUIRED_FIELDS = [
        'rg',
        'nacionality',
        'civil_status',
        'address',
        'telephone',
    ];

    /**
     * Rótulos dos campos obrigatórios do contrato, para exibição ao usuário.
     *
     * @var array<string, string>
     */
    public const CONTRACT_FIELD_LABELS = [
        'rg' => 'RG',
        'nacionality' => 'nacionalidade',
        'civil_status' => 'estado civil',
        'address' => 'endereço',
        'telephone' => 'telefone',
    ];

    /* ---------------------------------------------------------------------
     | Chave PIX
     |
     | É para ela que o dinheiro do contrato sai, e uma chave errada paga a
     | conta de outra pessoa — já aconteceu. Por isso o tipo da chave é
     | ESCOLHIDO na atualização, e não adivinhado: 11 dígitos tanto podem ser
     | um CPF quanto um celular com DDD, e o palpite errado manda o Pix para
     | um domicílio bancário que não é o do freelancer.
     |---------------------------------------------------------------------*/

    public const PIX_KEY_CPF = 'cpf';
    public const PIX_KEY_CNPJ = 'cnpj';
    public const PIX_KEY_PHONE = 'telefone';
    public const PIX_KEY_EMAIL = 'email';
    public const PIX_KEY_RANDOM = 'aleatoria';

    /** @var array<string, string> */
    public const PIX_KEY_TYPE_LABELS = [
        self::PIX_KEY_CPF => 'CPF',
        self::PIX_KEY_CNPJ => 'CNPJ',
        self::PIX_KEY_PHONE => 'Telefone',
        self::PIX_KEY_EMAIL => 'E-mail',
        self::PIX_KEY_RANDOM => 'Chave aleatória',
    ];

    /**
     * Tipos que o freelancer pode escolher ao corrigir a chave no tablet. CNPJ
     * fica de fora: o contrato é de pessoa física, e o titular da chave tem de
     * ser o próprio freelancer (é o que o Sicoob confere antes de pagar).
     *
     * @var array<int, string>
     */
    public const PIX_KEY_INPUT_TYPES = [
        self::PIX_KEY_CPF,
        self::PIX_KEY_PHONE,
        self::PIX_KEY_EMAIL,
        self::PIX_KEY_RANDOM,
    ];

    protected $fillable = [
        'name',
        'cpf',
        'pix_key',
        'rg',
        'email',
        'nacionality',
        'civil_status',
        'address',
        'telephone',
        'created_by',
        'updated_by',
    ];

    /**
     * Quando a chave PIX não é informada, ela é igual ao CPF. A regra fica no
     * model para valer em qualquer caminho de gravação (painel e API).
     */
    protected static function booted(): void
    {
        static::saving(function (Freelancer $freelancer) {
            if (blank($freelancer->pix_key)) {
                $freelancer->pix_key = $freelancer->cpf;
            }
        });
    }

    /* ---------------------------------------------------------------------
     | Completude do cadastro (pré-requisito para gerar contrato)
     |---------------------------------------------------------------------*/

    /**
     * Campos obrigatórios do contrato que ainda estão em branco.
     *
     * @return array<int, string>
     */
    public function missingContractFields(): array
    {
        return array_values(array_filter(
            self::CONTRACT_REQUIRED_FIELDS,
            fn(string $field) => blank($this->{$field})
        ));
    }

    /**
     * Rótulos (em português) dos campos pendentes, para mensagens ao usuário.
     *
     * @return array<int, string>
     */
    public function missingContractFieldLabels(): array
    {
        return array_map(
            fn(string $field) => self::CONTRACT_FIELD_LABELS[$field] ?? $field,
            $this->missingContractFields()
        );
    }

    /**
     * O cadastro tem todos os dados necessários para gerar um contrato?
     */
    public function hasCompleteContractData(): bool
    {
        return $this->missingContractFields() === [];
    }

    /* ---------------------------------------------------------------------
     | Chave PIX — leitura, formatação e normalização
     |---------------------------------------------------------------------*/

    /** A chave que vale para este freelancer. Sem chave informada, é o CPF. */
    public function pixKey(): string
    {
        return (string) ($this->pix_key ?: $this->cpf);
    }

    public function pixKeyType(): ?string
    {
        return self::pixKeyTypeFor($this->pixKey());
    }

    public function pixKeyTypeLabel(): string
    {
        return self::pixKeyTypeLabelFor($this->pixKey());
    }

    public function pixKeyFormatted(): string
    {
        return self::formatPixKey($this->pixKey());
    }

    /**
     * Que tipo de chave é esta. A leitura é do valor JÁ GRAVADO, que passou
     * pela normalização: telefone tem o `+55` na frente, e é isso que o separa
     * de um CPF de 11 dígitos. Null quando não dá para dizer.
     */
    public static function pixKeyTypeFor(?string $key): ?string
    {
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        if (str_contains($key, '@')) {
            return self::PIX_KEY_EMAIL;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return self::PIX_KEY_RANDOM;
        }

        if (str_starts_with($key, '+')) {
            return self::PIX_KEY_PHONE;
        }

        return match (strlen(self::digits($key))) {
            11 => self::PIX_KEY_CPF,
            14 => self::PIX_KEY_CNPJ,
            default => null,
        };
    }

    public static function pixKeyTypeLabelFor(?string $key): string
    {
        return self::PIX_KEY_TYPE_LABELS[self::pixKeyTypeFor($key)] ?? 'Chave PIX';
    }

    /** A chave como se lê na tela e no contrato — pontuada quando é número. */
    public static function formatPixKey(?string $key): string
    {
        $key = trim((string) $key);
        $digits = self::digits($key);

        return match (self::pixKeyTypeFor($key)) {
            self::PIX_KEY_CPF => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits),
            self::PIX_KEY_CNPJ => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits),
            self::PIX_KEY_PHONE => self::formatPhoneKey($digits),
            default => $key,
        };
    }

    /** "+5524999998888" → "+55 (24) 99999-8888". */
    private static function formatPhoneKey(string $digits): string
    {
        // Sem o país e o DDD não há o que formatar — devolve o que veio.
        if (!preg_match('/^55(\d{2})(\d{4,5})(\d{4})$/', $digits, $m)) {
            return '+' . $digits;
        }

        return "+55 ({$m[1]}) {$m[2]}-{$m[3]}";
    }

    /**
     * A chave como o DICT a espera, a partir do que foi digitado: CPF só
     * dígitos, telefone em `+55DDNNNNNNNNN`, e-mail em minúsculas.
     */
    public static function normalizePixKey(string $type, string $key): string
    {
        $key = trim($key);

        return match ($type) {
            self::PIX_KEY_CPF, self::PIX_KEY_CNPJ => self::digits($key),
            self::PIX_KEY_PHONE => self::normalizePhoneKey(self::digits($key)),
            self::PIX_KEY_EMAIL => mb_strtolower($key),
            self::PIX_KEY_RANDOM => mb_strtolower($key),
            default => $key,
        };
    }

    /**
     * Acrescenta o código do país quando ele não veio. A decisão é pelo
     * TAMANHO, não pelos dois primeiros dígitos: "55999998888" tanto pode ser
     * o DDD 55 sem país quanto o país sem DDD, e o comprimento (11 dígitos =
     * DDD + celular) desfaz o empate.
     */
    private static function normalizePhoneKey(string $digits): string
    {
        return '+' . (strlen($digits) <= 11 ? '55' . $digits : $digits);
    }

    /**
     * O que há de errado com a chave digitada, ou null quando ela serve. Um
     * lugar só, porque a mesma resposta vale para o formulário do painel, para
     * o tablet e para a API.
     */
    public static function pixKeyError(string $type, string $key): ?string
    {
        $key = self::normalizePixKey($type, $key);
        $digits = self::digits($key);

        return match ($type) {
            self::PIX_KEY_CPF => strlen($digits) === 11 ? null : 'O CPF da chave PIX deve ter 11 dígitos.',
            self::PIX_KEY_CNPJ => strlen($digits) === 14 ? null : 'O CNPJ da chave PIX deve ter 14 dígitos.',
            // 55 + DDD + 8 ou 9 dígitos.
            self::PIX_KEY_PHONE => preg_match('/^55\d{2}\d{8,9}$/', $digits)
                ? null
                : 'Informe o telefone com DDD (ex.: 24 99999-8888).',
            self::PIX_KEY_EMAIL => filter_var($key, FILTER_VALIDATE_EMAIL) && mb_strlen($key) <= 77
                ? null
                : 'Informe um e-mail válido (até 77 caracteres).',
            self::PIX_KEY_RANDOM => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $key)
                ? null
                : 'A chave aleatória tem 32 caracteres separados por hífen, como o banco a exibe.',
            default => 'Escolha o tipo da chave PIX.',
        };
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public function freelancerServices()
    {
        return $this->hasMany(FreelancerService::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
