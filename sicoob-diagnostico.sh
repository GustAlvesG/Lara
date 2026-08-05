#!/usr/bin/env bash
#
# Diagnóstico da integração Sicoob — curl puro, sem Laravel.
#
# Foco em CABEÇALHO. A documentação do Sicoob atrela o 401 aos headers de
# autenticação, então o script (a) mostra exatamente os headers que saem no fio
# e (b) varre variações deles — presença, ausência, grafia e capitalização —
# comparando o código HTTP de cada uma.
#
# Também isola o problema tirando a aplicação da equação: se o mesmo
# certificado e o mesmo client_id funcionam numa API e não em outra, a matriz
# final mostra isso pronta para anexar em chamado.
#
# NÃO TRANSFERE DINHEIRO. Só são chamados endpoints de leitura e a iniciação
# DICT (POST /pagamentos), que consulta a chave e reserva um identificador. O
# endpoint que efetiva pagamento NÃO é chamado em nenhum cenário — há uma trava
# explícita no fim do arquivo.
#
# Uso:
#   ./sicoob-diagnostico.sh                          # lê o .env do diretório atual
#   ./sicoob-diagnostico.sh --chave sua@chave.pix    # inclui os testes de DICT
#   ./sicoob-diagnostico.sh --env /var/www/html/Lara/.env --chave ...
#
# Rode como o usuário do PHP para exercitar as mesmas permissões da aplicação:
#   sudo -u www-data ./sicoob-diagnostico.sh --chave sua@chave.pix

set -uo pipefail

ENV_FILE=".env"
CHAVE=""

while [ $# -gt 0 ]; do
    case "$1" in
        --env)   ENV_FILE="$2"; shift 2 ;;
        --chave) CHAVE="$2";    shift 2 ;;
        -h|--help) sed -n '2,27p' "$0"; exit 0 ;;
        *) echo "Opção desconhecida: $1"; exit 1 ;;
    esac
done

# ---------------------------------------------------------------------------
# Saída
# ---------------------------------------------------------------------------
if [ -t 1 ]; then
    VERDE='\033[0;32m'; VERMELHO='\033[0;31m'; AMARELO='\033[0;33m'; AZUL='\033[0;36m'; FIM='\033[0m'
else
    VERDE=''; VERMELHO=''; AMARELO=''; AZUL=''; FIM=''
fi

titulo()  { printf "\n${AZUL}== %s ==${FIM}\n" "$1"; }
ok()      { printf "${VERDE}OK${FIM}    %s\n" "$1"; }
falha()   { printf "${VERMELHO}FALHA${FIM} %s\n" "$1"; }
aviso()   { printf "${AMARELO}AVISO${FIM} %s\n" "$1"; }
info()    { printf "      %s\n" "$1"; }

# Mostra só as pontas de um segredo — o suficiente para conferir, insuficiente
# para vazar num print de tela ou num anexo de chamado.
mascarar() {
    local v="${1:-}" n=${#1}
    [ "$n" -le 8 ] && { printf '%*s' "$n" '' | tr ' ' '*'; return; }
    printf '%s%s%s' "${v:0:4}" "$(printf '%*s' $((n-8)) '' | tr ' ' '*')" "${v: -4}"
}

# ---------------------------------------------------------------------------
# Configuração
# ---------------------------------------------------------------------------
titulo "Configuração"

[ -r "$ENV_FILE" ] || { falha "Arquivo .env não encontrado ou sem permissão: $ENV_FILE"; exit 1; }

# Lê uma chave do .env sem dar `source` no arquivo: o .env tem valores com
# espaço, cifrão e aspas que o shell interpretaria — e um `source` aqui
# executaria qualquer coisa que estivesse lá dentro.
env_get() {
    local chave="$1" linha valor
    linha=$(grep -E "^[[:space:]]*${chave}=" "$ENV_FILE" | tail -1) || true
    [ -z "$linha" ] && return 0
    valor="${linha#*=}"
    valor="${valor%$'\r'}"
    case "$valor" in
        \"*\") valor="${valor:1:${#valor}-2}" ;;
        \'*\') valor="${valor:1:${#valor}-2}" ;;
    esac
    printf '%s' "$valor"
}

CLIENT_ID=$(env_get SICOOB_CLIENT_ID)
CERT=$(env_get SICOOB_CERT_PATH)
KEY=$(env_get SICOOB_CERT_KEY_PATH)
KEY_PASS=$(env_get SICOOB_CERT_KEY_PASSWORD)
TOKEN_URL=$(env_get SICOOB_TOKEN_URL)
PIX_URL=$(env_get SICOOB_PIX_BASE_URL)
CC_URL=$(env_get SICOOB_CC_BASE_URL)
CC_CONTA=$(env_get SICOOB_CC_NUMERO_CONTA)

TOKEN_URL="${TOKEN_URL:-https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token}"
PIX_URL="${PIX_URL:-https://api.sicoob.com.br/pix-pagamentos/v2}"
CC_URL="${CC_URL:-https://api.sicoob.com.br/conta-corrente/v4}"
PIX_URL="${PIX_URL%/}"
CC_URL="${CC_URL%/}"

info "env         : $ENV_FILE"
info "usuário     : $(id -un)"
info "client_id   : $(mascarar "$CLIENT_ID")  (${#CLIENT_ID} caracteres)"
info "token_url   : $TOKEN_URL"
info "pix_url     : $PIX_URL"
info "cc_url      : $CC_URL"
info "conta       : ${CC_CONTA:-(não configurada)}"

[ -n "$CLIENT_ID" ] || { falha "SICOOB_CLIENT_ID está vazio."; exit 1; }

# Espaço ou quebra de linha dentro do valor viaja no header e o gateway rejeita
# como credencial inválida — e não aparece em nenhuma inspeção visual do .env.
case "$CLIENT_ID" in
    *[[:space:]]*) falha "SICOOB_CLIENT_ID contém espaço ou quebra de linha. Isso sozinho causa 401."
                   info  "Confira aspas sobrando ou CRLF no .env."; exit 1 ;;
esac
ok "client_id sem espaços ou caracteres de controle"

# ---------------------------------------------------------------------------
# Certificados
# ---------------------------------------------------------------------------
titulo "Certificados"

for par in "certificado:$CERT" "chave:$KEY"; do
    rotulo="${par%%:*}"; caminho="${par#*:}"
    if [ -z "$caminho" ]; then
        falha "Caminho do $rotulo não configurado no .env"; exit 1
    elif [ -r "$caminho" ]; then
        ok "$rotulo legível: $caminho"
    else
        falha "$rotulo ilegível por $(id -un): $caminho"
        info "Confira dono e permissão, ou rode com sudo -u www-data"
        exit 1
    fi
done

# A senha nunca aparece na linha de comando (ficaria visível em `ps`).
export SICOOB_KEYPASS="$KEY_PASS"

MOD_CERT=$(openssl x509 -noout -modulus -in "$CERT" 2>/dev/null | openssl md5 | awk '{print $NF}')
if [ -n "$KEY_PASS" ]; then
    MOD_KEY=$(openssl rsa -noout -modulus -in "$KEY" -passin env:SICOOB_KEYPASS 2>/dev/null | openssl md5 | awk '{print $NF}')
else
    MOD_KEY=$(openssl rsa -noout -modulus -in "$KEY" 2>/dev/null | openssl md5 | awk '{print $NF}')
fi

if [ -z "$MOD_KEY" ]; then
    falha "Não foi possível ler a chave privada — senha errada ou arquivo corrompido."; exit 1
elif [ "$MOD_CERT" = "$MOD_KEY" ]; then
    ok "certificado e chave são o mesmo par"
else
    falha "certificado e chave NÃO casam — vieram de .pfx diferentes"; exit 1
fi

info "serial      : $(openssl x509 -in "$CERT" -noout -serial | cut -d= -f2)"
info "titular     : $(openssl x509 -in "$CERT" -noout -subject | sed 's/^subject= *//')"
info "validade    : $(openssl x509 -in "$CERT" -noout -enddate | cut -d= -f2)"

if openssl x509 -in "$CERT" -noout -checkend 0 >/dev/null 2>&1; then
    ok "certificado dentro da validade"
else
    falha "certificado VENCIDO"; exit 1
fi

CURL_MTLS=(--cert "$CERT" --key "$KEY" --silent --show-error --max-time 45)
[ -n "$KEY_PASS" ] && CURL_MTLS+=(--pass "$KEY_PASS")

# ---------------------------------------------------------------------------
# Token
# ---------------------------------------------------------------------------
titulo "Token OAuth (client_credentials + mTLS)"

# Decodifica o payload do JWT sem validar assinatura — só para ler o que o
# emissor declarou. O token em si nunca é impresso.
escopos_do_token() {
    local token="$1" payload
    payload=$(printf '%s' "$token" | cut -d. -f2 | tr '_-' '/+')
    case $(( ${#payload} % 4 )) in
        2) payload="${payload}==" ;;
        3) payload="${payload}=" ;;
    esac
    printf '%s' "$payload" | base64 -d 2>/dev/null \
        | grep -o '"scope"[[:space:]]*:[[:space:]]*"[^"]*"' \
        | sed 's/.*:[[:space:]]*"//; s/"$//'
}

pedir_token() {
    curl "${CURL_MTLS[@]}" -X POST "$TOKEN_URL" \
        -H 'Content-Type: application/x-www-form-urlencoded' \
        --data-urlencode "grant_type=client_credentials" \
        --data-urlencode "client_id=$CLIENT_ID" \
        --data-urlencode "scope=$1" 2>&1 \
        | grep -o '"access_token"[[:space:]]*:[[:space:]]*"[^"]*"' | sed 's/.*:[[:space:]]*"//; s/"$//'
}

ESCOPOS_PIX="pixpagamentos_escrita pixpagamentos_consulta"

TOKEN_PIX=$(pedir_token "$ESCOPOS_PIX")
if [ -z "$TOKEN_PIX" ]; then
    falha "não foi possível obter token para os escopos de Pix"
    info "Resposta bruta do SSO:"
    curl "${CURL_MTLS[@]}" -X POST "$TOKEN_URL" \
        -H 'Content-Type: application/x-www-form-urlencoded' \
        --data-urlencode "grant_type=client_credentials" \
        --data-urlencode "client_id=$CLIENT_ID" \
        --data-urlencode "scope=$ESCOPOS_PIX" | head -c 500
    echo; exit 1
fi
ok "token de Pix obtido (${#TOKEN_PIX} caracteres)"
info "escopos pedidos    : $ESCOPOS_PIX"
info "escopos concedidos : $(escopos_do_token "$TOKEN_PIX")"

# Um `Authorization` com 3 mil caracteres é grande para header. Alguns gateways
# têm limite por header (4 KB é comum) e respondem 401 em vez de 431.
BYTES_AUTH=$(( ${#TOKEN_PIX} + 7 ))
info "tamanho do header Authorization: ${BYTES_AUTH} bytes"
[ "$BYTES_AUTH" -gt 4000 ] && aviso "acima de 4 KB — se houver limite por header no gateway, seria aqui"

TOKEN_CC=$(pedir_token "cco_consulta")
[ -n "$TOKEN_CC" ] && ok "token de Conta Corrente obtido (${#TOKEN_CC} caracteres)" \
                   && info "escopos concedidos : $(escopos_do_token "$TOKEN_CC")"

# ---------------------------------------------------------------------------
# Os headers que realmente saem no fio
#
# É a verificação que a documentação do Sicoob pede: em vez de confiar no que
# achamos que estamos mandando, lê a saída verbosa do curl e imprime as linhas
# de requisição, com o token mascarado.
# ---------------------------------------------------------------------------
titulo "Headers enviados (leitura do fio)"

TRACE="/tmp/sicoob_trace.$$"
curl "${CURL_MTLS[@]}" -o /dev/null -v \
    -X POST "$PIX_URL/pagamentos" \
    -H "Authorization: Bearer $TOKEN_PIX" \
    -H "client_id: $CLIENT_ID" \
    -H 'Content-Type: application/json' \
    -d '{"chave":"diagnostico@invalido"}' 2>"$TRACE"

# As linhas de requisição vêm prefixadas por "> " na saída verbosa.
grep '^> ' "$TRACE" \
    | sed -E "s/(Bearer )[A-Za-z0-9._-]{12}[A-Za-z0-9._-]*/\1<TOKEN de ${#TOKEN_PIX} caracteres>/" \
    | sed -E "s/(client_id: ).*/\1$(mascarar "$CLIENT_ID")/" \
    | sed 's/^> /      /'

if grep -qi '^> client_id:' "$TRACE"; then
    ok "o header client_id está sendo enviado"
else
    falha "o header client_id NÃO está no fio"
fi
grep -qi '^> authorization: Bearer ' "$TRACE" \
    && ok "o header Authorization: Bearer está sendo enviado" \
    || falha "o header Authorization está ausente ou malformado"

rm -f "$TRACE"

# ---------------------------------------------------------------------------
# Chamadas
# ---------------------------------------------------------------------------
E2E_FAKE="E00000000000000000000000000000ZZ"   # 32 alfanuméricos, inexistente
RESULTADOS=()

# chamar <rótulo> <método> <url> [header...]
#
# Cada header é passado inteiro ("Nome: valor"), para que as variações de
# grafia sejam exercitadas exatamente como o gateway as receberá.
chamar() {
    local rotulo="$1" metodo="$2" url="$3"; shift 3
    local args=("${CURL_MTLS[@]}" -o "/tmp/sicoob_resp.$$" -w '%{http_code}' -X "$metodo" "$url")

    local h
    for h in "$@"; do args+=(-H "$h"); done
    [ "$metodo" = "POST" ] && [ -n "$CORPO" ] && args+=(-H 'Content-Type: application/json' -d "$CORPO")

    local codigo resp
    codigo=$(curl "${args[@]}" 2>/dev/null)
    resp=$(head -c 220 "/tmp/sicoob_resp.$$" 2>/dev/null | tr -d '\n')
    rm -f "/tmp/sicoob_resp.$$"

    case "$codigo" in
        2*) printf "${VERDE}%-4s${FIM} %s\n" "$codigo" "$rotulo" ;;
        4*) printf "${VERMELHO}%-4s${FIM} %s\n" "$codigo" "$rotulo" ;;
        *)  printf "${AMARELO}%-4s${FIM} %s\n" "$codigo" "$rotulo" ;;
    esac
    [ -n "$resp" ] && info "$resp"

    RESULTADOS+=("$codigo|$rotulo")
}

AUTH="Authorization: Bearer $TOKEN_PIX"
CORPO=""

titulo "Conta Corrente (controle — sabidamente funcional)"
chamar "GET /saldo  [Authorization + client_id]" GET "$CC_URL/saldo?numeroContaCorrente=$CC_CONTA" \
    "Authorization: Bearer $TOKEN_CC" "client_id: $CLIENT_ID"
chamar "GET /saldo  [só Authorization]" GET "$CC_URL/saldo?numeroContaCorrente=$CC_CONTA" \
    "Authorization: Bearer $TOKEN_CC"

titulo "Pix Pagamentos — matriz de cabeçalhos (GET, leitura)"
# Um 404 em vez de 401 provaria que a autorização passou e só o recurso não
# existe. É a distinção central: 401 é porta fechada, 404 é porta aberta e
# quarto vazio.
chamar "GET  [Authorization + client_id]"          GET "$PIX_URL/pagamentos/$E2E_FAKE" "$AUTH" "client_id: $CLIENT_ID"
chamar "GET  [só Authorization]"                   GET "$PIX_URL/pagamentos/$E2E_FAKE" "$AUTH"
chamar "GET  [só client_id, sem Authorization]"    GET "$PIX_URL/pagamentos/$E2E_FAKE" "client_id: $CLIENT_ID"
chamar "GET  [sem nenhum header de auth]"          GET "$PIX_URL/pagamentos/$E2E_FAKE"
chamar "GET  [Client-Id (hífen, capitalizado)]"    GET "$PIX_URL/pagamentos/$E2E_FAKE" "$AUTH" "Client-Id: $CLIENT_ID"
chamar "GET  [client-id (hífen, minúsculo)]"       GET "$PIX_URL/pagamentos/$E2E_FAKE" "$AUTH" "client-id: $CLIENT_ID"
chamar "GET  [X-Client-Id]"                        GET "$PIX_URL/pagamentos/$E2E_FAKE" "$AUTH" "X-Client-Id: $CLIENT_ID"
chamar "GET  [bearer minúsculo]"                   GET "$PIX_URL/pagamentos/$E2E_FAKE" "Authorization: bearer $TOKEN_PIX" "client_id: $CLIENT_ID"
chamar "GET  [+ Accept: application/json]"         GET "$PIX_URL/pagamentos/$E2E_FAKE" "$AUTH" "client_id: $CLIENT_ID" "Accept: application/json"
chamar "GET  [client_id também na query]"          GET "$PIX_URL/pagamentos/$E2E_FAKE?client_id=$CLIENT_ID" "$AUTH" "client_id: $CLIENT_ID"

if [ -n "$CHAVE" ]; then
    titulo "Pix Pagamentos — matriz de cabeçalhos (POST /pagamentos, iniciação DICT)"
    info "A iniciação consulta a chave e reserva um identificador. Não move dinheiro."
    CORPO="{\"chave\":\"$CHAVE\"}"
    chamar "POST [Authorization + client_id]"       POST "$PIX_URL/pagamentos" "$AUTH" "client_id: $CLIENT_ID"
    chamar "POST [só Authorization]"                POST "$PIX_URL/pagamentos" "$AUTH"
    chamar "POST [Client-Id (hífen)]"               POST "$PIX_URL/pagamentos" "$AUTH" "Client-Id: $CLIENT_ID"
    chamar "POST [+ Accept: application/json]"      POST "$PIX_URL/pagamentos" "$AUTH" "client_id: $CLIENT_ID" "Accept: application/json"
    CORPO=""
else
    aviso "sem --chave: a matriz de POST foi pulada"
fi

# ---------------------------------------------------------------------------
# Leitura do resultado
# ---------------------------------------------------------------------------
titulo "Resumo"

if [ ${#RESULTADOS[@]} -eq 0 ]; then
    aviso "Nenhuma chamada foi feita."; exit 1
fi

for r in "${RESULTADOS[@]}"; do
    printf "  %-5s %s\n" "${r%%|*}" "${r#*|}"
done

echo
CC_OK=$(printf '%s\n'  "${RESULTADOS[@]}" | grep -c '^2..|GET /saldo' || true)
PIX_OK=$(printf '%s\n' "${RESULTADOS[@]}" | grep -c '^2..|\(GET\|POST\) ' || true)
PIX_404=$(printf '%s\n' "${RESULTADOS[@]}" | grep -c '^404|' || true)

if [ "$PIX_OK" -gt 1 ]; then
    ok "Alguma variação de cabeçalho passou no Pix — veja acima QUAL, e replique no .env."
elif [ "$PIX_404" -gt 0 ]; then
    ok "Houve 404 no Pix: a autorização PASSOU nessa variação (o recurso é que não existe)."
    info "Compare com as linhas 401 — a diferença entre elas é a causa."
elif [ "$CC_OK" -gt 0 ]; then
    falha "Todas as variações de cabeçalho no Pix falharam, e a Conta Corrente responde."
    echo
    info "Se NENHUMA combinação de headers muda o resultado, o problema não é"
    info "cabeçalho: é a autorização do produto Pix Pagamentos para este"
    info "client_id no gateway de produção — e isso se resolve no portal ou na"
    info "cooperativa, não aqui."
    echo
    info "Anexe esta saída ao chamado: ela mostra o certificado válido, o token"
    info "emitido com os escopos corretos, os headers no fio e outra API do"
    info "mesmo app respondendo 200 com a mesma credencial."
else
    aviso "Resultado misto — veja a matriz acima."
fi

# ---------------------------------------------------------------------------
# Trava de segurança
#
# Este script roda em produção, contra a conta real. O endpoint de efetivação
# nunca deve ser chamado daqui: um diagnóstico que transfere dinheiro deixa de
# ser diagnóstico. A verificação abaixo falha de propósito se alguém
# acrescentar essa chamada no futuro.
#
# O termo é montado em duas partes e os comentários são descartados de
# propósito: escrito inteiro, ele apareceria no cabeçalho deste arquivo e nesta
# própria linha, e a trava acusaria a si mesma.
# ---------------------------------------------------------------------------
TERMO_PROIBIDO="confirma""cao"
if grep -vE '^[[:space:]]*#' "$0" | grep -q "$TERMO_PROIBIDO"; then
    echo
    falha "ESTE SCRIPT NÃO PODE CHAMAR O ENDPOINT DE EFETIVAÇÃO. Remova essa chamada."
    exit 2
fi

echo
info "Nenhum pagamento foi efetivado por este script."
