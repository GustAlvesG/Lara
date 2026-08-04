<?php

namespace App\Exceptions\Sicoob;

/**
 * A chave Pix do cadastro pertence a outra pessoa.
 *
 * A iniciação DICT devolve o CPF/CNPJ do dono da chave; comparado com o CPF do
 * freelancer, ele não bate. Chave digitada errada, chave portada para outro
 * titular ou cadastro trocado — em qualquer dos casos, confirmar transferiria
 * o dinheiro para um terceiro.
 *
 * A iniciação NÃO move dinheiro, então parar aqui não deixa nada pela metade.
 */
class SicoobPayeeMismatchException extends SicoobException
{
    public function __construct(
        public readonly ?string $expectedDocument = null,
        public readonly ?string $actualDocument = null,
        public readonly ?string $actualName = null,
        array $context = [],
    ) {
        parent::__construct(
            'A chave Pix informada pertence a outro titular.',
            $context
        );
    }

    public function userMessage(): string
    {
        return 'A chave PIX cadastrada pertence a outra pessoa'
            . ($this->actualName ? ' (' . $this->actualName . ')' : '')
            . '. Nenhum valor foi transferido — confira a chave no cadastro do freelancer.';
    }
}
