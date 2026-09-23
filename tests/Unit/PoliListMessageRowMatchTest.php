<?php

namespace Tests\Unit;

use App\Models\PoliListMessage;
use Tests\TestCase;

/**
 * Casamento entre a resposta recebida e a linha do menu que a produziu.
 *
 * Sem banco: o model não é salvo, só serve de casca para o `rows`.
 */
class PoliListMessageRowMatchTest extends TestCase
{
    private function menu(): PoliListMessage
    {
        return new PoliListMessage([
            'rows' => [
                ['title' => 'Financeiro', 'description' => 'Consulte seus débitos ou outras pendências.'],
                ['title' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi'],
                ['title' => 'Sem Descrição', 'description' => null],
            ],
        ]);
    }

    /**
     * O texto que chega é "título descrição" — a quebra já virou espaço no
     * parser.
     */
    public function test_acha_a_linha_pelo_titulo_mais_descricao(): void
    {
        $linha = $this->menu()->rowForAnswer('Carro de Aplicativo Carro, moto ou táxi');

        $this->assertSame('Carro de Aplicativo', $linha['title']);
    }

    /**
     * O título sozinho NÃO casa quando a linha tem descrição. É o que impede
     * que uma mensagem digitada em resposta ao menu — que preencheria o
     * contexto igualzinho a um toque — passe por toque no botão.
     */
    public function test_titulo_sozinho_nao_casa_quando_a_linha_tem_descricao(): void
    {
        $this->assertNull($this->menu()->rowForAnswer('Carro de Aplicativo'));
    }

    /** Linha sem descrição casa pelo título, que é tudo o que ela tem. */
    public function test_linha_sem_descricao_casa_pelo_titulo(): void
    {
        $linha = $this->menu()->rowForAnswer('Sem Descrição');

        $this->assertSame('Sem Descrição', $linha['title']);
    }

    /**
     * Espaçamento e caixa não derrubam o casamento: quem garante a origem é o
     * contexto, e aqui só se descobre QUAL opção foi tocada.
     */
    public function test_tolera_espacamento_e_caixa(): void
    {
        $linha = $this->menu()->rowForAnswer("  CARRO DE APLICATIVO \n  Carro,  moto ou táxi  ");

        $this->assertSame('Carro de Aplicativo', $linha['title']);
    }

    public function test_nao_casa_com_texto_de_fora_do_menu(): void
    {
        $this->assertNull($this->menu()->rowForAnswer('Carro de Aplicativo - Uber'));
        $this->assertNull($this->menu()->rowForAnswer('Portaria 2'));
        $this->assertNull($this->menu()->rowForAnswer(''));
        $this->assertNull($this->menu()->rowForAnswer(null));
    }

    /** Opção diferente do mesmo menu devolve a linha dela, não a do carro. */
    public function test_distingue_as_opcoes_do_mesmo_menu(): void
    {
        $linha = $this->menu()->rowForAnswer('Financeiro Consulte seus débitos ou outras pendências.');

        $this->assertSame('Financeiro', $linha['title']);
    }
}
