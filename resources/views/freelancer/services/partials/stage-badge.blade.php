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
        'awaiting_signatures' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        'awaiting_release'    => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        'awaiting_batch'      => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        'in_draft'            => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        'awaiting_manager'    => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'awaiting_director'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'awaiting_payment'    => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'paying'              => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'partially_paid'      => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'paid'                => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        'manager_rejected'    => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'director_rejected'   => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'closed'              => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'empty'               => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        'cancelled'           => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400 line-through',
        'amended'             => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    ];

    $classe = $cores[$stage] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
    $tamanho = ($size ?? 'md') === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-3 py-1 text-xs';
@endphp

<span class="inline-flex items-center rounded-full font-bold whitespace-nowrap {{ $tamanho }} {{ $classe }}">
    {{ $label }}
</span>
