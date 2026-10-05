<?php

namespace Tests\Unit;

use App\Support\ArchivePath;
use PHPUnit\Framework\TestCase;

/** Nomes de pasta e de arquivo do servidor de arquivos. */
class ArchivePathTest extends TestCase
{
    public function test_nome_sai_sem_acento_e_sem_caractere_proibido(): void
    {
        $this->assertSame('Termo uso emprestimo de quadra 2026', ArchivePath::clean('Termo: uso/empréstimo de "quadra" <2026>?', 80));
        $this->assertSame('Joao Antonio da Conceicao', ArchivePath::clean('  João Antônio da Conceição. ', 60));
        $this->assertSame('', ArchivePath::clean('???', 60));
        // O corte não deixa espaço nem ponto na ponta: Windows os recusa em nome de pasta.
        $this->assertSame('Maria', ArchivePath::clean('Maria de Souza', 6));
    }

    public function test_a_quantidade_de_signatarios_aparece_nos_nomes(): void
    {
        $this->assertSame('Ana', ArchivePath::signers(['Ana']));
        $this->assertSame('Ana e Beto', ArchivePath::signers(['Ana', 'Beto']));
        $this->assertSame('Ana e Beto e mais 1', ArchivePath::signers(['Ana', 'Beto', 'Caio']));
        $this->assertSame('Ana e Beto e mais 3', ArchivePath::signers(['Ana', 'Beto', 'Caio', 'Duda', 'Edu']));
        // Nome vazio não conta nem ocupa lugar.
        $this->assertSame('Ana e Caio', ArchivePath::signers(['Ana', '', 'Caio']));
        $this->assertSame('', ArchivePath::signers([]));
    }

    public function test_pasta_da_pessoa_nunca_fica_sem_nome(): void
    {
        $this->assertSame('Maria de Souza', ArchivePath::person('Maria de Souza'));
        $this->assertSame('Sem nome', ArchivePath::person(null));
        $this->assertSame('Sem nome', ArchivePath::person('***'));
    }

    public function test_nome_do_arquivo_junta_so_os_trechos_que_existem(): void
    {
        $this->assertSame('2026-10-03 - Ana e Beto - 6W5Y.pdf', ArchivePath::file(['2026-10-03', null, 'Ana e Beto', '6W5Y']));
        $this->assertSame('2026-10-03 - Contrato - C12.pdf', ArchivePath::file(['2026-10-03', 'Contrato', 'C12']));
    }
}
