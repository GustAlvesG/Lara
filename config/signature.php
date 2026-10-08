<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Onde os arquivos ficam
    |--------------------------------------------------------------------------
    |
    | Disco PRIVADO, sempre. PDF, PNG da assinatura e foto do signatário são
    | prova documental ligada ao CPF de uma pessoa: nada disso vira URL.
    | Tudo sai por rota autenticada, com Policy.
    |
    | Não use o disco `public` nem `placar`: os dois apontam para dentro de
    | `public/` e serviriam os arquivos como estático, sem passar por
    | autorização nenhuma.
    |
    */

    'disk' => env('SIGNATURE_DISK', 'local'),

    'paths' => [
        'documents' => 'signature/documents',
        'signatures' => 'signature/signatures',
        'photos' => 'signature/photos',
        // Imagens do papel timbrado (cabeçalho e rodapé da empresa).
        'layouts' => 'signature/layouts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Liberação por QR Code
    |--------------------------------------------------------------------------
    |
    | Dois prazos diferentes, e é importante que sejam diferentes:
    |
    | `qr_ttl_seconds` é o tempo entre o atendente mostrar o QR e o tablet
    | lê-lo — coisa de segundos na prática. Cinco minutos já é folga; um QR que
    | fica valendo é um QR que alguém fotografa por cima do ombro.
    |
    | `session_ttl_minutes` começa na LEITURA e cobre o atendimento inteiro:
    | ler o documento, conferir o CPF, assinar. Quinze minutos é o suficiente
    | para um termo de uma página lido com calma.
    |
    | `session_warning_seconds` é quando o tablet avisa que vai encerrar. Sem o
    | aviso, a pessoa perde o que estava fazendo sem entender por quê.
    |
    */

    'qr_ttl_seconds' => (int) env('SIGNATURE_QR_TTL_SECONDS', 300),
    'session_ttl_minutes' => (int) env('SIGNATURE_SESSION_TTL_MINUTES', 15),
    'session_warning_seconds' => (int) env('SIGNATURE_SESSION_WARNING_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Modo sem HTTPS (caminho degradado)
    |--------------------------------------------------------------------------
    |
    | `getUserMedia` só funciona em origem segura. Em HTTP comum, o tablet não
    | lê QR e não tira foto — a tela abre e não faz nada.
    |
    | `manual_code` liga um código CURTO, ditado pelo atendente e digitado no
    | tablet, ao lado do QR. É a mesma liberação, com as mesmas travas: uso
    | único, mesmo prazo, mesmo vínculo com um documento. O que muda é só como
    | o segredo chega ao aparelho — e aí entra a diferença que importa: um
    | código ditado passa por uma pessoa, e uma pessoa pode repeti-lo a quem
    | não devia. Por isso ele é mais curto de vida e nasce DESLIGADO.
    |
    | `photo.skip_without_camera` deixa o tablet concluir a assinatura sem a
    | foto quando a câmera não existe. Não é o mesmo que "o modelo não pedia
    | foto": a evidência fica registrada como AUSENTE, com o motivo, e o
    | manifesto imprime isso. Também nasce desligado, porque ligar é abrir mão
    | de uma evidência que o modelo declarou necessária.
    |
    | Os dois juntos são o modo de operação possível enquanto não há HTTPS —
    | não um modo equivalente.
    |
    */

    'manual_code' => [
        'enabled' => (bool) env('SIGNATURE_MANUAL_CODE_ENABLED', false),
        // Metade do prazo do QR, por padrão: um código ditado em voz alta no
        // balcão é ouvido por quem estiver na fila.
        'ttl_seconds' => (int) env('SIGNATURE_MANUAL_CODE_TTL_SECONDS', 150),
    ],

    /*
    | Prazo do documento inteiro: liberado e esquecido, é encerrado pelo
    | comando `signature:expire`. Conta a partir do congelamento.
    */

    'document_ttl_hours' => (int) env('SIGNATURE_DOCUMENT_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Restrição de rede (opcional)
    |--------------------------------------------------------------------------
    |
    | Faixas de IP que podem CONSUMIR um token de QR, em CIDR ou IP solto,
    | separadas por vírgula. Vazio = sem restrição.
    |
    | É uma trava a mais, não a principal: o token de uso único já é a
    | proteção. Serve para o caso de alguém fotografar o QR e tentar abri-lo
    | fora da rede do clube.
    |
    | Exemplo: SIGNATURE_ALLOWED_IPS=192.168.10.0/24,10.0.0.5
    |
    */

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SIGNATURE_ALLOWED_IPS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Evidências
    |--------------------------------------------------------------------------
    |
    | `min_stroke_points` recusa a "assinatura" de um toque só. Um traço de
    | verdade, mesmo de quem assina rápido, passa fácil de 30 pontos.
    |
    | Os limites de upload valem para o que o tablet manda, e o tipo é
    | conferido pelos BYTES, não pelo cabeçalho do data URL.
    |
    */

    'evidence' => [
        'min_stroke_points' => (int) env('SIGNATURE_MIN_STROKE_POINTS', 30),

        // O visto (rubrica) é um desenho curto: menos pontos que a assinatura,
        // mas ainda mais que um toque.
        'min_initials_points' => (int) env('SIGNATURE_MIN_INITIALS_POINTS', 8),
        'max_signature_kb' => (int) env('SIGNATURE_MAX_SIGNATURE_KB', 2048),
        'max_photo_kb' => (int) env('SIGNATURE_MAX_PHOTO_KB', 4096),

        /*
         | Concluir sem foto quando a câmera não existe (ambiente sem HTTPS).
         | Ver o bloco "Modo sem HTTPS" acima: a ausência é REGISTRADA com o
         | motivo e impressa no manifesto, e não silenciada.
         */
        'skip_photo_without_camera' => (bool) env('SIGNATURE_SKIP_PHOTO_WITHOUT_CAMERA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção
    |--------------------------------------------------------------------------
    |
    | Prazo de guarda padrão, em meses, para modelos que não definem o seu.
    | A EXCLUSÃO FÍSICA NÃO ESTÁ IMPLEMENTADA: o campo é gravado desde já para
    | que a decisão de retenção exista antes de haver o que apagar.
    |
    */

    'retention_months' => (int) env('SIGNATURE_RETENTION_MONTHS', 60),

    /*
    |--------------------------------------------------------------------------
    | Arquivo no servidor de arquivos (FTP)
    |--------------------------------------------------------------------------
    |
    | Depois de finalizado, o PDF assinado ganha uma CÓPIA no FTP, organizada
    | em pastas por modelo e por pessoa (ver SignatureArchiver). O arquivo de
    | verdade continua no disco privado acima.
    |
    | Desligado por padrão: a máquina de desenvolvimento tem as credenciais do
    | FTP no .env, e um teste local não deve criar "documento assinado" na
    | pasta de produção. Ligue no .env do servidor.
    |
    | `root` é relativo à pasta inicial da conta FTP e é criado se não existir.
    |
    */

    'archive' => [
        'enabled' => (bool) env('SIGNATURE_ARCHIVE_ENABLED', false),
        'disk' => env('SIGNATURE_ARCHIVE_DISK', 'signature_archive'),
        'root' => env('SIGNATURE_ARCHIVE_ROOT', 'Lara/DocumentosAssinados'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Envio da via ao signatário
    |--------------------------------------------------------------------------
    |
    | O WhatsApp nasce DESLIGADO: o gateway da Poli responde 200 para envios
    | que não entrega, então um "enviado" na tela não provaria entrega. Ligue
    | sabendo disso.
    |
    */

    'delivery' => [
        'email' => (bool) env('SIGNATURE_DELIVERY_EMAIL', true),
        'whatsapp' => (bool) env('SIGNATURE_DELIVERY_WHATSAPP', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assinatura pelo gov.br (conferência do PDF devolvido)
    |--------------------------------------------------------------------------
    |
    | O atendente prepara o documento para o gov.br, a pessoa assina o PDF no
    | portal do gov.br (assinador.iti.br) e o devolve, e o atendente o envia na
    | aba "Assinatura gov.br" da tela do documento. O Lara confere — é este
    | documento, nada mudou depois, o certificado é do gov.br e o CPF é de um
    | signatário — e, aprovado, conclui a assinatura. Ver
    | GovbrSignatureValidator e GovbrCheckService.
    |
    | `ttl_days` é o prazo do documento preparado para o gov.br: o vaivém por
    | e-mail leva dias, e o prazo do balcão (`document_ttl_hours`) é de horas.
    |
    | `trust_bundle` é a cadeia oficial do gov.br (Raiz → Intermediária →
    | Final), baixada de https://repo.iti.br/docs/Cadeia_GovBr-der.p7b e
    | guardada no repositório. É a ÚNICA âncora de confiança: o que não sobe
    | até a raiz deste arquivo é recusado. Os certificados vencem em 2033.
    |
    */

    'govbr' => [
        'trust_bundle' => resource_path('certs/govbr/cadeia-govbr.pem'),
        'max_upload_kb' => (int) env('SIGNATURE_GOVBR_MAX_UPLOAD_KB', 20480),
        'ttl_days' => (int) env('SIGNATURE_GOVBR_TTL_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificado ICP-Brasil (assinatura qualificada) na mesma aba
    |--------------------------------------------------------------------------
    |
    | Quem tem e-CPF (A1, A3 ou em nuvem) pode assinar o PDF em qualquer
    | programa que faça atualização incremental e devolvê-lo pela mesma aba. A
    | conferência é a mesma do gov.br; muda a âncora: as raízes da ICP-Brasil,
    | baixadas do ITI e guardadas no repositório (v5, v6, v7, v10 a v13 — a
    | origem e as impressões digitais estão no cabeçalho do arquivo). As ACs
    | intermediárias vêm na assinatura ou são baixadas do endereço que o
    | certificado declara (ver `pki` abaixo).
    |
    */

    'icp_brasil' => [
        'trust_bundle' => resource_path('certs/icp-brasil/raizes-icp-brasil.pem'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consultas de PKI na rede (revogação e ACs intermediárias)
    |--------------------------------------------------------------------------
    |
    | A conferência do PDF assinado consulta a Lista de Certificados Revogados
    | (LCR) de cada certificado e, quando falta uma AC intermediária, baixa o
    | certificado dela. Tudo o que vem da rede é conferido pela assinatura de
    | quem o emitiu; fica guardado no disco das assinaturas, em `cache_path`.
    |
    | `network`              — desligue onde o servidor não sai para a internet:
    |                          a conferência segue só com o que já está guardado.
    | `revocation`           — consultar a LCR.
    | `revocation_required`  — lista indisponível REPROVA (o padrão é mostrar
    |                          "não conferido" e não reprovar).
    |
    | O comando `signature:crl` (agendado de hora em hora) renova as listas já
    | conhecidas, para a conferência não esperar o download da do gov.br (~3 MB).
    |
    */

    'pki' => [
        'network' => (bool) env('SIGNATURE_PKI_NETWORK', true),
        'revocation' => (bool) env('SIGNATURE_PKI_REVOCATION', true),
        'revocation_required' => (bool) env('SIGNATURE_PKI_REVOCATION_REQUIRED', false),
        'timeout_seconds' => (int) env('SIGNATURE_PKI_TIMEOUT_SECONDS', 30),
        'issuer_cache_days' => (int) env('SIGNATURE_PKI_ISSUER_CACHE_DAYS', 30),
        'cache_path' => 'signature/pki',
        // Sempre renovadas pelo `signature:crl`, mesmo antes da primeira conferência.
        'crl_urls' => [
            'http://repo.iti.br/lcr/public/acf/LCRacfGovBr.crl',
            'http://repo.iti.br/lcr/public/aci/LCRaciGovBr.crl',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anexos do documento
    |--------------------------------------------------------------------------
    |
    | Identidade, comprovante — o que o modelo ou o documento pedir. O
    | atendente envia na tela do documento; aceita PDF, JPG e PNG (pelo
    | conteúdo, não pela extensão). Ver SignatureAttachmentService.
    |
    */

    'attachments' => [
        'max_kb' => (int) env('SIGNATURE_ATTACHMENT_MAX_KB', 10240),
    ],

    /*
    | Conferência de identidade por código enviado por e-mail (opção do
    | modelo): minutos de validade do código de 6 números.
    */
    'identity_code_ttl_minutes' => (int) env('SIGNATURE_IDENTITY_CODE_TTL_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Lacre do PDF final com o e-CNPJ do clube (PAdES) + carimbo de tempo
    |--------------------------------------------------------------------------
    |
    | Ligado, todo PDF que sai finalizado — o do tablet, o do gov.br (por
    | atualização incremental, sem desfazer as assinaturas das pessoas) e o
    | relatório do gov.br — recebe uma assinatura digital do CLUBE, com o
    | certificado A1 (e-CNPJ ICP-Brasil, arquivo .pfx/.p12). Ela não assina
    | pela pessoa: LACRA o arquivo — prova que não mudou depois de emitido e
    | quem o emitiu — e qualquer um confere em validar.iti.gov.br.
    |
    | `tsa_url` é a Autoridade de Carimbo do Tempo (ACT) credenciada na
    | ICP-Brasil, contratada à parte (RFC 3161, HTTP). Com ela, o lacre leva a
    | hora de um terceiro, e não a do servidor. Usuário e senha, se a ACT pedir
    | autenticação básica. Vazio = lacre sem carimbo.
    |
    | Falha alto: lacre ligado sem certificado, com senha errada, certificado
    | vencido ou carimbo que não volta faz a finalização falhar e tentar de
    | novo (FinalizeSignatureDocument). Um lacre que silenciosamente não
    | acontece é pior que nenhum, porque alguém passa a contar com ele.
    | Confira a configuração com `php artisan signature:seal-check`.
    |
    */

    'pades' => [
        'enabled' => (bool) env('SIGNATURE_PADES_ENABLED', false),
        'certificate_path' => env('SIGNATURE_PADES_CERTIFICATE'),
        'certificate_password' => env('SIGNATURE_PADES_PASSWORD'),
        'reason' => env('SIGNATURE_PADES_REASON', 'Lacre do documento emitido pelo Lara'),
        'tsa_url' => env('SIGNATURE_TSA_URL'),
        'tsa_user' => env('SIGNATURE_TSA_USER'),
        'tsa_password' => env('SIGNATURE_TSA_PASSWORD'),
        // OID da política da ACT, se o contrato exigir; vazio = a padrão da ACT.
        'tsa_policy' => env('SIGNATURE_TSA_POLICY'),
        'tsa_timeout_seconds' => (int) env('SIGNATURE_TSA_TIMEOUT_SECONDS', 30),
        // Bytes reservados no PDF para a assinatura (CMS + cadeia + carimbo).
        'reserve_bytes' => (int) env('SIGNATURE_PADES_RESERVE_BYTES', 32768),
    ],

    /*
    |--------------------------------------------------------------------------
    | Termo de Menores (autoatendimento do sócio nos eventos)
    |--------------------------------------------------------------------------
    |
    | device_ttl_hours        — por quanto tempo o tablet fica pareado depois
    |                           de ler o QR de pareamento.
    | pairing_ttl_seconds     — validade do QR de pareamento na tela do
    |                           computador.
    | flow_ttl_minutes        — inatividade que encerra o atendimento no tablet
    |                           (título digitado, responsável confirmado). Vale
    |                           também para o "Autorizar outro menor".
    | max_cpf_attempts        — tentativas de CPF do responsável por título,
    |                           neste tablet, a cada 15 minutos.
    | adult_age               — idade a partir da qual a pessoa é responsável;
    |                           abaixo dela, é menor.
    |
    */

    'minor_terms' => [
        'device_ttl_hours' => (int) env('SIGNATURE_MINOR_DEVICE_TTL_HOURS', 12),
        'pairing_ttl_seconds' => (int) env('SIGNATURE_MINOR_PAIRING_TTL_SECONDS', 300),
        'flow_ttl_minutes' => (int) env('SIGNATURE_MINOR_FLOW_TTL_MINUTES', 10),
        'max_cpf_attempts' => (int) env('SIGNATURE_MINOR_MAX_CPF_ATTEMPTS', 5),
        'adult_age' => 18,
    ],

];
