<?php

namespace App\Services\Sicoob;

use App\Exceptions\Sicoob\SicoobAuthenticationException;
use App\Exceptions\Sicoob\SicoobCertificateException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Autenticação nas APIs do Sicoob: OAuth2 client_credentials sobre mTLS.
 *
 * Duas coisas comprovam quem somos, e as duas são obrigatórias: o `client_id`
 * do app e o certificado ICP Brasil apresentado no handshake TLS. Sem o
 * certificado a conexão nem chega a existir — não é um header que se esqueceu,
 * é a porta que não abre.
 *
 * O token vale ~300s. Cacheamos por menos (config `token_cache.ttl`, 240s) para
 * nunca partir para a API com um token que expira no caminho.
 *
 * Escopos são por API — Pix Pagamentos e Conta Corrente pedem conjuntos
 * diferentes —, então o cache é por conjunto de escopos, não global.
 */
class SicoobAuthService
{
    /**
     * Access token válido para os escopos pedidos.
     *
     * @param  array<int, string>  $scopes
     *
     * @throws SicoobAuthenticationException
     * @throws SicoobCertificateException
     */
    public function token(array $scopes): string
    {
        $key = $this->cacheKey($scopes);

        $token = Cache::get($key);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = $this->requestToken($scopes);

        Cache::put($key, $token, config('sicoob.token_cache.ttl', 240));

        return $token;
    }

    /**
     * Descarta o token cacheado. Chamado quando a API responde 401 com um token
     * que ainda estava dentro da validade — acontece quando o app é
     * reconfigurado no portal ou o Sicoob revoga a sessão do lado dele.
     *
     * @param  array<int, string>  $scopes
     */
    public function forgetToken(array $scopes): void
    {
        Cache::forget($this->cacheKey($scopes));
    }

    /**
     * Um cliente HTTP já com o certificado, o timeout e os headers que TODA
     * chamada às APIs do Sicoob precisa: `Authorization` e `client_id`.
     *
     * @param  array<int, string>  $scopes
     *
     * @throws SicoobAuthenticationException
     * @throws SicoobCertificateException
     */
    public function client(array $scopes): PendingRequest
    {
        return $this->baseClient()
            ->withToken($this->token($scopes))
            ->withHeaders(['client_id' => (string) config('sicoob.client_id')]);
    }

    /**
     * Cliente com o certificado montado, sem token — é ele que pede o token.
     *
     * @throws SicoobCertificateException
     */
    protected function baseClient(): PendingRequest
    {
        return Http::withOptions($this->certificateOptions())
            ->connectTimeout((int) config('sicoob.http.connect_timeout', 10))
            ->timeout((int) config('sicoob.http.timeout', 60))
            ->acceptJson();
    }

    /**
     * Opções de mTLS do Guzzle.
     *
     * O par tem de estar em PEM. O `.pfx` original não serve aqui: o cURL só
     * lê PKCS#12 com um tipo de certificado que o Guzzle não expõe, e o modo
     * como ele falha (handshake recusado, sem mensagem) custa horas para
     * diagnosticar. A conversão é feita uma vez, fora do repositório.
     *
     * @return array<string, mixed>
     *
     * @throws SicoobCertificateException
     */
    protected function certificateOptions(): array
    {
        $cert = (string) config('sicoob.certificate.path');
        $key = (string) config('sicoob.certificate.key_path');
        $password = config('sicoob.certificate.key_password');

        foreach (['SICOOB_CERT_PATH' => $cert, 'SICOOB_CERT_KEY_PATH' => $key] as $env => $path) {
            if ($path === '') {
                throw new SicoobCertificateException(
                    "Certificado do Sicoob não configurado: {$env} está vazio.",
                    ['variavel' => $env]
                );
            }

            if (!is_readable($path)) {
                // O caminho aparece no log de propósito: o erro quase sempre é
                // permissão do usuário do PHP, e sem o caminho não dá para ver isso.
                throw new SicoobCertificateException(
                    "Arquivo de certificado do Sicoob não encontrado ou sem permissão de leitura: {$path}",
                    ['variavel' => $env, 'caminho' => $path]
                );
            }
        }

        return [
            'cert' => $cert,
            // A senha vai para o Guzzle, nunca para log nem exceção.
            'ssl_key' => filled($password) ? [$key, $password] : $key,
        ];
    }

    /**
     * @param  array<int, string>  $scopes
     *
     * @throws SicoobAuthenticationException
     * @throws SicoobCertificateException
     */
    protected function requestToken(array $scopes): string
    {
        $scopeString = implode(' ', $scopes);

        try {
            $response = $this->baseClient()->asForm()->post(config('sicoob.token_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('sicoob.client_id'),
                'scope' => $scopeString,
            ]);
        } catch (ConnectionException $e) {
            // Falha de TLS chega como ConnectionException. Distinguir do "servidor
            // fora do ar" importa: uma é problema nosso de certificado, a outra não.
            if ($this->looksLikeCertificateProblem($e->getMessage())) {
                throw new SicoobCertificateException(
                    'Falha no handshake mTLS com o Sicoob — verifique o certificado, a chave e a senha.',
                    ['scopes' => $scopeString],
                    $e
                );
            }

            throw new SicoobAuthenticationException(
                'Não foi possível contatar o servidor de autenticação do Sicoob.',
                ['scopes' => $scopeString],
                $e
            );
        }

        if ($response->failed()) {
            $context = [
                'status' => $response->status(),
                'scopes' => $scopeString,
                // O corpo do erro do Keycloak traz `error` e
                // `error_description`, e nenhum segredo nosso.
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
            ];

            Log::channel('sicoob')->error('Sicoob: falha ao obter access token', $context);

            throw new SicoobAuthenticationException(
                'O Sicoob recusou as credenciais da integração (HTTP ' . $response->status() . ').',
                $context
            );
        }

        $token = $response->json('access_token');

        if (!is_string($token) || $token === '') {
            throw new SicoobAuthenticationException(
                'O Sicoob respondeu sem access_token.',
                ['scopes' => $scopeString, 'status' => $response->status()]
            );
        }

        Log::channel('sicoob')->info('Sicoob: access token renovado', [
            'scopes' => $scopeString,
            'expires_in' => $response->json('expires_in'),
            'ambiente' => config('sicoob.environment'),
        ]);

        return $token;
    }

    /** @param  array<int, string>  $scopes */
    protected function cacheKey(array $scopes): string
    {
        sort($scopes);

        return config('sicoob.token_cache.prefix', 'sicoob_access_token')
            . ':' . config('sicoob.environment')
            . ':' . md5(implode(' ', $scopes));
    }

    /**
     * O cURL não dá um código para "seu certificado é o problema": a pista está
     * no texto. Vale como triagem para a mensagem de erro, não como diagnóstico.
     */
    protected function looksLikeCertificateProblem(string $message): bool
    {
        $message = mb_strtolower($message);

        foreach (['certificate', 'ssl', 'unable to set private key', 'pem', 'handshake'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
