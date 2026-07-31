<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Confere as configurações de e-mail sem disparar mensagem nenhuma.
 *
 * O teste vai até onde dá para ir sem gerar e-mail: lê o que está configurado,
 * aponta o que falta e abre a conexão com o servidor SMTP — o handshake já
 * valida host, porta, criptografia e credenciais, porque o transporte do
 * Symfony faz EHLO, STARTTLS e AUTH ao iniciar. Nenhum MAIL FROM é enviado,
 * então nada chega a caixa de entrada de ninguém.
 */
class MailConfigurationTester
{
    /** Espera máxima no handshake SMTP: o teste roda dentro de um request. */
    public const CONNECTION_TIMEOUT = 10;

    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** Valor que vem de fábrica no Laravel — quase sempre é esquecimento. */
    private const PLACEHOLDER_SENDER = 'hello@example.com';

    /**
     * Roda a bateria de verificações do mailer informado (ou do padrão).
     *
     * @return array{mailer: string, transport: ?string, ok: bool, settings: array<string, string>, checks: array<int, array{label: string, status: string, detail: string}>, tested_at: string}
     */
    public function run(?string $mailer = null): array
    {
        $name = $mailer ?: (string) config('mail.default');
        $config = (array) config("mail.mailers.{$name}", []);
        $transport = $config['transport'] ?? null;

        $checks = [
            $this->checkMailer($name, $config),
            $this->checkSender(),
        ];

        if ($transport === 'smtp') {
            $checks[] = $this->checkServer($config);
            $checks[] = $this->checkCredentials($config);
            $checks[] = $this->checkConnection($name, $config);
        }

        return [
            'mailer' => $name,
            'transport' => $transport,
            'ok' => ! collect($checks)->contains(fn (array $check) => $check['status'] === self::FAIL),
            'settings' => $this->settings($config),
            'checks' => $checks,
            'tested_at' => now()->format('d/m/Y H:i:s'),
        ];
    }

    /** O mailer padrão existe e realmente entrega mensagens? */
    private function checkMailer(string $name, array $config): array
    {
        if ($config === []) {
            return $this->result('Mailer padrão', self::FAIL,
                "MAIL_MAILER aponta para \"{$name}\", que não existe em config/mail.php.");
        }

        $transport = $config['transport'] ?? null;

        if (in_array($transport, ['log', 'array'], true)) {
            return $this->result('Mailer padrão', self::WARN,
                "Transporte \"{$transport}\": as mensagens não saem da aplicação — ficam no log/memória. "
                . 'Use SMTP para entregar de verdade.');
        }

        return $this->result('Mailer padrão', self::OK, "\"{$name}\" usando o transporte \"{$transport}\".");
    }

    /** Remetente global: sem ele o provedor recusa a mensagem. */
    private function checkSender(): array
    {
        $address = (string) config('mail.from.address');
        $name = (string) config('mail.from.name');

        if ($address === '') {
            return $this->result('Remetente', self::FAIL, 'MAIL_FROM_ADDRESS não está definido.');
        }

        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return $this->result('Remetente', self::FAIL, "\"{$address}\" não é um e-mail válido.");
        }

        if ($address === self::PLACEHOLDER_SENDER) {
            return $this->result('Remetente', self::WARN,
                'Ainda está no valor de exemplo do Laravel (' . self::PLACEHOLDER_SENDER . ').');
        }

        if ($name === '') {
            return $this->result('Remetente', self::WARN,
                "{$address} sem MAIL_FROM_NAME: o destinatário verá apenas o endereço.");
        }

        return $this->result('Remetente', self::OK, "{$name} <{$address}>");
    }

    /** Endereço do servidor SMTP. */
    private function checkServer(array $config): array
    {
        $host = (string) ($config['host'] ?? '');
        $port = $config['port'] ?? null;

        if ($host === '') {
            return $this->result('Servidor SMTP', self::FAIL, 'MAIL_HOST não está definido.');
        }

        if (blank($port) || ! is_numeric($port)) {
            return $this->result('Servidor SMTP', self::FAIL, 'MAIL_PORT não está definido ou não é numérico.');
        }

        return $this->result('Servidor SMTP', self::OK, "{$host}:{$port}");
    }

    /** Usuário e senha do SMTP. */
    private function checkCredentials(array $config): array
    {
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($username === '' && $password === '') {
            return $this->result('Credenciais', self::WARN,
                'Nenhum usuário configurado: só funciona em servidor que aceita envio sem autenticação.');
        }

        if ($username === '') {
            return $this->result('Credenciais', self::WARN, 'Há senha configurada, mas MAIL_USERNAME está vazio.');
        }

        if ($password === '') {
            return $this->result('Credenciais', self::WARN, "Usuário \"{$username}\" sem MAIL_PASSWORD.");
        }

        return $this->result('Credenciais', self::OK, "Usuário \"{$username}\" com senha definida.");
    }

    /**
     * Abre a conexão com o servidor. É aqui que host, porta, criptografia e
     * credenciais deixam de ser texto no .env e passam a ser verdade ou não.
     */
    private function checkConnection(string $mailer, array $config): array
    {
        try {
            $transport = Mail::mailer($mailer)->getSymfonyTransport();
        } catch (Throwable $e) {
            return $this->result('Conexão com o servidor', self::FAIL,
                'Não foi possível montar o transporte: ' . $this->sanitize($e->getMessage(), $config));
        }

        if (! $transport instanceof SmtpTransport) {
            return $this->result('Conexão com o servidor', self::WARN,
                'O transporte configurado não é SMTP; não há conexão para testar.');
        }

        $stream = $transport->getStream();

        if (method_exists($stream, 'setTimeout')) {
            $stream->setTimeout(self::CONNECTION_TIMEOUT);
        }

        $startedAt = microtime(true);

        try {
            $transport->start();
        } catch (Throwable $e) {
            return $this->result('Conexão com o servidor', self::FAIL, $this->sanitize($e->getMessage(), $config));
        } finally {
            // Deixar a conexão aberta prenderia o socket até o fim do request.
            try {
                $transport->stop();
            } catch (Throwable) {
                // Fechar é melhor esforço: o que importa é o resultado do start().
            }
        }

        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);

        $detail = filled($config['username'] ?? null)
            ? "Servidor aceitou a conexão e as credenciais ({$elapsed} ms)."
            : "Servidor aceitou a conexão ({$elapsed} ms).";

        return $this->result('Conexão com o servidor', self::OK, $detail);
    }

    /**
     * O que está valendo hoje, para conferência a olho.
     *
     * @return array<string, string>
     */
    private function settings(array $config): array
    {
        $settings = [
            'Transporte' => (string) ($config['transport'] ?? '—'),
            'Remetente' => trim(config('mail.from.name') . ' <' . config('mail.from.address') . '>'),
        ];

        if (($config['transport'] ?? null) === 'smtp') {
            $settings['Host'] = (string) ($config['host'] ?? '—');
            $settings['Porta'] = (string) ($config['port'] ?? '—');
            $settings['Criptografia'] = (string) ($config['encryption'] ?? '—');
            $settings['Usuário'] = filled($config['username'] ?? null) ? (string) $config['username'] : '—';
            $settings['Senha'] = filled($config['password'] ?? null) ? 'definida' : 'não definida';
        }

        return $settings;
    }

    /** A mensagem do servidor vai para a tela: a senha não pode ir junto. */
    private function sanitize(string $message, array $config): string
    {
        $password = (string) ($config['password'] ?? '');

        if ($password !== '') {
            $message = str_replace($password, '********', $message);
        }

        // Erro de socket no Windows chega na codificação do sistema, não em
        // UTF-8, e viraria caractere quebrado no Blade.
        if (! mb_check_encoding($message, 'UTF-8')) {
            $message = mb_convert_encoding($message, 'UTF-8', 'Windows-1252');
        }

        return trim($message);
    }

    /** @return array{label: string, status: string, detail: string} */
    private function result(string $label, string $status, string $detail): array
    {
        return compact('label', 'status', 'detail');
    }
}
