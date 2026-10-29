<?php

namespace App\Services\EmailCampaign;

use App\Exceptions\SpreadsheetImportException;
use App\Imports\XlsxReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Lê a lista de destinatários: .csv ou .xlsx (primeira aba).
 *
 * Diferente das importações de cadastro (SpreadsheetImport), aqui não há
 * modelo obrigatório: a lista costuma vir pronta de outro sistema. Basta uma
 * coluna de e-mail — reconhecida por vários nomes, ou a lista sem cabeçalho
 * nenhum (a primeira linha já é um e-mail). As outras colunas viram variáveis
 * do texto pelo slug do cabeçalho ("Nome Completo" => {{nome_completo}}).
 *
 * A coluna de e-mail sai sempre como `email` e a de nome como `nome`.
 */
final class RecipientListReader
{
    private const EMAIL_ALIASES = [
        'email', 'e_mail', 'emails', 'e_mails', 'mail', 'endereco_de_email', 'endereco_de_e_mail', 'endereco_email',
        'email_address', 'correio_eletronico',
    ];

    private const NAME_ALIASES = [
        'nome', 'name', 'nome_completo', 'nome_do_socio', 'socio', 'destinatario', 'full_name',
    ];

    /**
     * @return array{
     *     columns: array<string, string>,
     *     rows: list<array{line: int, data: array<string, string>}>,
     *     headerless: bool
     * }
     *
     * @throws SpreadsheetImportException
     */
    public function read(string $path, string $extension): array
    {
        $raw = match (strtolower($extension)) {
            'csv', 'txt' => $this->csvRows($path),
            'xlsx', 'xls' => $this->spreadsheetRows($path, strtolower($extension)),
            default => throw new SpreadsheetImportException('Envie a lista em .csv ou .xlsx.'),
        };

        $headerIndex = null;

        foreach ($raw as $index => $cells) {
            if (implode('', $cells) !== '') {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new SpreadsheetImportException('O arquivo está vazio.');
        }

        $header = $raw[$headerIndex];
        $headerless = $this->looksLikeData($header);

        [$keys, $columns] = $headerless ? $this->generatedHeader($header) : $this->namedHeader($header);

        $rows = [];
        $start = $headerless ? $headerIndex : $headerIndex + 1;

        for ($i = $start, $total = count($raw); $i < $total; $i++) {
            $data = [];

            foreach ($keys as $position => $key) {
                $data[$key] = $raw[$i][$position] ?? '';
            }

            if (implode('', $data) === '') {
                continue;
            }

            $rows[] = ['line' => $i + 1, 'data' => $data];
        }

        return ['columns' => $columns, 'rows' => $rows, 'headerless' => $headerless];
    }

    /** A "primeira linha" já traz um e-mail: a lista não tem cabeçalho. */
    private function looksLikeData(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (filter_var(trim($cell), FILTER_VALIDATE_EMAIL)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>} posição => chave, chave => rótulo
     */
    private function generatedHeader(array $firstRow): array
    {
        $keys = [];
        $columns = [];
        $emailFound = false;

        foreach ($firstRow as $position => $cell) {
            if (! $emailFound && filter_var(trim($cell), FILTER_VALIDATE_EMAIL)) {
                $keys[$position] = 'email';
                $columns['email'] = 'E-mail';
                $emailFound = true;

                continue;
            }

            $key = 'coluna_' . ($position + 1);
            $keys[$position] = $key;
            $columns[$key] = 'Coluna ' . ($position + 1);
        }

        return [$keys, $columns];
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     *
     * @throws SpreadsheetImportException
     */
    private function namedHeader(array $header): array
    {
        $slugs = [];

        foreach ($header as $position => $label) {
            $slug = XlsxReader::slug($label);

            if ($slug !== '') {
                $slugs[$position] = $slug;
            }
        }

        $emailPosition = $this->find($slugs, self::EMAIL_ALIASES)
            ?? $this->findContaining($slugs, 'email')
            ?? $this->findContaining($slugs, 'e_mail');

        if ($emailPosition === null) {
            throw new SpreadsheetImportException(
                'A lista não tem coluna de e-mail. Dê à coluna o nome "E-mail" (ou envie só os endereços, um por linha, sem cabeçalho).'
            );
        }

        $namePosition = $this->find($slugs, self::NAME_ALIASES);

        $keys = [];
        $columns = [];

        foreach ($slugs as $position => $slug) {
            $key = match ($position) {
                $emailPosition => 'email',
                $namePosition => 'nome',
                default => $slug,
            };

            // Dois cabeçalhos com o mesmo slug ("Telefone" e "telefone").
            $base = $key;
            for ($n = 2; isset($columns[$key]); $n++) {
                $key = $base . '_' . $n;
            }

            $keys[$position] = $key;
            $columns[$key] = trim($header[$position]);
        }

        return [$keys, $columns];
    }

    private function find(array $slugs, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $position = array_search($alias, $slugs, true);

            if ($position !== false) {
                return $position;
            }
        }

        return null;
    }

    private function findContaining(array $slugs, string $needle): ?int
    {
        foreach ($slugs as $position => $slug) {
            if (str_contains($slug, $needle)) {
                return $position;
            }
        }

        return null;
    }

    /**
     * CSV exportado do Excel em português vem com `;` e em Windows-1252; o do
     * Google Planilhas, com `,` e em UTF-8. Os dois são aceitos.
     *
     * @return list<list<string>>
     */
    private function csvRows(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $delimiter = $this->delimiter($content);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $cells);
        }

        fclose($stream);

        return $rows;
    }

    private function delimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';
        $counts = [];

        foreach ([';', ',', "\t", '|'] as $candidate) {
            $counts[$candidate] = substr_count($firstLine, $candidate);
        }

        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    /**
     * @return list<list<string>>
     *
     * @throws SpreadsheetImportException
     */
    private function spreadsheetRows(string $path, string $extension): array
    {
        try {
            $reader = IOFactory::createReader($extension === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($path);
        } catch (Throwable $e) {
            throw new SpreadsheetImportException('Não foi possível ler a planilha. Confira se o arquivo é mesmo .xlsx ou salve como .csv.');
        }

        $rows = [];

        foreach ($spreadsheet->getActiveSheet()->toArray(null, true, false, false) as $cells) {
            $rows[] = array_map(function ($value) {
                // Telefone e matrícula digitados como número: 61999990000.0 => "61999990000".
                if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
                    return (string) (int) $value;
                }

                return trim((string) $value);
            }, $cells);
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }
}
