<?php

/*
|--------------------------------------------------------------------------
| Reservas de espaços sociais
|--------------------------------------------------------------------------
|
| `contact_business_days`: dias úteis (segunda a sexta, sem feriados) que a
| equipe tem, depois da pré-reserva, para entrar em contato com o sócio. O
| Lara avisa no sino quando a pré-reserva chega e de novo quando o prazo vence
| sem contato registrado (`social:contact-reminders`).
|
| `gallery_root`: pasta onde a galeria de fotos dos espaços grava
| (`<raiz>/galeria/{espaço}/`). Vazio = `public/images`, que é o que o
| servidor web entrega como arquivo estático. Só os testes mudam.
|
*/

return [
    'contact_business_days' => (int) env('SOCIAL_CONTACT_BUSINESS_DAYS', 2),
    'gallery_root' => null,
];
