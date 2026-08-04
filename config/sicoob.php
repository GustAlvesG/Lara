<?php

/*
|--------------------------------------------------------------------------
| Sicoob — Pix Pagamentos e Conta Corrente
|--------------------------------------------------------------------------
|
| Integração que ENVIA dinheiro: o botão "Dar baixa" do financeiro de
| freelancers dispara um Pix para a chave do freelancer. Tudo aqui vem do
| .env — nada de credencial, certificado ou senha no repositório.
|
| Os endpoints, escopos e nomes de campo seguem a especificação oficial
| "Pagamentos PIX 2.0.24.6" e "Conta Corrente 2.1.1.43" do portal
| developers.sicoob.com.br. Ver docs/funcionalidades/pix-sicoob.md.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Chave geral
    |--------------------------------------------------------------------------
    |
    | Desligado, TODO o envio automático fica inerte: o "Dar baixa" volta a ser
    | o que era antes desta integração — uma marcação manual, sem tocar no
    | banco. É assim que o código sobe para produção sem começar a mover
    | dinheiro no mesmo deploy.
    |
    */

    'enabled' => (bool) env('SICOOB_PIX_ENABLED', false),

    /*
    | 'sandbox' ou 'producao'. Só documenta a intenção e aparece nos logs e na
    | tela — quem de fato decide para onde a requisição vai são as URLs abaixo.
    | Manter os dois em dia evita ler um log de produção achando que é teste.
    */

    'environment' => env('SICOOB_ENVIRONMENT', 'sandbox'),

    'client_id' => env('SICOOB_CLIENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Autenticação (OAuth2 client_credentials + mTLS)
    |--------------------------------------------------------------------------
    |
    | O token sai do Keycloak do Sicoob (realm `cooperado`) e vale ~300s. O
    | cache guarda por menos tempo que isso, para nunca chegar na API com um
    | token que expirou no caminho.
    |
    */

    'token_url' => env(
        'SICOOB_TOKEN_URL',
        'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token'
    ),

    'token_cache' => [
        'prefix' => 'sicoob_access_token',
        // 240s < 300s de validade real. A folga cobre a latência da chamada
        // seguinte e o relógio das duas pontas não estar perfeitamente igual.
        'ttl' => (int) env('SICOOB_TOKEN_TTL', 240),
    ],

    /*
    | Escopos por API — são conjuntos diferentes, e o token é cacheado por
    | conjunto. Os nomes vêm do `securitySchemes` de cada especificação; errar
    | um deles dá 401 sem mensagem útil.
    */

    'scopes' => [
        'pix' => ['pixpagamentos_escrita', 'pixpagamentos_consulta'],
        'conta_corrente' => ['cco_consulta'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificado ICP Brasil (mTLS)
    |--------------------------------------------------------------------------
    |
    | O Guzzle/cURL não lê `.pfx` de forma confiável: o par tem de estar em PEM.
    | A conversão é feita UMA vez, fora do repositório (ver a documentação), e
    | os arquivos ficam em storage/certificates/, que está no .gitignore.
    |
    | `key_password` é a senha da CHAVE PRIVADA em PEM. Se você gerou a chave
    | sem senha (`-nodes`), deixe vazio.
    |
    */

    'certificate' => [
        'path' => env('SICOOB_CERT_PATH'),
        'key_path' => env('SICOOB_CERT_KEY_PATH'),
        'key_password' => env('SICOOB_CERT_KEY_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pix Pagamentos
    |--------------------------------------------------------------------------
    */

    'pix' => [

        'base_url' => env(
            'SICOOB_PIX_BASE_URL',
            'https://sandbox.sicoob.com.br/sicoob/sandbox/pix-pagamentos/v2'
        ),

        /*
        | Dados da conta pagadora. Em produção o campo `origem` é OPCIONAL: o
        | Sicoob usa a conta vinculada ao certificado. Preencher só se a sua
        | cooperativa exigir, ou para deixar explícito de onde o dinheiro sai.
        |
        | `tipo`: CORRENTE | POUPANCA | SALARIO | CONTAPAGAMENTO.
        */
        'origem' => array_filter([
            'ispb' => env('SICOOB_ORIGEM_ISPB'),
            'cpfCnpj' => env('SICOOB_ORIGEM_CPF_CNPJ'),
            'nome' => env('SICOOB_ORIGEM_NOME'),
            'agencia' => env('SICOOB_ORIGEM_AGENCIA'),
            'conta' => env('SICOOB_ORIGEM_CONTA'),
            'tipo' => env('SICOOB_ORIGEM_TIPO'),
        ], fn($value) => filled($value)),

        /*
        | Chave Pix de origem (a nossa). Não entra no payload de envio — a API
        | não pede —, mas é registrada em cada tentativa para a auditoria saber
        | de qual chave a conta estava operando na época.
        */
        'chave_origem' => env('SICOOB_PIX_CHAVE_ORIGEM'),

        /*
        | Teto por transferência, em reais. É a última barreira antes de o
        | dinheiro sair: um preço de contrato digitado errado (R$ 25.000 no
        | lugar de R$ 250,00) para aqui, e não no extrato.
        */
        'max_amount' => (float) env('SICOOB_PIX_MAX_AMOUNT', 5000),

        /*
        | Enviar o header `client_id` nas chamadas de Pix Pagamentos.
        |
        | A especificação da Conta Corrente declara esse header como obrigatório;
        | a de Pix Pagamentos não o menciona — ela só declara OAuth sobre mTLS.
        | Quando o gateway responde 401 "Invalid client id or secret" numa conta
        | em que a Conta Corrente funciona com a MESMA credencial, este é o
        | primeiro parafuso a mexer.
        |
        | Padrão `true` porque é o comportamento uniforme das APIs do Sicoob;
        | desligue apenas se o teste mostrar que é isso.
        */
        'enviar_client_id' => (bool) env('SICOOB_PIX_ENVIAR_CLIENT_ID', true),

        /*
        | Exige que o CPF/CNPJ dono da chave no DICT seja o mesmo CPF do
        | freelancer cadastrado. É o que impede pagar a pessoa errada quando a
        | chave PIX do cadastro está errada ou foi trocada de dono. Só desligue
        | com um motivo muito bom (ex.: freelancer que recebe em conta de PJ).
        */
        'validar_titular' => (bool) env('SICOOB_PIX_VALIDAR_TITULAR', true),

        /*
        | Quanto tempo um pagamento pode ficar `sent`/`unknown` antes de a
        | reconciliação parar de insistir e pedir olho humano.
        */
        'reconcile_timeout_minutes' => (int) env('SICOOB_PIX_RECONCILE_TIMEOUT', 1440),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conta Corrente (consulta de saldo)
    |--------------------------------------------------------------------------
    |
    | Usada só para a pré-checagem de saldo antes de confirmar o Pix. Falha na
    | consulta NÃO impede o pagamento (ver SicoobContaCorrenteService): o saldo
    | é uma cortesia, não a autoridade — quem recusa por saldo é o banco.
    |
    */

    'conta_corrente' => [
        'enabled' => (bool) env('SICOOB_SALDO_CHECK_ENABLED', true),
        'base_url' => env('SICOOB_CC_BASE_URL', 'https://api.sicoob.com.br/conta-corrente/v4'),
        'numero_conta' => env('SICOOB_CC_NUMERO_CONTA'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | O timeout é generoso de propósito na confirmação: desistir cedo de uma
    | requisição que MOVE DINHEIRO é o pior dos mundos — a transferência pode
    | ter sido processada e nós não saberíamos. Ver a regra de retry no Job.
    |
    */

    'http' => [
        'connect_timeout' => (int) env('SICOOB_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('SICOOB_TIMEOUT', 60),
    ],

];
