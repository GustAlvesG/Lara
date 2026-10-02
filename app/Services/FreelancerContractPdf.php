<?php

namespace App\Services;

use App\Models\FreelancerService;

/**
 * O contrato do freelancer (contrato, termo aditivo ou termo de comissão) em
 * PDF.
 *
 * Até aqui o documento só existia como página: o painel o imprime pelo
 * navegador e o tablet o exibe. Para ir ao servidor de arquivos ele precisa
 * ser um arquivo — e é o MESMO documento, montado pelo mesmo parcial
 * (`partials/contract-document`) com a redação que o contrato firmou e a
 * qualificação congelada na assinatura. Só a folha de estilo é outra, porque
 * o DomPDF não entende a do navegador (ver a view `freelancer.services.pdf`).
 */
class FreelancerContractPdf
{
    public function html(FreelancerService $service): string
    {
        $service->loadMissing(['freelancer', 'functionFreelancer', 'director', 'baseService']);

        return view('freelancer.services.pdf', ['service' => $service])->render();
    }

    /** Os bytes do PDF. */
    public function render(FreelancerService $service): string
    {
        // Instância nova a cada documento: o wrapper guardado no container
        // acumularia as páginas do anterior.
        return app('dompdf.wrapper')
            ->loadHTML($this->html($service))
            ->setPaper('a4')
            ->output();
    }
}
