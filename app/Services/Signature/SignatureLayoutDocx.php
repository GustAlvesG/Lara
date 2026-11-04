<?php

namespace App\Services\Signature;

use App\Models\SignatureLayout;
use App\Support\DocxWriter;

/**
 * O papel timbrado do módulo (cabeçalho e rodapé do Clube) no Word.
 *
 * O contrato exportado em Word sai com a mesma cara do PDF de assinatura:
 * a imagem do cabeçalho, a do rodapé e a linha de texto do rodapé, na mesma
 * medida (`SignaturePageGeometry`, que é quem calcula o tamanho no PDF) e com
 * o mesmo alinhamento. "De borda a borda" vira imagem presa à borda do papel.
 *
 * Sem imagem de cabeçalho, sai o cabeçalho de texto padrão do PDF (nome do
 * Clube em grená e o título). Usa o papel timbrado VIGENTE: o Word é cópia de
 * trabalho, e o documento congelado guarda o seu.
 *
 * Devolve o `$pages` de `DocxWriter::package()`.
 */
class SignatureLayoutDocx
{
    /** Largura útil do texto no Word, em px (A4 menos as margens 1418 + 1134 twips). */
    private const TEXT_WIDTH_PX = (DocxWriter::PAGE_WIDTH - 1418 - 1134) / DocxWriter::TWIPS_PER_PX;

    private const CLUB = 'Clube dos Funcionários da CSN';

    /**
     * @return array{header: array, footer?: array, margins: array{top: int, bottom: int, header: int, footer: int}}
     */
    public function pages(?SignatureLayout $layout, string $title): array
    {
        $geometry = new SignaturePageGeometry($layout, false);
        $sangra = $geometry->fullWidth();
        $px = DocxWriter::TWIPS_PER_PX;

        $margens = ['top' => 1418, 'bottom' => 1418, 'header' => 709, 'footer' => 709];

        // Cabeçalho.
        $cabecalho = $this->image($layout, 'header', $geometry->headerImage(), $sangra);

        if ($cabecalho) {
            $header = [
                'xml' => DocxWriter::paragraph(
                    [DocxWriter::picture('rIdImgHeader', 1, $cabecalho['width'], $cabecalho['height'], $sangra ? 'top' : null)],
                    ['jc' => $sangra ? 'left' : $geometry->align()],
                ),
                'media' => ['rIdImgHeader' => ['bytes' => $cabecalho['bytes'], 'ext' => $cabecalho['ext']]],
            ];
            // Como no PDF: 28 px de respiro acima (nenhum, sangrando) e 12 abaixo.
            $margens['header'] = $sangra ? 0 : 28 * $px;
            $margens['top'] = max(1134, ($cabecalho['height'] + ($sangra ? 28 : 40)) * $px);
        } else {
            $header = [
                'xml' => DocxWriter::paragraph([DocxWriter::run(self::CLUB, ['b' => true, 'color' => 'A00001', 'sz' => 23])], ['jc' => 'left'])
                    . DocxWriter::paragraph([DocxWriter::run($title, ['color' => '6D6062', 'sz' => 16])], ['jc' => 'left', 'border' => true]),
            ];
        }

        // Rodapé: a linha de texto e, embaixo dela, a imagem.
        $rodape = $this->image($layout, 'footer', $geometry->footerImage(), $sangra);
        $texto = $geometry->footerText();
        $footer = null;

        if ($rodape || $texto !== null) {
            $xml = $texto !== null
                ? DocxWriter::paragraph([DocxWriter::run($texto, ['color' => '4A4042', 'sz' => 16])], ['jc' => 'left'])
                : '';
            $media = [];
            $alturaTexto = $texto !== null ? 22 : 0;

            if ($rodape) {
                $xml .= DocxWriter::paragraph(
                    [DocxWriter::picture('rIdImgFooter', 2, $rodape['width'], $rodape['height'], $sangra ? 'bottom' : null)],
                    ['jc' => $sangra ? 'left' : $geometry->align()],
                );
                $media['rIdImgFooter'] = ['bytes' => $rodape['bytes'], 'ext' => $rodape['ext']];
            }

            $footer = ['xml' => $xml, 'media' => $media];

            if ($rodape && $sangra) {
                // A imagem fica na borda do papel; o texto sobe para cima dela.
                $margens['footer'] = ($rodape['height'] + 6) * $px;
                $margens['bottom'] = max(1134, $margens['footer'] + ($alturaTexto + 16) * $px + 240);
            } else {
                $margens['footer'] = 22 * $px;
                $margens['bottom'] = max(1134, (22 + ($rodape['height'] ?? 0) + $alturaTexto + 24) * $px);
            }
        }

        return array_filter([
            'header' => $header,
            'footer' => $footer,
            'margins' => $margens,
        ], fn ($v) => $v !== null);
    }

    /**
     * Bytes, extensão e tamanho de uma das imagens. Fora a de borda a borda,
     * que vai até a beira do papel, a largura cabe na área de texto do Word
     * (um pouco mais estreita que a do PDF).
     *
     * @param  'header'|'footer'  $which
     * @param  array{width: int, height: int}|null  $fit
     * @return array{bytes: string, ext: string, width: int, height: int}|null
     */
    private function image(?SignatureLayout $layout, string $which, ?array $fit, bool $sangra): ?array
    {
        $bytes = $layout?->imageBytes($which);

        if ($fit === null || $bytes === null || !($info = @getimagesizefromstring($bytes))) {
            return null;
        }

        [$largura, $altura] = [$fit['width'], $fit['height']];

        if (!$sangra && $largura > self::TEXT_WIDTH_PX) {
            $altura = (int) round($altura * self::TEXT_WIDTH_PX / $largura);
            $largura = (int) floor(self::TEXT_WIDTH_PX);
        }

        return [
            'bytes' => $bytes,
            'ext' => $info['mime'] === 'image/png' ? 'png' : 'jpeg',
            'width' => max(1, $largura),
            'height' => max(1, $altura),
        ];
    }
}
