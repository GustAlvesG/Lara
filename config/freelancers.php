<?php

return [

    /*
    | A diretoria (nome, e-mail que recebe os códigos do lote e a imagem da
    | assinatura) NÃO mora mais aqui: é cadastrada pela gerência na tela
    | Serviços / Contratos → Diretoria, tabela `freelancer_directors`. As
    | variáveis FREELANCER_DIRECTOR_* do `.env` só foram lidas uma vez, pela
    | migration que criou a tabela, para o destinatário de hoje continuar
    | recebendo no dia do deploy.
    */

    /*
    |--------------------------------------------------------------------------
    | Limite semanal
    |--------------------------------------------------------------------------
    |
    | Passar de 2 serviços em 7 dias exige a liberação do coordenador do setor
    | Comercial. Presencialmente ele digita o próprio PIN; ausente, recebe por
    | e-mail um código de 6 dígitos e o dita para quem está registrando.
    |
    | O prazo de 2 horas dá folga para o coordenador ver o e-mail e responder
    | sem que o atendimento tenha de pedir um código novo. Quem segura o risco
    | de um prazo mais longo é o resto do desenho: o código vale para UM
    | contrato, UMA vez, e o número de tentativas é limitado — seis dígitos não
    | resistem a chutes ilimitados.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cópia dos contratos assinados no servidor de arquivos (FTP)
    |--------------------------------------------------------------------------
    |
    | Contrato, termo aditivo e termo de comissão, depois de assinados pelas
    | duas partes, ganham uma cópia em PDF no mesmo arquivo de rede dos
    | documentos do balcão — pasta `Freelancers`, uma pasta por pessoa (ver
    | FreelancerContractArchiver). O disco e a pasta-raiz são os de
    | config/signature.php → archive.
    |
    | Desligado por padrão, pelo mesmo motivo de lá: a máquina de
    | desenvolvimento tem as credenciais do FTP, e um teste local não deve
    | criar contrato na pasta de produção. Ligue no .env do servidor.
    |
    */

    'archive' => [
        'enabled' => (bool) env('FREELANCER_ARCHIVE_ENABLED', false),
    ],

    'weekly_limit' => [
        'code_ttl_minutes' => (int) env('FREELANCER_WEEKLY_CODE_TTL_MINUTES', 120),
        'code_max_attempts' => (int) env('FREELANCER_WEEKLY_CODE_MAX_ATTEMPTS', 5),
    ],

];
