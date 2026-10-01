<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\CardTemplate;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Carteirinhas repaginadas: modelos em cartões com frente e verso (busca na
 * página, imagem sumida cai no substituto), o editor de posição dos campos e
 * a emissão — sem perder o que a impressão e a câmera precisam.
 *
 * Models não salvos, sem banco; usuário mock.
 */
class CarteirinhasScreensTest extends TestCase
{
    use RendersScreens;

    private function modelo(int $id, array $attributes = []): CardTemplate
    {
        return (new CardTemplate)->forceFill(array_merge([
            'id' => $id,
            'name' => 'Funcionário 2026',
            'front_image' => 'cards/frente.png',
            'back_image' => 'cards/verso.png',
            'is_active' => true,
            'layout' => null,
            'card_width_mm' => 54,
            'card_height_mm' => 85.6,
        ], $attributes));
    }

    private function quemEmite()
    {
        return $this->usuario(new UserAccess([P::CARTEIRINHAS]));
    }

    public function test_modelos_em_cartoes_com_frente_verso_e_busca(): void
    {
        $html = $this->tela($this->quemEmite(), 'card-templates.index', [], 'card-templates.index', [
            'templates' => collect([$this->modelo(1), $this->modelo(2, ['name' => 'Estagiário', 'is_active' => false])]),
        ]);

        $this->assertStringContainsString('src="' . asset('images/cards/frente.png') . '"', $html);
        $this->assertStringContainsString('src="' . asset('images/cards/verso.png') . '"', $html);
        // Arquivo que sumiu do disco: o <img> sai e fica o substituto.
        $this->assertStringContainsString('onerror="this.remove()"', $html);

        $this->assertStringContainsString('laraSearch(', $html);
        $this->assertStringContainsString('data-search="Estagiário inativo"', $html);
        $this->assertStringContainsString('Inativo', $html);
        $this->assertStringContainsString(route('card-templates.edit', 1), $html);

        $this->assertStringContainsString('aria-label="Páginas de Carteirinhas"', $html);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('card-templates.index'), '#') . '"\s+aria-current="page"#', $html);
        $this->assertStringNotContainsString('bootstrap-grid', $html);
    }

    public function test_editor_do_modelo_sem_grid_do_bootstrap(): void
    {
        $html = $this->tela($this->quemEmite(), 'card-templates.edit', [3], 'card-templates.edit', ['template' => $this->modelo(3)]);

        $this->assertStringContainsString('x-data="cardTemplateEditor(', $html);
        $this->assertStringContainsString('action="' . route('card-templates.update', 3) . '"', $html);
        $this->assertStringContainsString('Salvar alterações', $html);
        // O rodapé usava .row/.col-2, que sem o bootstrap-grid desmontava.
        $this->assertStringNotContainsString('class="row', $html);
        $this->assertStringNotContainsString('bootstrap-grid', $html);
        $this->assertStringContainsString(route('card-templates.index'), $html);

        $novo = $this->tela($this->quemEmite(), 'card-templates.create', [], 'card-templates.create', []);
        $this->assertStringContainsString('Cadastrar modelo', $novo);
        $this->assertStringContainsString('action="' . route('card-templates.store') . '"', $novo);
    }

    public function test_emissao_mantem_camera_e_impressao(): void
    {
        $html = $this->tela($this->quemEmite(), 'id-cards.issue', [], 'card-issuer.create', [
            'templates' => collect([$this->modelo(1)]),
        ]);

        $this->assertStringContainsString('x-data="cardIssuer(', $html);
        $this->assertStringContainsString('id="print-area"', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('x-on:click="startCamera()"', $html);
        $this->assertStringContainsString('x-on:click="takePhoto()"', $html);
        $this->assertStringContainsString('@click="printCard()"', $html);
        // O select de modelo agora é o componente — com as opções dentro dele.
        $this->assertMatchesRegularExpression('#<select[^>]*id="template"[^>]*>\s*<template x-for="t in templates"#', $html);
        $this->assertStringNotContainsString('#A00001', substr($html, strpos($html, 'x-data="cardIssuer(')));
        $this->assertStringContainsString('aria-label="Páginas de Carteirinhas"', $html);
    }

    public function test_emissao_sem_modelo_ativo_aponta_o_cadastro(): void
    {
        $html = $this->tela($this->quemEmite(), 'id-cards.issue', [], 'card-issuer.create', ['templates' => collect()]);

        $this->assertStringContainsString('Nenhum modelo de carteirinha ativo.', $html);
        $this->assertStringContainsString(route('card-templates.create'), $html);
    }
}
