<?php

namespace Tests\Feature\Rebrand;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\View\Navigation;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Feature\Rebrand\Concerns\RendersScreens;
use Tests\TestCase;

/**
 * Assinatura eletrônica no visual novo e no catálogo de acesso: o módulo
 * entra no menu por permissão, as listas têm busca e as páginas próprias
 * (tablet e validação pública) usam a paleta do rebrand.
 *
 * Models não salvos, sem banco; usuário mock.
 */
class AssinaturasScreensTest extends TestCase
{
    use RendersScreens;

    private function miolo(string $html): string
    {
        $inicio = strpos($html, '<main>');
        $fim = strpos($html, '<footer');

        return substr($html, (int) $inicio, $fim === false ? null : $fim - (int) $inicio);
    }

    private function filhos(array $permissoes): array
    {
        $nav = Navigation::build($this->usuario(new UserAccess($permissoes)), $this->requisicaoEm('dashboard'));
        $grupo = collect($nav['links'])->firstWhere('label', 'Assinaturas');

        return $grupo ? array_column($grupo['children'], 'label') : [];
    }

    public function test_menu_mostra_assinaturas_conforme_a_permissao(): void
    {
        // Quem só consulta os assinados vê Documentos, e não os Modelos. O Guia
        // acompanha qualquer parte do módulo que a pessoa alcance.
        $this->assertSame(['Documentos', 'Guia'], $this->filhos([P::ASSINATURA_CONSULTAR]));
        $this->assertSame(['Documentos', 'Guia'], $this->filhos([P::ASSINATURA_DOCUMENTOS]));
        $this->assertSame(['Modelos', 'Guia'], $this->filhos([P::ASSINATURA_MODELOS]));
        $this->assertSame(['Documentos', 'Modelos', 'Guia'], $this->filhos([P::ASSINATURA_DOCUMENTOS, P::ASSINATURA_MODELOS]));
        // Sem nenhuma, o módulo não aparece.
        $this->assertSame([], $this->filhos([P::SIV_BUSCA]));
    }

    public function test_documentos_com_busca_no_servidor_e_sem_paleta_antiga(): void
    {
        $html = $this->tela($this->usuario(new UserAccess([P::ASSINATURA_DOCUMENTOS])), 'signature-documents.index', [], 'signature.documents.index', [
            'documents' => new LengthAwarePaginator([], 0, 20, 1, ['path' => route('signature-documents.index')]),
            'busca' => 'termo de imagem',
            'status' => null,
        ], ['q' => 'termo de imagem']);

        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('value="termo de imagem"', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('Nenhum documento com esses filtros.', $html);
        $this->assertStringContainsString(route('signature-documents.create'), $html);
        $this->assertStringContainsString('aria-label="Páginas de Assinaturas"', $html);
        $this->assertDoesNotMatchRegularExpression('#\b(?:bg|text|border)-(?:gray|indigo|red|green|amber)-\d{2,3}\b|\[\#A00001\]#', $this->miolo($html));
    }

    public function test_paginas_proprias_usam_a_paleta_do_rebrand(): void
    {
        foreach (['quiosque/index', 'signature/validate'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));

            // Ação em grená; o carmim (#A00001) não pinta mais superfície.
            $this->assertStringContainsString('--brand:#8a1538', $source, $view);
            $this->assertStringNotContainsStringIgnoringCase('--brand:#A00001', $source, $view);
            $this->assertStringContainsString('Unbounded', $source, $view);
        }
    }

    public function test_nenhuma_tela_do_painel_de_assinatura_ficou_na_paleta_antiga(): void
    {
        $views = array_merge(
            glob(resource_path('views/signature/documents/*.blade.php')),
            glob(resource_path('views/signature/documents/partials/*.blade.php')),
            glob(resource_path('views/signature/templates/*.blade.php')),
            glob(resource_path('views/signature/templates/partials/*.blade.php')),
        );
        $this->assertNotEmpty($views);

        foreach ($views as $view) {
            $source = file_get_contents($view);
            $this->assertDoesNotMatchRegularExpression('#\b(?:bg|text|border)-(?:gray|indigo|red|green|amber)-\d{2,3}\b|\[\#A00001\]#', $source, $view);
            // @class dentro de class="" não funciona no Blade.
            $this->assertDoesNotMatchRegularExpression('#class="[^"]*@class\(#s', $source, $view);
        }
    }
}
