<?php

namespace Tests\Feature\EmailCampaigns;

use App\Services\EmailCampaign\EmailComposer;
use Tests\TestCase;

/**
 * Editor visual do Disparo de E-mails: o que o contenteditable produz é
 * limpo (sanitize) e vira um e-mail com estilo dentro das tags, no layout do
 * clube (compose). Sem banco.
 */
class EmailComposerTest extends TestCase
{
    private EmailComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new EmailComposer();
    }

    public function test_limpa_o_que_o_editor_nao_produz(): void
    {
        $clean = $this->composer->sanitize(
            '<div style="color:red" onclick="x()">Olá <b>Ana</b> <i>!</i></div>'
            . '<script>alert(1)</script>'
            . '<h1>Título</h1>'
            . '<p><span style="font-size:40px">grande</span> <font color="red">vermelho</font></p>'
            . '<p><a href="javascript:alert(1)">mau</a> <a href="https://clube.test" target="_blank" onmouseover="x">bom</a></p>'
            . '<img src="data:image/png;base64,AAAA"><img src="https://clube.test/a.png" onerror="x">'
        );

        $this->assertSame(
            '<p>Olá <strong>Ana</strong> <em>!</em></p>'
            . '<h2>Título</h2>'
            . '<p>grande vermelho</p>'
            . '<p>mau <a href="https://clube.test">bom</a></p>'
            . '<img src="https://clube.test/a.png" alt="">',
            $clean
        );
    }

    public function test_guarda_botao_alinhamento_e_variavel_no_link(): void
    {
        $clean = $this->composer->sanitize(
            '<p style="text-align: center;"><a class="email-button outra" href="https://clube.test/?e={{email}}">Ir</a></p>'
            . '<ul><li>um</li><li style="text-align:right">dois</li></ul>'
        );

        $this->assertSame(
            '<p style="text-align:center;"><a href="https://clube.test/?e={{email}}" class="email-button">Ir</a></p>'
            . '<ul><li>um</li><li style="text-align:right;">dois</li></ul>',
            $clean
        );
    }

    public function test_monta_o_email_no_layout_com_estilo_em_cada_tag(): void
    {
        $html = $this->composer->compose(
            '<h2>Festa</h2><p style="text-align:center">Olá, {{primeiro_nome}}!</p>'
            . '<p><a class="email-button" href="https://clube.test">Garanta</a></p><hr>',
            'Clube <Social>'
        );

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Clube &lt;Social&gt;', $html);
        $this->assertStringContainsString('Para não receber mais', $html);
        $this->assertStringContainsString('{{email}}', $html);

        $this->assertMatchesRegularExpression('/<h2 style="margin:0 0 14px;font-family:Arial[^"]*font-size:24px/', $html);
        // O alinhamento escolhido vem depois do padrão, e vence.
        $this->assertMatchesRegularExpression('/<p style="margin:0 0 16px;[^"]*text-align:center;">Olá, \{\{primeiro_nome\}\}!<\/p>/', $html);
        $this->assertMatchesRegularExpression('/<a href="https:\/\/clube.test" style="display:inline-block;[^"]*background-color:#a00001;[^"]*">Garanta<\/a>/', $html);
        $this->assertStringNotContainsString('class="email-button"', $html);
        $this->assertStringContainsString('<hr style="border:0;', $html);
    }

    public function test_sem_cabecalho_usa_o_nome_do_sistema_e_vazio_nao_quebra(): void
    {
        config(['mail.from.name' => 'Clube dos Funcionários']);

        $html = $this->composer->compose('', null);

        $this->assertStringContainsString('Clube dos Funcionários', $html);
        $this->assertSame('', $this->composer->sanitize('   '));
    }
}
