<?php

/*
| Disparo de E-mails (docs/funcionalidades/disparo-emails.md).
*/
return [
    // Quantos e-mails o `email-campaigns:send` manda por minuto, somando todas
    // as campanhas em andamento. É o freio contra o limite do provedor SMTP
    // (o SMTP2GO recusa e pode suspender a conta acima do plano): 120/min dá
    // 7.200 por hora.
    'per_minute' => (int) env('EMAIL_CAMPAIGNS_PER_MINUTE', 120),

    // Teto de destinatários por campanha. A lista é gravada linha a linha no
    // banco; acima disso a importação recusa o que passar.
    'max_recipients' => (int) env('EMAIL_CAMPAIGNS_MAX_RECIPIENTS', 50000),

    // Endereços que a tela oferece como remetente, separados por vírgula. Só
    // ponha aqui endereço de domínio verificado no provedor SMTP, senão o
    // e-mail sai sem SPF/DKIM e cai no spam. Vazio = só o MAIL_FROM_ADDRESS.
    'from_addresses' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EMAIL_CAMPAIGNS_FROM_ADDRESSES', env('MAIL_FROM_ADDRESS', '')))
    ))),

    // Mailer de config/mail.php usado no disparo. Nulo = o padrão
    // (MAIL_MAILER). Serve para mandar as campanhas por outra conta SMTP sem
    // mexer nos e-mails do sistema.
    'mailer' => env('EMAIL_CAMPAIGNS_MAILER'),

    // Tentativas por destinatário antes de marcar "Falhou". Recusa definitiva
    // do servidor (código 5xx: caixa inexistente, domínio inválido) falha na
    // primeira.
    'max_attempts' => 3,

    // Falhas seguidas que interrompem a rodada: com o servidor SMTP fora, não
    // adianta gastar a lista inteira em tentativas. A rodada seguinte, um
    // minuto depois, tenta de novo.
    'max_consecutive_failures' => 5,

    // Imagem enviada pelo editor: vai embutida no e-mail (O Lara não é
    // alcançável da internet, então link para cá não abriria no destinatário).
    'image_max_kb' => 1024,
];
