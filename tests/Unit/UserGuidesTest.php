<?php

namespace Tests\Unit;

use App\Services\DocumentationService;
use App\Services\Guides\GuideMarkdown;
use App\Services\Guides\UserGuides;
use Tests\TestCase;

/**
 * O recorte dos guias do usuário em seções (o que a busca da Lara IA lê) e a
 * exibição deles na tela Documentação. Sem banco.
 */
class UserGuidesTest extends TestCase
{
    private const GUIA = <<<'MD'
---
modulo: SIV
palavras: placa, carro, veículo
---

# SIV — guia de uso

O SIV identifica **veículos** pela placa.

## Buscar uma placa
<!-- telas: parking.search -->

Na tela **SIV › Busca**, digite a [placa](https://exemplo).

| Campo | O que é |
|---|---|
| Placa | Sem traço |

### Placa não encontrada

Confira se digitou `ABC1D23` certo.

#### Detalhe

Fica dentro da seção de cima.

## Dúvidas comuns

### Buscar uma placa

Título repetido de propósito: a âncora ganha sufixo.
MD;

    private function sections(): array
    {
        return (new UserGuides('/inexistente'))->parse('siv', self::GUIA);
    }

    public function test_quebra_o_guia_em_secoes_por_titulo(): void
    {
        $titulos = array_column($this->sections(), 'titulo');

        // "Dúvidas comuns" não tem texto próprio (só ###) e não vira seção.
        $this->assertSame(['Visão geral', 'Buscar uma placa', 'Placa não encontrada', 'Buscar uma placa'], $titulos);
        $this->assertSame('SIV — guia de uso', $this->sections()[0]['titulo_guia']);
        $this->assertSame('SIV', $this->sections()[0]['modulo']);
        $this->assertSame('placa carro veículo', $this->sections()[0]['palavras']);
    }

    public function test_subsecao_herda_as_telas_do_titulo_de_cima(): void
    {
        [, $busca, $naoEncontrada, $duvida] = $this->sections();

        $this->assertSame(['parking.search'], $busca['telas']);
        $this->assertSame(['parking.search'], $naoEncontrada['telas']);
        $this->assertSame('Buscar uma placa', $naoEncontrada['pai']);
        // Debaixo de "Dúvidas comuns", que não tem anotação: nada herdado.
        $this->assertSame([], $duvida['telas']);
        $this->assertSame('Dúvidas comuns', $duvida['pai']);
    }

    public function test_texto_puro_sem_markdown_e_sem_anotacao(): void
    {
        [$visao, $busca, $naoEncontrada] = $this->sections();

        $this->assertSame('O SIV identifica veículos pela placa.', $visao['texto']);
        $this->assertStringContainsString('Na tela SIV › Busca, digite a placa.', $busca['texto']);
        $this->assertStringContainsString('Placa — Sem traço', $busca['texto']);
        $this->assertStringNotContainsString('telas:', $busca['texto']);
        $this->assertStringNotContainsString('---', $busca['texto']);
        $this->assertStringContainsString('ABC1D23', $naoEncontrada['texto']);
        $this->assertStringContainsString('Fica dentro da seção de cima.', $naoEncontrada['texto']);
    }

    public function test_ancora_repetida_ganha_sufixo(): void
    {
        $ancoras = array_column($this->sections(), 'ancora');

        $this->assertSame(['siv-guia-de-uso', 'buscar-uma-placa', 'placa-nao-encontrada', 'buscar-uma-placa-1'], $ancoras);
    }

    public function test_documentacao_esconde_cabecalho_e_anotacoes_e_usa_as_mesmas_ancoras(): void
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'guia') . '.md';
        file_put_contents($arquivo, self::GUIA);

        try {
            $html = app(DocumentationService::class)->render($arquivo);
        } finally {
            @unlink($arquivo);
        }

        $this->assertStringNotContainsString('modulo:', $html);
        $this->assertStringNotContainsString('telas:', $html);

        // Cada âncora que a busca devolve existe na tela.
        foreach (array_column($this->sections(), 'ancora') as $ancora) {
            $this->assertStringContainsString('id="' . $ancora . '"', $html);
        }
    }

    public function test_arquivo_sem_cabecalho_nao_quebra(): void
    {
        $sections = (new UserGuides('/inexistente'))->parse('solto', "# Solto\n\n## Uma tarefa\n\nTexto.\n");

        $this->assertSame('', $sections[0]['modulo']);
        $this->assertSame('Uma tarefa', $sections[0]['titulo']);
        $this->assertSame('Solto', $sections[0]['titulo_guia']);
    }

    public function test_doc_comum_continua_igual(): void
    {
        $md = "# Título\n\nTexto com <!-- comentário qualquer --> no meio.\n";

        $this->assertSame($md, GuideMarkdown::forDisplay($md));
    }
}
