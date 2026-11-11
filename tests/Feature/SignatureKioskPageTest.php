<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A página do tablet de assinatura depois da separação de 11/11/2026:
 * esqueleto em quiosque/index, telas em quiosque/telas, JS em quiosque/js
 * (uma IIFE só, em partes) e CSS pelo Vite.
 *
 * O JS acha cada elemento pelo id (`$('#...')`): se uma tela perder um id,
 * o tablet quebra só quando alguém chega naquela etapa. Aqui, por modo: o
 * balcão leva o núcleo + a diretoria; o Termo de Menores, o núcleo + menores.
 */
class SignatureKioskPageTest extends TestCase
{
    private function idsDoJs(string ...$partes): array
    {
        $ids = [];

        foreach ($partes as $parte) {
            $js = file_get_contents(resource_path("views/quiosque/js/{$parte}.blade.php"));
            preg_match_all("/\\\$\\('#([A-Za-z][\\w-]*)/", $js, $m);
            $ids = array_merge($ids, $m[1]);
        }

        return array_values(array_unique($ids));
    }

    private function confereIds(string $html, array $ids): void
    {
        $this->assertGreaterThan(40, count($ids), 'O padrão de busca dos ids mudou no JS?');

        foreach ($ids as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, "#{$id} é usado no JS do tablet e não está na página");
        }
    }

    public function test_balcao_tem_todo_id_que_o_nucleo_e_a_diretoria_procuram(): void
    {
        $html = $this->withoutVite()->get(route('quiosque.index'))->assertOk()->getContent();

        $this->confereIds($html, $this->idsDoJs('nucleo', 'diretoria', 'inicio'));
    }

    public function test_termo_de_menores_tem_todo_id_que_o_nucleo_e_menores_procuram(): void
    {
        $html = $this->withoutVite()->get(route('quiosque.menores.index'))->assertOk()->getContent();

        $this->confereIds($html, $this->idsDoJs('nucleo', 'menores', 'inicio'));
    }

    public function test_gerencia_tem_todo_id_que_o_js_procura_e_os_dados_do_servidor(): void
    {
        $html = $this->withoutVite()->get(route('quiosque.gerencia.index'))->assertOk()->getContent();
        $js = file_get_contents(resource_path('js/quiosque-gerencia.js'));

        preg_match_all("/\\\$\\('#([A-Za-z][\\w-]*)/", $js, $m);
        $ids = array_values(array_unique($m[1]));
        $this->assertGreaterThan(30, count($ids));

        foreach ($ids as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, "#{$id} é usado no JS da Gerência e não está na página");
        }

        // O que antes era Blade dentro do script chega por window.GERENCIA.
        $this->assertStringContainsString('window.GERENCIA', $html);
        $this->assertStringContainsString('urls: {', $html);
        $this->assertStringContainsString('ociosoSegundos: ', $html);
        $this->assertStringContainsString('class="g-hero q-band"', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_esqueleto_e_identidade(): void
    {
        $html = $this->withoutVite()->get(route('quiosque.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="topbar q-band"', $html);
        $this->assertStringContainsString('class="q-hero q-band"', $html);
        $this->assertStringContainsString(asset('images/logo.png'), $html);
        // Uma IIFE só: abre no núcleo, fecha no início.
        $this->assertSame(1, substr_count($html, "(function () {\n  'use strict';"));
        $this->assertStringContainsString("})();\n", $html);
        // O traço refeito quando o tablet gira.
        $this->assertStringContainsString('A tela girou: faça o desenho de novo.', $html);
    }
}
