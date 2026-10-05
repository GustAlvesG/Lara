<?php

namespace Tests\Unit;

use App\Exceptions\DocxImportException;
use App\Services\Signature\DocxTemplateImporter;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\BuildsDocx;

/**
 * O importador é o que deixa alguém escrever um modelo sem saber HTML. O que
 * ele não pode fazer é mudar o documento no caminho: perder um campo, deixar
 * uma tag no meio de um marcador ou renumerar uma cláusula.
 *
 * PHPUnit puro, sem subir o Laravel — o importador só lê um arquivo.
 */
class DocxTemplateImporterTest extends TestCase
{
    use BuildsDocx;

    protected function tearDown(): void
    {
        $this->apagaDocxTemporarios();

        parent::tearDown();
    }

    public function test_o_que_a_pessoa_escreve_entre_colchetes_vira_o_campo_e_o_rotulo(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Locação do espaço [[Nome do Espaço]] em [[Data do evento]], por R$ [[valor_total]].'),
        ));

        $this->assertSame(
            '<p>Locação do espaço [[nome_do_espaco]] em [[data_do_evento]], por R$ [[valor_total]].</p>',
            $resultado['html'],
        );

        // O tipo é um palpite pelo nome do campo; a tela mostra e deixa trocar.
        $this->assertSame([
            ['key' => 'nome_do_espaco', 'label' => 'Nome do Espaço', 'required' => true, 'type' => 'text'],
            ['key' => 'data_do_evento', 'label' => 'Data do evento', 'required' => true, 'type' => 'date'],
            ['key' => 'valor_total', 'label' => 'Valor total', 'required' => true, 'type' => 'money'],
        ], $resultado['variables']);
    }

    public function test_o_mesmo_campo_escrito_duas_vezes_e_um_campo_so(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Espaço: [[Espaço]].') . $this->docxP('Devolver o [[espaço]] limpo.'),
        ));

        $this->assertCount(1, $resultado['variables']);
        $this->assertSame('espaco', $resultado['variables'][0]['key']);
        $this->assertSame(2, substr_count($resultado['html'], '[[espaco]]'));
    }

    /**
     * O Word grava um marcador digitado de uma vez em vários trechos — basta o
     * corretor sublinhar uma palavra ou a pessoa aplicar negrito em parte. Sem
     * juntar, a tag cairia no meio e o `[[...]]` nunca seria substituído.
     */
    public function test_marcador_partido_em_trechos_pelo_word_e_reunido(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            '<w:p>'
            . $this->docxR('Valor: [[Va')
            . $this->docxR('lor to', '<w:b/>')
            . $this->docxR('tal]] reais.')
            . '</w:p>',
        ));

        $this->assertSame('<p>Valor: [[valor_total]] reais.</p>', $resultado['html']);
        $this->assertSame('Valor total', $resultado['variables'][0]['label']);
    }

    public function test_formatacao_de_caractere_atravessa(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            '<w:p>'
            . $this->docxR('O ')
            . $this->docxR('LOCADOR', '<w:b/>')
            . $this->docxR(' e o ')
            . $this->docxR('locatário', '<w:i/><w:u w:val="single"/>')
            // `w:val="0"` é negrito DESLIGADO — o Word grava assim ao desfazer um estilo.
            . $this->docxR(' acordam.', '<w:b w:val="0"/>')
            . '</w:p>',
        ));

        $this->assertSame(
            '<p>O <strong>LOCADOR</strong> e o <em><u>locatário</u></em> acordam.</p>',
            $resultado['html'],
        );
    }

    public function test_assinatura_sozinha_na_linha_sai_sem_paragrafo_em_volta(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Declaro estar ciente.') . $this->docxP('  [[Assinatura]] '),
        ));

        $this->assertSame("<p>Declaro estar ciente.</p>\n[[assinatura]]", $resultado['html']);
        $this->assertTrue($resultado['has_signature']);
        // A assinatura é o marcador reservado, e não um campo do atendente.
        $this->assertSame([], $resultado['variables']);
    }

    public function test_assinatura_com_nome_declara_a_parte_que_assina_ali(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('As partes assinam.')
            . $this->docxP('[[Assinatura: Contratante]]')
            . $this->docxP('[[assinatura - Contratado]]'),
        ));

        $this->assertSame(
            "<p>As partes assinam.</p>\n[[assinatura:contratante]]\n[[assinatura:contratado]]",
            $resultado['html'],
        );

        $this->assertSame([
            ['key' => 'contratante', 'label' => 'Contratante'],
            ['key' => 'contratado', 'label' => 'Contratado'],
        ], $resultado['parties']);

        $this->assertTrue($resultado['has_signature']);
        // Parte não é campo do atendente.
        $this->assertSame([], $resultado['variables']);
    }

    /**
     * No Word, pôr duas assinaturas lado a lado é fazer uma tabela. Essa
     * tabela é layout: saem os marcadores, vizinhos, e não uma tabela com
     * borda em volta de cada assinatura.
     */
    public function test_tabela_so_de_assinaturas_vira_marcadores_vizinhos(): void
    {
        $celula = fn(string $texto) => '<w:tc>' . $this->docxP($texto) . '</w:tc>';

        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Assinam:')
            . '<w:tbl><w:tr>' . $celula('[[assinatura: Contratante]]') . $celula('[[assinatura: Contratado]]')
            . '</w:tr></w:tbl>',
        ));

        $this->assertSame(
            "<p>Assinam:</p>\n[[assinatura:contratante]]\n[[assinatura:contratado]]",
            $resultado['html'],
        );
    }

    public function test_sem_marcador_de_assinatura_o_resultado_avisa(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx($this->docxP('Texto fixo.')));

        $this->assertFalse($resultado['has_signature']);
    }

    /**
     * A numeração automática vira texto. Uma lista HTML recomeçaria do 1
     * depois do parágrafo intercalado, e a cláusula 2 viraria cláusula 1.
     */
    public function test_numeracao_automatica_sobrevive_a_paragrafo_intercalado(): void
    {
        $numerado = fn(int $nivel) => '<w:numPr><w:ilvl w:val="' . $nivel . '"/><w:numId w:val="1"/></w:numPr>';

        $numbering = $this->docxXml(
            'numbering',
            '<w:abstractNum w:abstractNumId="0">'
            . '<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>'
            . '<w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1.%2."/></w:lvl>'
            . '</w:abstractNum>'
            . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>',
        );

        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Objeto', $numerado(0))
            . $this->docxP('Parágrafo comum no meio.')
            . $this->docxP('Obrigações', $numerado(0))
            . $this->docxP('Devolver o espaço.', $numerado(1))
            . $this->docxP('Respeitar o horário.', $numerado(1))
            . $this->docxP('Foro', $numerado(0)),
            ['word/numbering.xml' => $numbering],
        ));

        $this->assertSame(implode("\n", [
            '<p>1. Objeto</p>',
            '<p>Parágrafo comum no meio.</p>',
            '<p>2. Obrigações</p>',
            '<p>2.1. Devolver o espaço.</p>',
            '<p>2.2. Respeitar o horário.</p>',
            '<p>3. Foro</p>',
        ]), $resultado['html']);
    }

    /**
     * Título numerado do Word: o parágrafo só diz o estilo, o estilo aponta a
     * lista, e é o NÍVEL da lista que diz qual estilo ele numera.
     */
    public function test_numeracao_que_vem_do_estilo_tambem_vira_texto(): void
    {
        $styles = $this->docxXml(
            'styles',
            '<w:style w:type="paragraph" w:styleId="Ttulo1"><w:name w:val="heading 1"/>'
            . '<w:pPr><w:numPr><w:numId w:val="4"/></w:numPr></w:pPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Ttulo2"><w:name w:val="heading 2"/>'
            . '<w:pPr><w:numPr><w:numId w:val="4"/></w:numPr></w:pPr></w:style>',
        );

        $numbering = $this->docxXml(
            'numbering',
            '<w:abstractNum w:abstractNumId="0">'
            . '<w:lvl w:ilvl="0"><w:numFmt w:val="upperRoman"/><w:pStyle w:val="Ttulo1"/><w:lvlText w:val="%1 -"/></w:lvl>'
            . '<w:lvl w:ilvl="1"><w:numFmt w:val="lowerLetter"/><w:pStyle w:val="Ttulo2"/><w:lvlText w:val="%2)"/></w:lvl>'
            . '</w:abstractNum>'
            . '<w:num w:numId="4"><w:abstractNumId w:val="0"/></w:num>',
        );

        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Objeto', '<w:pStyle w:val="Ttulo1"/>')
            . $this->docxP('Foro', '<w:pStyle w:val="Ttulo1"/>')
            . $this->docxP('Comarca', '<w:pStyle w:val="Ttulo2"/>')
            // `numId` 0 direto no parágrafo: o Word dizendo "este título não é numerado".
            . $this->docxP('Anexo', '<w:pStyle w:val="Ttulo1"/><w:numPr><w:numId w:val="0"/></w:numPr>'),
            ['word/styles.xml' => $styles, 'word/numbering.xml' => $numbering],
        ));

        $this->assertSame(
            "<h2>I - Objeto</h2>
<h2>II - Foro</h2>
<h3>a) Comarca</h3>
<h2>Anexo</h2>",
            $resultado['html'],
        );
    }

    public function test_marcadores_viram_lista(): void
    {
        $marcador = '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr>';

        $numbering = $this->docxXml(
            'numbering',
            '<w:abstractNum w:abstractNumId="3">'
            . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl>'
            . '</w:abstractNum>'
            . '<w:num w:numId="7"><w:abstractNumId w:val="3"/></w:num>',
        );

        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Trazer:') . $this->docxP('Documento', $marcador) . $this->docxP('Comprovante', $marcador),
            ['word/numbering.xml' => $numbering],
        ));

        $this->assertSame(
            '<p>Trazer:</p><ul><li>Documento</li><li>Comprovante</li></ul>',
            preg_replace('/\s+/', '', $resultado['html']),
        );
    }

    public function test_estilo_de_titulo_e_reconhecido_pelo_nome_e_nao_pelo_id(): void
    {
        // O Word em português grava o id `Ttulo1` e o nome `heading 1`.
        $styles = $this->docxXml(
            'styles',
            '<w:style w:type="paragraph" w:styleId="Ttulo1"><w:name w:val="heading 1"/></w:style>',
        );

        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Do objeto', '<w:pStyle w:val="Ttulo1"/>') . $this->docxP('Texto da cláusula.'),
            ['word/styles.xml' => $styles],
        ));

        // h2, e não h1: o <h1> é o título do documento, impresso pelo renderer.
        $this->assertStringContainsString('<h2>Do objeto</h2>', $resultado['html']);
    }

    public function test_tabela_e_linha_em_branco(): void
    {
        $celula = fn(string $texto) => '<w:tc>' . $this->docxP($texto) . '</w:tc>';

        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            $this->docxP('Dados:')
            . '<w:p/>'
            . '<w:tbl><w:tr>' . $celula('Telefone') . $celula('[[Telefone]]') . '</w:tr></w:tbl>',
        ));

        $this->assertSame(
            '<p>Dados:</p><table><tr><td>Telefone</td><td>[[telefone]]</td></tr></table>',
            preg_replace('/\s+/', '', $resultado['html']),
        );
    }

    public function test_texto_removido_em_revisao_nao_volta_e_o_inserido_entra(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            '<w:p>'
            . $this->docxR('Multa de ')
            . '<w:del><w:r><w:delText>dez</w:delText></w:r></w:del>'
            . '<w:ins>' . $this->docxR('vinte') . '</w:ins>'
            . $this->docxR(' por cento.')
            . '</w:p>',
        ));

        $this->assertSame('<p>Multa de vinte por cento.</p>', $resultado['html']);
    }

    public function test_imagem_gera_aviso_e_nao_derruba_a_importacao(): void
    {
        $resultado = (new DocxTemplateImporter())->import($this->criaDocx(
            '<w:p><w:r><w:drawing/></w:r>' . $this->docxR('Termo') . '</w:p>',
        ));

        $this->assertSame('<p>Termo</p>', $resultado['html']);
        $this->assertCount(1, $resultado['warnings']);
    }

    public function test_arquivo_que_nao_e_docx_e_recusado_com_mensagem_para_a_pessoa(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($caminho, '%PDF-1.4 isto não é um zip');

        try {
            $this->expectException(DocxImportException::class);
            $this->expectExceptionMessage('Salvar como');

            (new DocxTemplateImporter())->import($caminho);
        } finally {
            @unlink($caminho);
        }
    }

    public function test_xml_com_doctype_e_recusado(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'docx');

        $zip = new \ZipArchive();
        $zip->open($caminho, \ZipArchive::OVERWRITE);
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>&e;</w:t></w:r></w:p></w:body></w:document>',
        );
        $zip->close();

        try {
            $this->expectException(DocxImportException::class);

            (new DocxTemplateImporter())->import($caminho);
        } finally {
            @unlink($caminho);
        }
    }

    public function test_documento_sem_texto_e_recusado(): void
    {
        $this->expectException(DocxImportException::class);
        $this->expectExceptionMessage('não tem texto');

        (new DocxTemplateImporter())->import($this->criaDocx('<w:p/>'));
    }

    public function test_chave_sempre_obedece_a_regra_do_formulario(): void
    {
        $this->assertSame('data_do_evento', DocxTemplateImporter::keyFor(' Data do Evento '));
        $this->assertSame('campo_2a_via', DocxTemplateImporter::keyFor('2ª via'));
        $this->assertSame('', DocxTemplateImporter::keyFor(' — '));
        $this->assertMatchesRegularExpression(
            '/^[a-z][a-z0-9_]{0,59}$/',
            DocxTemplateImporter::keyFor(str_repeat('nome bem comprido ', 10)),
        );
    }
}
