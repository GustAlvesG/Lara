<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A página do tablet dos freelancers (/kiosk) depois da separação de
 * 11/11/2026: esqueleto + telas em kiosk/telas + CSS e JS pelo Vite.
 *
 * O JS acha cada elemento pelo id (`$('#...')`): se uma tela perder um id na
 * separação, o tablet quebra só na hora em que alguém chega naquela etapa.
 * Este teste pega isso antes.
 */
class KioskPageTest extends TestCase
{
    private function pagina(): string
    {
        return $this->withoutVite()->get('/kiosk')->assertOk()->getContent();
    }

    public function test_monta_as_dezenove_telas_e_o_esqueleto(): void
    {
        $html = $this->pagina();

        foreach (['s-login', 's-mode', 's-coord', 's-lote', 's-cpf', 's-busca', 's-busca-lista', 's-dia', 's-falta',
                  's-cadastro', 's-completar', 's-menu', 's-novo', 's-previa', 's-contratos', 's-aditivo',
                  's-adit-previa', 's-comissao', 's-com-previa', 's-pix', 's-assinar', 's-pin', 's-janta'] as $tela) {
            $this->assertStringContainsString('id="' . $tela . '"', $html, "Tela {$tela} sumiu");
        }

        $this->assertStringContainsString('window.KIOSK', $html);
        $this->assertStringContainsString('k-hero k-band', $html);
        $this->assertStringContainsString(asset('images/logo.png'), $html);
    }

    public function test_todo_id_que_o_js_procura_existe_na_pagina(): void
    {
        $html = $this->pagina();
        $js = file_get_contents(resource_path('js/kiosk.js'));

        preg_match_all("/\\\$\\('#([A-Za-z][\\w-]*)'\\)/", $js, $m);
        $ids = array_unique($m[1]);

        $this->assertGreaterThan(100, count($ids), 'O padrão de busca dos ids mudou no JS?');

        // Ids que o próprio JS cria ao montar o documento a assinar.
        $criadosPeloJs = ['sigSlot', 'sigPh', 'sigCanvas'];

        foreach (array_diff($ids, $criadosPeloJs) as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, "#{$id} é usado no kiosk.js e não está na página");
        }
    }

    public function test_comentario_blade_nao_vaza_como_texto(): void
    {
        // Antes da separação, um {{-- --}} dentro do @verbatim aparecia como
        // texto na prévia da comissão.
        $this->assertStringNotContainsString('{{--', $this->pagina());
        $this->assertStringNotContainsString('MultiVendas apurou? Diga por quê', $this->pagina());
    }
}
