<?php

namespace App\Services\Signature;

use App\Models\SignatureLayout;

/**
 * As medidas da página de um documento: margens, cabeçalho, rodapé e a faixa
 * do visto.
 *
 * Existe porque três coisas precisam concordar ao pixel — o CSS do documento
 * (`@page` e os blocos fixos), a imagem do papel timbrado e o desenho do visto,
 * que é feito direto na página depois de o PDF estar montado. Com as contas
 * espalhadas pelo Blade e pelo renderer, mudar a altura do rodapé empurraria o
 * visto para cima do texto sem ninguém perceber.
 *
 * Tudo em pixels CSS (96 por polegada), que é a unidade do documento. O visto
 * é desenhado em pontos; a conversão está em `PT_PER_PX`.
 *
 * De cima para baixo, o pé da página é:
 *
 *     texto do documento
 *     [ faixa do visto ]         só quando o modelo exige
 *     validação + texto do rodapé
 *     [ imagem do rodapé ]       só quando o papel timbrado tem
 *     borda do papel
 *
 * Sem papel timbrado e sem visto, as medidas são as que o documento sempre
 * teve (margens de 96 e 78 px).
 */
class SignaturePageGeometry
{
    /** A4 a 96 dpi. */
    public const PAGE_WIDTH = 794;
    public const PAGE_HEIGHT = 1123;

    /** Margem lateral do texto. */
    public const SIDE = 56;

    public const PX_PER_MM = 3.7795;
    public const PT_PER_PX = 0.75;

    public const VISTO_BOX_WIDTH = 112;
    public const VISTO_BOX_HEIGHT = 40;
    public const VISTO_GAP = 8;

    /** Altura reservada ao visto: a caixa mais o rótulo embaixo dela. */
    private const VISTO_ZONE = 62;

    /** Cabeçalho de texto padrão (nome do clube + título). */
    private const DEFAULT_HEADER = 56;

    /** Bloco da validação: borda, respiro e duas linhas miúdas. */
    private const VALIDATION_BLOCK = 40;

    private const FOOTER_TEXT_LINE = 14;

    /** @var array<string, array{src: string, width: int, height: int}|null> */
    private array $images = [];

    public function __construct(
        public readonly ?SignatureLayout $layout,
        public readonly bool $initials,
    ) {
    }

    /**
     * A imagem do cabeçalho já na medida em que entra na página, como data
     * URI — o arquivo mora no disco privado e não tem URL.
     *
     * @return array{src: string, width: int, height: int}|null
     */
    public function headerImage(): ?array
    {
        return $this->image('header');
    }

    /**
     * @return array{src: string, width: int, height: int}|null
     */
    public function footerImage(): ?array
    {
        return $this->image('footer');
    }

    public function fullWidth(): bool
    {
        return (bool) $this->layout?->full_width;
    }

    public function align(): string
    {
        return $this->layout?->align ?? 'left';
    }

    public function footerText(): ?string
    {
        $texto = trim((string) $this->layout?->footer_text);

        return $texto === '' ? null : $texto;
    }

    public function headerHeight(): int
    {
        return $this->headerImage()['height'] ?? self::DEFAULT_HEADER;
    }

    /** O cabeçalho encosta na borda de cima do papel? */
    private function headerBleeds(): bool
    {
        return $this->fullWidth() && $this->headerImage() !== null;
    }

    private function footerBleeds(): bool
    {
        return $this->fullWidth() && $this->footerImage() !== null;
    }

    public function marginTop(): int
    {
        // 40 = 28 de respiro acima do cabeçalho + 12 abaixo; sangrando, o
        // respiro de cima deixa de existir.
        return $this->headerHeight() + ($this->headerBleeds() ? 28 : 40);
    }

    /** `top` do cabeçalho fixo, relativo ao início do texto. */
    public function headerTop(): int
    {
        return -($this->marginTop() - ($this->headerBleeds() ? 0 : 28));
    }

    /** Altura do bloco de texto do rodapé: validação e, se houver, a linha da empresa. */
    public function footerTextHeight(): int
    {
        return self::VALIDATION_BLOCK + ($this->footerText() !== null ? self::FOOTER_TEXT_LINE : 0);
    }

    private function footerImageHeight(): int
    {
        return $this->footerImage()['height'] ?? 0;
    }

    /** Distância do fim do rodapé até a borda de baixo do papel. */
    private function edge(): int
    {
        return $this->footerBleeds() ? 0 : 22;
    }

    private function vistoZone(): int
    {
        return $this->initials ? self::VISTO_ZONE : 0;
    }

    public function marginBottom(): int
    {
        return 16 + $this->vistoZone() + $this->footerTextHeight() + $this->footerImageHeight() + $this->edge();
    }

    /** `bottom` da imagem do rodapé, relativo ao fim do texto. */
    public function footerImageBottom(): int
    {
        return -($this->marginBottom() - $this->edge());
    }

    /** `bottom` do bloco de texto do rodapé — logo acima da imagem. */
    public function footerTextBottom(): int
    {
        return $this->footerImageBottom() + $this->footerImageHeight();
    }

    /** Onde começa a faixa do visto, a partir do TOPO do papel. */
    public function vistoTop(): int
    {
        return self::PAGE_HEIGHT - $this->marginBottom() + 12;
    }

    /**
     * @param  'header'|'footer'  $which
     * @return array{src: string, width: int, height: int}|null
     */
    private function image(string $which): ?array
    {
        if (array_key_exists($which, $this->images)) {
            return $this->images[$which];
        }

        return $this->images[$which] = $this->fit($which);
    }

    /**
     * @param  'header'|'footer'  $which
     * @return array{src: string, width: int, height: int}|null
     */
    private function fit(string $which): ?array
    {
        $bytes = $this->layout?->imageBytes($which);

        if ($bytes === null) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if (!$info || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        [$largura, $altura] = $info;

        if ($this->fullWidth()) {
            // De borda a borda: quem manda é a largura do papel, e a altura
            // sai da proporção da imagem.
            $escala = self::PAGE_WIDTH / $largura;
        } else {
            $caixaAltura = ($which === 'header'
                ? $this->layout->header_height_mm
                : $this->layout->footer_height_mm) * self::PX_PER_MM;

            $escala = min((self::PAGE_WIDTH - 2 * self::SIDE) / $largura, $caixaAltura / $altura);
        }

        return [
            'src' => 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes),
            'width' => max(1, (int) round($largura * $escala)),
            'height' => max(1, (int) round($altura * $escala)),
        ];
    }
}
