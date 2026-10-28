<?php

namespace Tests\Feature;

use App\Services\Guides\GuideMarkdown;
use App\Services\Guides\UserGuides;
use App\View\Navigation;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Confere os guias de verdade (docs/guias): a busca da Lara (IA) depende do
 * cabeçalho e das anotações estarem certos, e um erro ali não quebra nada à
 * vista — só faz a IA mandar link errado ou dizer que a pessoa não tem
 * acesso. Este teste é o que pega isso.
 */
class UserGuidesContentTest extends TestCase
{
    /** Guias que não são de um módulo do menu e valem para todos. */
    private const FORA_DO_MENU = ['Primeiros passos'];

    /** @return array<string, array{0: string}> */
    public static function guias(): array
    {
        $files = glob(dirname(__DIR__, 2) . '/docs/' . UserGuides::DIRECTORY . '/*.md') ?: [];

        return collect($files)->mapWithKeys(fn ($f) => [basename($f) => [$f]])->all();
    }

    public function test_existe_pelo_menos_um_guia(): void
    {
        $this->assertNotEmpty(self::guias());
    }

    #[DataProvider('guias')]
    public function test_cabecalho_titulo_e_duvidas_comuns(string $file): void
    {
        $markdown = (string) file_get_contents($file);
        [$meta, $body] = GuideMarkdown::splitFrontMatter($markdown);

        $this->assertMatchesRegularExpression('/^[a-z0-9-]+\.md$/', basename($file), 'Nome do arquivo: só minúsculas, números e hífen (a tela Documentação recusa acento e espaço).');

        $modulos = array_unique(array_column(Navigation::destinations(), 'modulo'));
        $this->assertContains($meta['modulo'] ?? null, array_merge($modulos, self::FORA_DO_MENU), 'modulo: precisa ser o nome do módulo como está no menu.');
        $this->assertNotEmpty(GuideMarkdown::list($meta['palavras'] ?? ''), 'palavras: vazio.');

        $this->assertSame(1, preg_match_all('/^# /m', $body), 'Um único título # por guia.');
        $this->assertMatchesRegularExpression('/^## Dúvidas comuns\s*$/m', $body, 'Falta a seção "## Dúvidas comuns".');
    }

    #[DataProvider('guias')]
    public function test_anotacoes_logo_abaixo_do_titulo_e_com_telas_do_menu(string $file): void
    {
        [, $body] = GuideMarkdown::splitFrontMatter((string) file_get_contents($file));
        $destinations = Navigation::destinations();

        $previous = null;
        foreach (explode("\n", $body) as $number => $line) {
            $annotation = GuideMarkdown::annotation($line);

            if ($annotation !== null) {
                $this->assertTrue(
                    $previous !== null && (preg_match('/^#{2,3}\s/', $previous) || GuideMarkdown::annotation($previous) !== null),
                    basename($file) . ':' . ($number + 1) . ' — a anotação precisa vir logo abaixo de um título ## ou ###, senão é ignorada.'
                );

                [$kind, $values] = $annotation;
                foreach ($values as $value) {
                    if ($kind === 'telas') {
                        $this->assertArrayHasKey($value, $destinations, basename($file) . ": tela {$value} não está no menu.");
                        $this->assertTrue(Route::has($value), basename($file) . ": rota {$value} não existe.");
                    }
                }
            }

            if (trim($line) !== '') {
                $previous = $line;
            }
        }
    }

    #[DataProvider('guias')]
    public function test_vira_secoes_com_texto(string $file): void
    {
        $sections = (new UserGuides())->parse(pathinfo($file, PATHINFO_FILENAME), (string) file_get_contents($file));

        $this->assertGreaterThan(2, count($sections));
        foreach ($sections as $section) {
            $this->assertStringNotContainsString('<!--', $section['texto'], basename($file) . ' › ' . $section['titulo']);
        }
    }
}
