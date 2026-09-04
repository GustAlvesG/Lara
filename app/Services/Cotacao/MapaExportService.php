<?php

namespace App\Services\Cotacao;

use App\Models\CotacaoMapa;
use App\Services\Cotacao\Export\LayoutClassico;
use App\Services\Cotacao\Export\LayoutCompleto;
use App\Services\Cotacao\Export\LayoutExportacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * A porta de entrada da exportação: escolhe o layout e cuida do arquivo.
 *
 * SÃO DOIS LAYOUTS, e a escolha é do comprador na hora de exportar — porque as
 * duas saídas servem a momentos diferentes do mesmo trabalho:
 *
 *   `classico` — o papel da reunião. Reproduz a planilha que a compra já
 *                imprime, assina e arquiva.
 *   `completo` — o arquivo de análise, para decidir na tela.
 *
 * O clássico continua sendo o padrão de propósito: quem pede "exportar" sem
 * pensar quer o de sempre, e o dia em que o novo virar padrão é uma decisão de
 * quem usa, não uma consequência de ter escrito o novo.
 *
 * Gravar em disco e nomear o arquivo mora aqui, e não nos layouts: é igual nos
 * dois, e duplicar daria dois lugares para o caminho divergir.
 */
class MapaExportService
{
    public const LAYOUT_CLASSICO = 'classico';

    public const LAYOUT_COMPLETO = 'completo';

    public const LAYOUT_PADRAO = self::LAYOUT_CLASSICO;

    public function __construct(private readonly MapaCalculoService $calculo)
    {
    }

    /**
     * Os layouts disponíveis, para o menu de exportação montar as opções sem
     * saber os nomes das classes.
     *
     * @return array<string, array{nome: string, descricao: string}>
     */
    public function layouts(): array
    {
        $lista = [];

        foreach ([self::LAYOUT_CLASSICO, self::LAYOUT_COMPLETO] as $chave) {
            $layout = $this->layout($chave);

            $lista[$chave] = [
                'nome' => $layout->nome(),
                'descricao' => $layout->descricao(),
            ];
        }

        return $lista;
    }

    /**
     * Monta a planilha e devolve o caminho do arquivo gerado.
     *
     * Grava em `storage/app/private/cotacao` porque o arquivo é um anexo de uso
     * único: o controller o entrega com `deleteFileAfterSend()`. Ele não tem
     * URL pública e não deve ter — o mapa é documento interno de compra.
     */
    public function gerar(CotacaoMapa $mapa, string $layout = self::LAYOUT_PADRAO): string
    {
        $planilha = $this->montar($mapa, $layout);

        $diretorio = storage_path('app/private/cotacao');

        if (! is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }

        $caminho = $diretorio . DIRECTORY_SEPARATOR . $this->nomeArquivo($mapa, $layout);

        (new XlsxWriter($planilha))->save($caminho);

        // A planilha carrega o documento inteiro em memória; sem isto, exportar
        // vários mapas na mesma requisição acumula tudo até o limite do PHP.
        $planilha->disconnectWorksheets();

        return $caminho;
    }

    /**
     * O documento pronto, ainda em memória. Público para o teste conseguir
     * inspecionar as fórmulas sem passar pelo disco.
     */
    public function montar(CotacaoMapa $mapa, string $layout = self::LAYOUT_PADRAO): Spreadsheet
    {
        return $this->layout($layout)->montar($mapa);
    }

    /**
     * `COTACAO_<slug do título>_<SC>_<dd_mm_aaaa>.xlsx`
     *
     * O layout completo ganha um sufixo. Os dois arquivos costumam conviver na
     * pasta de downloads do comprador, e sem o sufixo o segundo sobrescreveria
     * o primeiro sem avisar.
     */
    public function nomeArquivo(CotacaoMapa $mapa, string $layout = self::LAYOUT_PADRAO): string
    {
        return sprintf(
            'COTACAO_%s_%d_%s%s.xlsx',
            Str::upper(Str::slug($mapa->titulo, '_')) ?: 'MAPA',
            $mapa->questor_solicitacao,
            ($mapa->data_mapa ?? Carbon::today())->format('d_m_Y'),
            $this->normalizar($layout) === self::LAYOUT_COMPLETO ? '_COMPLETO' : ''
        );
    }

    /**
     * O layout pedido, ou o clássico quando o valor não é reconhecido.
     *
     * Cair no padrão em vez de estourar é deliberado: o parâmetro vem da URL, e
     * um link velho ou digitado errado deve entregar a planilha de sempre, não
     * uma página de erro no meio de uma cotação.
     */
    public function layout(string $layout = self::LAYOUT_PADRAO): LayoutExportacao
    {
        return match ($this->normalizar($layout)) {
            self::LAYOUT_COMPLETO => new LayoutCompleto($this->calculo),
            default => new LayoutClassico($this->calculo),
        };
    }

    /**
     * A chave de layout que de fato vai ser usada.
     *
     * Pública porque a trilha registra o layout exportado: gravar o valor cru
     * da URL faria o log dizer "completo" num dia em que saiu o clássico,
     * porque o parâmetro veio escrito errado.
     */
    public function normalizar(string $layout): string
    {
        return in_array($layout, [self::LAYOUT_CLASSICO, self::LAYOUT_COMPLETO], true)
            ? $layout
            : self::LAYOUT_PADRAO;
    }
}
