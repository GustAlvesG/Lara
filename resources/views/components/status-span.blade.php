@php
// Normaliza a chave para garantir que a verificação funcione
$status_key = strtolower($status->id ?? '');

// Define as cores padrão
$bg_color = '#F3F4F6'; // gray-100 (Fundo Padrão)
$text_color = '#1F2937'; // gray-800 (Texto Padrão)

if ($status_key === '1') {
    // Cores para Status Confirmado (Baseado no Tailwind green-100/800)
    $bg_color = '#D1FAE5' ;// green-100
    $text_color = '#065F46'; // green-800
} elseif ($status_key === '3') {
    // Cores para Status Pendente (Baseado no Tailwind yellow-100/800)
    $bg_color = '#FEF3C7'; // yellow-100
    $text_color = '#92400E'; // yellow-800
} elseif ($status_key === '0') {
    // Cores para Status Cancelado (Baseado no Tailwind red-100/800)
    $bg_color = '#FEE2E2'; // red-100
    $text_color = '#991B1B'; // red-800
}
else {
    // Cores para Status Não Definido (Baseado no Tailwind gray-100/800)
    $bg_color = '#F3F4F6'; // gray-100
    $text_color = '#1F2937'; // gray-800
}

// Concatena as classes base do Tailwind que não são de cor (para manter o formato)
$base_classes = 'font-bold bg-indigo-500 px-2 py-1 rounded-full shadow-md'
@endphp

<span class="{{ $base_classes }}" 
    style="background-color: {{ $bg_color }}; color: {{ $text_color }};">

{{ Str::ucfirst($status->portuguese ?? 'Status Não Definido') }}

</span>
    