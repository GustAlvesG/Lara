<?php

namespace App\Services\MultiClubes;

use Illuminate\Support\Carbon;

/**
 * Uma pessoa de título ativo no MultiClubes, com o que os torneios precisam
 * para conferir a categoria (sexo e nascimento) e avisar por e-mail.
 *
 * `gender` = `male` | `female` | null (código desconhecido no MultiClubes).
 * `phone` e `barcode` servem às Contas do site (cadastro manual e "trazer do
 * MultiClubes").
 */
final class MemberProfile
{
    public function __construct(
        public readonly string $name,
        public readonly string $cpf,
        public readonly string $title,
        public readonly ?Carbon $birthDate,
        public readonly ?string $gender,
        public readonly ?string $email,
        public readonly bool $titular,
        public readonly ?string $phone = null,
        public readonly ?string $barcode = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'cpf' => $this->cpf,
            'title' => $this->title,
            'birth_date' => $this->birthDate?->toDateString(),
            'gender' => $this->gender,
            'email' => $this->email,
            'titular' => $this->titular,
            'phone' => $this->phone,
            'barcode' => $this->barcode,
        ];
    }
}
