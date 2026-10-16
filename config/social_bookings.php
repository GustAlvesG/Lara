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
*/

return [
    'contact_business_days' => (int) env('SOCIAL_CONTACT_BUSINESS_DAYS', 2),
];
