<?php

namespace App\Services\Signature\Pki;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Baixa e guarda o que a conferência busca na rede: as Listas de
 * Certificados Revogados (LCR) e os certificados das ACs intermediárias que a
 * assinatura não trouxe.
 *
 * As duas coisas são públicas e assinadas — a LCR pela AC, o certificado pela
 * AC de cima —, então quem confere a assinatura de cada uma é quem as usa
 * (RevocationChecker e a montagem da cadeia). Este repositório só cuida de
 * baixar e de não baixar de novo à toa:
 *
 *  - LCR guardada vale até o `nextUpdate` dela. A do gov.br tem ~3 MB e muda a
 *    cada 2 h; o comando `signature:crl` (agendado) a mantém fresca para a
 *    conferência não esperar o download.
 *  - Certificado de AC guardado vale `pki.issuer_cache_days` dias.
 *
 * Fica no disco das assinaturas (privado), em `signature/pki`.
 */
class PkiRepository
{
    /**
     * A LCR do endereço — guardada, se ainda vale; baixada, se não.
     *
     * Falhou o download e há uma guardada, mesmo vencida, ela volta: quem
     * decide se serve é o RevocationChecker (que a recusa por desatualizada).
     */
    public function crl(string $url, bool $forcar = false): ?CertificateRevocationList
    {
        $caminho = $this->path('lcr', $url, 'crl');
        $guardada = $this->readCrl($caminho);

        if (!$forcar && $guardada !== null && $guardada->isCurrent(time())) {
            return $guardada;
        }

        try {
            $bytes = $this->download($url);
            $nova = CertificateRevocationList::parse($bytes);
            $this->disk()->put($caminho, $bytes);

            return $nova;
        } catch (Throwable $e) {
            Log::warning('Assinaturas: não foi possível baixar a lista de revogação.', [
                'url' => $url,
                'erro' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return $guardada;
        }
    }

    /**
     * Os certificados publicados no endereço do emissor (Authority Information
     * Access) — um certificado ou um pacote PKCS#7, como o do gov.br.
     *
     * @return array<int, string> PEM
     */
    public function issuers(string $url): array
    {
        $caminho = $this->path('ac', $url, 'der');
        $disk = $this->disk();
        $validade = max(1, (int) config('signature.pki.issuer_cache_days', 30)) * 86400;

        if ($disk->exists($caminho) && time() - $disk->lastModified($caminho) < $validade) {
            return Certificates::fromAnyFormat((string) $disk->get($caminho));
        }

        try {
            $bytes = $this->download($url);
            $certs = Certificates::fromAnyFormat($bytes);

            if ($certs !== []) {
                $disk->put($caminho, $bytes);
            }

            return $certs;
        } catch (Throwable $e) {
            Log::warning('Assinaturas: não foi possível baixar o certificado do emissor.', [
                'url' => $url,
                'erro' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return $disk->exists($caminho) ? Certificates::fromAnyFormat((string) $disk->get($caminho)) : [];
        }
    }

    /**
     * Os endereços das LCR já guardadas — o que o `signature:crl` renova.
     *
     * @return array<int, string>
     */
    public function knownCrlUrls(): array
    {
        $disk = $this->disk();
        $indice = $this->root() . '/lcr/indice.json';

        return $disk->exists($indice) ? array_keys((array) json_decode((string) $disk->get($indice), true)) : [];
    }

    private function readCrl(string $caminho): ?CertificateRevocationList
    {
        $disk = $this->disk();

        if (!$disk->exists($caminho)) {
            return null;
        }

        try {
            return CertificateRevocationList::parse((string) $disk->get($caminho));
        } catch (RuntimeException) {
            return null;
        }
    }

    /** @throws RuntimeException */
    private function download(string $url): string
    {
        if (!config('signature.pki.network', true)) {
            throw new RuntimeException('Consulta à rede desligada (SIGNATURE_PKI_NETWORK=false).');
        }

        $resposta = Http::timeout((int) config('signature.pki.timeout_seconds', 30))
            ->withHeaders(['Accept' => '*/*'])
            ->get($url);

        if (!$resposta->successful() || $resposta->body() === '') {
            throw new RuntimeException('HTTP ' . $resposta->status());
        }

        return $resposta->body();
    }

    /** Nome do arquivo pelo hash do endereço; a LCR também entra no índice, para o comando renovar. */
    private function path(string $tipo, string $url, string $extensao): string
    {
        $caminho = $this->root() . '/' . $tipo . '/' . sha1($url) . '.' . $extensao;

        if ($tipo === 'lcr') {
            $disk = $this->disk();
            $indice = $this->root() . '/lcr/indice.json';
            $urls = $disk->exists($indice) ? (array) json_decode((string) $disk->get($indice), true) : [];

            if (!isset($urls[$url])) {
                $urls[$url] = basename($caminho);
                $disk->put($indice, json_encode($urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        return $caminho;
    }

    private function root(): string
    {
        return (string) config('signature.pki.cache_path', 'signature/pki');
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('signature.disk'));
    }
}
