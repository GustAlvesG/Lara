<?php

namespace App\Exceptions;

/**
 * Salvar o contrato com uma cópia velha (05/11/2026): outra pessoa gravou o
 * texto depois que esta tela abriu. O editor avisa "alguém salvou antes de
 * você" em vez de gravar por cima — decisão do usuário.
 */
class ContractConflictException extends ContractException
{
    public function __construct(
        public readonly ?string $savedBy,
        public readonly ?\DateTimeInterface $savedAt,
        public readonly int $revision,
    ) {
        parent::__construct(
            ($savedBy ?: 'Outra pessoa') . ' salvou este contrato'
            . ($savedAt ? ' às ' . $savedAt->format('H:i') . ' de ' . $savedAt->format('d/m') : '')
            . ' depois que você abriu esta tela. Para não apagar o que foi gravado, as suas alterações não foram salvas.'
        );
    }
}
