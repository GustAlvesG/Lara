# Pix Sicoob — instalação e ativação

Passo a passo para colocar o Pix automático de freelancers no ar. Cada etapa é verificável;
não pule a ordem — a última liga a transferência de dinheiro real.

O funcionamento do fluxo está em
[Pix automático (Sicoob)](funcionalidades/pix-sicoob.md).

---

## 1. Converter o certificado para PEM

O Guzzle/cURL não lê `.pfx` de forma confiável: o par precisa estar em PEM. A conversão é
feita **uma vez**, na máquina/servidor, **nunca no repositório**.

```bash
mkdir -p storage/certificates
chmod 700 storage/certificates

# Certificado público
openssl pkcs12 -in seu-certificado.pfx -clcerts -nokeys \
  -out storage/certificates/sicoob-cert.pem

# Chave privada (pede a senha do .pfx e depois uma senha para a chave PEM)
openssl pkcs12 -in seu-certificado.pfx -nocerts \
  -out storage/certificates/sicoob-key.pem

chmod 600 storage/certificates/*.pem
chown www-data:www-data storage/certificates/*.pem   # ajuste para o usuário do PHP
```

> Para gerar a chave **sem senha**, acrescente `-nodes` ao segundo comando e deixe
> `SICOOB_CERT_KEY_PASSWORD` vazio. Com senha é melhor.

**Confira a validade e que o par casa:**

```bash
openssl x509 -in storage/certificates/sicoob-cert.pem -noout -subject -dates

# Os dois hashes abaixo têm de ser IGUAIS
openssl x509 -noout -modulus -in storage/certificates/sicoob-cert.pem | openssl md5
openssl rsa  -noout -modulus -in storage/certificates/sicoob-key.pem  | openssl md5
```

**Nunca** commite esses arquivos. O `.gitignore` já cobre `storage/certificates/`, `*.pfx`,
`*.p12`, `*.pem`, `*.key` e `*.crt` — mas confira com `git status` antes do primeiro commit.

---

## 2. Preencher o `.env` do servidor

```dotenv
# --- comece DESLIGADO ---
SICOOB_PIX_ENABLED=false
SICOOB_ENVIRONMENT=sandbox
SICOOB_CLIENT_ID=<client id do app no portal>

SICOOB_CERT_PATH=/caminho/absoluto/storage/certificates/sicoob-cert.pem
SICOOB_CERT_KEY_PATH=/caminho/absoluto/storage/certificates/sicoob-key.pem
SICOOB_CERT_KEY_PASSWORD=<senha da chave PEM, ou vazio>

SICOOB_PIX_BASE_URL=https://sandbox.sicoob.com.br/sicoob/sandbox/pix-pagamentos/v2
SICOOB_TOKEN_URL=https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token

SICOOB_PIX_MAX_AMOUNT=5000
SICOOB_PIX_VALIDAR_TITULAR=true

SICOOB_SALDO_CHECK_ENABLED=true
SICOOB_CC_BASE_URL=https://api.sicoob.com.br/conta-corrente/v4
SICOOB_CC_NUMERO_CONTA=<número da conta corrente do clube>
```

Depois:

```bash
php artisan config:clear
```

### Preciso preencher `SICOOB_ORIGEM_*`?

Em produção, **não**: o Sicoob usa a conta vinculada ao certificado. Preencha só se a sua
cooperativa exigir ou se a confirmação retornar `violacoes: [origem]`:

```dotenv
SICOOB_ORIGEM_ISPB=
SICOOB_ORIGEM_CPF_CNPJ=
SICOOB_ORIGEM_NOME=
SICOOB_ORIGEM_AGENCIA=
SICOOB_ORIGEM_CONTA=
SICOOB_ORIGEM_TIPO=CORRENTE
```

---

## 3. Rodar a migration

```bash
php artisan migrate --path=database/migrations/2026_08_04_140000_create_pix_payments_table.php
```

Cria `pix_payments` — a trilha de auditoria. Nada mais no banco é alterado.

---

## 4. Garantir worker e scheduler

O envio é assíncrono e a reconciliação é agendada. **Sem os dois, nenhum Pix sai e nenhuma
baixa é registrada.**

```bash
# Worker (supervisor/systemd)
php artisan queue:work --queue=default --tries=1

# Scheduler (crontab do usuário do PHP)
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Confira que `sicoob:pix-reconciliar` está na agenda:

```bash
php artisan schedule:list | grep sicoob
```

---

## 5. Validar a conexão (ainda sem transferir)

Com `SICOOB_PIX_ENABLED=false`, use o Tinker para exercitar só a autenticação:

```bash
php artisan tinker
```

```php
// 1. mTLS + token. Deve devolver uma string; qualquer erro aqui é certificado ou credencial.
$token = app(\App\Services\Sicoob\SicoobAuthService::class)
    ->token(config('sicoob.scopes.pix'));
strlen($token);

// 2. Saldo. Deve devolver um número (ou null, se a conta não estiver configurada).
app(\App\Services\Sicoob\SicoobContaCorrenteService::class)->saldoDisponivel();

// 3. Iniciação DICT — NÃO MOVE DINHEIRO. Confirma que o escopo de pagamentos funciona.
app(\App\Services\Sicoob\SicoobPixPagamentoService::class)->iniciar('<uma chave pix sua>');
```

O passo 3 devolve `endToEndId`, `tipo` e `proprietario`. Se chegou até aqui, mTLS, token,
escopos e o endpoint de pagamentos estão todos funcionando.

> **Lembre-se:** no sandbox o passo 3 devolve valores fixos (`"stringstring..."`) e a
> confirmação sempre falha com 400. Isso é o mock do Sicoob, não um bug da integração.

Acompanhe em `storage/logs/sicoob-*.log`.

---

## 6. Ligar em produção

Só depois de todos os passos acima terem passado.

```dotenv
SICOOB_ENVIRONMENT=producao
SICOOB_PIX_BASE_URL=https://api.sicoob.com.br/pix-pagamentos/v2
SICOOB_PIX_ENABLED=true
```

```bash
php artisan config:clear && php artisan queue:restart
```

### O primeiro pagamento real

Faça-o de propósito, não por acaso:

1. Escolha **um** contrato, de **valor baixo**, de um freelancer conhecido.
2. Considere baixar `SICOOB_PIX_MAX_AMOUNT` temporariamente (ex.: `100`) para que um clique
   errado não possa custar caro.
3. Clique em "Pagar via Pix" e acompanhe:
   ```bash
   tail -f storage/logs/sicoob-$(date +%Y-%m-%d).log
   ```
4. Confira o `endToEndId` no extrato do Sicoob.
5. Confirme que o contrato recebeu a baixa na tela.
6. Só então volte `SICOOB_PIX_MAX_AMOUNT` ao valor definitivo.

---

## Desligar às pressas

```dotenv
SICOOB_PIX_ENABLED=false
```

```bash
php artisan config:clear
```

O botão volta a ser marcação manual **imediatamente**. Pagamentos já enfileirados que ainda
não rodaram seguem no worker — pare o worker também se a intenção for cortar tudo:

```bash
php artisan queue:restart   # ou pare o supervisor
```

Nada do que já foi enviado ao banco é revertido por isso: Pix não tem estorno automático.

---

## Checklist final

- [ ] `.pem` gerados, com permissão para o usuário do PHP, e o par confere (mesmo `md5`)
- [ ] Certificado dentro da validade
- [ ] `storage/certificates/` **não** aparece em `git status`
- [ ] `.env` do servidor preenchido; `.env.example` sem valores reais
- [ ] `php artisan migrate` executado (`pix_payments` existe)
- [ ] Worker de fila rodando
- [ ] `schedule:list` mostra `sicoob:pix-reconciliar`
- [ ] Tinker: token, saldo e iniciação DICT funcionaram
- [ ] `SICOOB_PIX_MAX_AMOUNT` coerente com o maior contrato legítimo
- [ ] Primeiro pagamento real feito com valor baixo e conferido no extrato
