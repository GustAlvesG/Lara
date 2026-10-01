<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  bool  $bootstrapGrid  O bootstrap-grid força com !important os
     *                               espaçamentos 3/4/5 (px-4 vira 1,5rem) e dá
     *                               row/col às telas antigas. As telas já
     *                               repaginadas passam `:bootstrap-grid="false"`
     *                               para usar a escala do Tailwind; o arquivo sai
     *                               de vez quando a última tela for adaptada.
     * @param  bool  $cover  Capa da área com as abas, na navegação por Módulos.
     *                       Sai com `:cover="false"` em tela que precisa da
     *                       altura toda (chat, quiosque). Ações da página na
     *                       capa: `<x-slot:cover-actions>`.
     */
    public function __construct(public bool $bootstrapGrid = true, public bool $cover = true)
    {
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
