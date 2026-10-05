@php
    /**
     * Selo da etapa do trâmite. Recebe $stage (chave de
     * FreelancerService::TRACKING_STAGES) e $label, e opcionalmente $size
     * ('sm' | 'md').
     *
     * A cor é decidida aqui, e não no model: é leitura de tela. A escala tem
     * três degraus e um significado — cinza é "ainda não é da vez", âmbar é
     * "está com alguém agora", verde é "acabou bem", vermelho é "acabou mal".
     * Sem isso a tela vira um mural de etiquetas coloridas sem hierarquia.
     */
    $cores = [
        'awaiting_signatures' => 'bg-subtle text-ink',
        'awaiting_release'    => 'bg-grena-tint text-grena-ink',
        'awaiting_batch'      => 'bg-subtle text-ink',
        'in_draft'            => 'bg-subtle text-ink',
        'awaiting_manager'    => 'bg-warn-soft text-warn',
        'awaiting_director'   => 'bg-warn-soft text-warn',
        'awaiting_payment'    => 'bg-grena-tint text-grena-ink',
        'paying'              => 'bg-grena-tint text-grena-ink',
        'partially_paid'      => 'bg-grena-tint text-grena-ink',
        'paid'                => 'bg-ok-soft text-ok',
        'manager_rejected'    => 'bg-danger-soft text-grena-ink',
        'director_rejected'   => 'bg-danger-soft text-grena-ink',
        'closed'              => 'bg-danger-soft text-grena-ink',
        'empty'               => 'bg-line text-ink-2',
        'cancelled'           => 'bg-line text-ink-2 line-through',
        'amended'             => 'bg-line text-ink-2',
    ];

    $classe = $cores[$stage] ?? 'bg-subtle text-ink';
    $tamanho = ($size ?? 'md') === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-3 py-1 text-xs';
@endphp

<span class="inline-flex items-center rounded-full font-bold whitespace-nowrap {{ $tamanho }} {{ $classe }}">
    {{ $label }}
</span>
