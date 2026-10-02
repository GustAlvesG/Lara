<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;

/**
 * O guia do usuário do módulo de assinatura: como preparar o Word, cadastrar
 * o modelo, emitir o documento e encontrar o assinado.
 *
 * Mora dentro do sistema, ao lado de Documentos e Modelos, para estar onde a
 * dúvida aparece — e para não existir uma cópia em PDF circulando por e-mail
 * que ninguém atualiza. A página e o PDF saem da MESMA view
 * (`signature.guide.content`): mudou o texto, mudaram os dois.
 */
class GuideController extends Controller
{
    public function index()
    {
        return view('signature.guide.index');
    }

    /**
     * O guia em si, como documento HTML completo. É o que a página mostra
     * dentro do iframe: ele traz o CSS dele, que não se mistura com o do
     * painel.
     */
    public function content()
    {
        return response($this->html(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // Só dentro do próprio sistema.
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function pdf()
    {
        $pdf = app('dompdf.wrapper')
            ->setOption([
                'defaultMediaType' => 'print',
                // O PDF vai por e-mail e é impresso: com a fonte inteira
                // embutida ele pesaria cinco vezes mais.
                'isFontSubsettingEnabled' => true,
            ])
            ->loadHTML($this->html())
            ->setPaper('a4');

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $fonte = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $cinza = [0.43, 0.38, 0.38];

        $canvas->page_text(51, 812, 'Assinatura de documentos no Lara — guia do dia a dia', $fonte, 7.5, $cinza);
        $canvas->page_text(490, 812, 'página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 7.5, $cinza);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="guia-assinatura-de-documentos.pdf"',
        ]);
    }

    private function html(): string
    {
        // O endereço da tela do tablet sai da rota: o guia não pode ensinar um
        // endereço que o sistema já não usa. Entra por troca de texto porque a
        // view é um bloco verbatim só (ver o comentário no topo dela).
        return str_replace(
            '%%ENDERECO_DO_TABLET%%',
            e((string) parse_url(route('quiosque.index'), PHP_URL_PATH)),
            view('signature.guide.content')->render(),
        );
    }
}
