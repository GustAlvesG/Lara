<?php

namespace App\Imports;

use App\Exceptions\ImportRowException;
use App\Exceptions\SpreadsheetImportException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Base da importação em massa por planilha.
 *
 * A importação é tudo-ou-nada: as linhas são conferidas primeiro e, havendo
 * qualquer erro, nada é gravado. Assim o usuário corrige a planilha e reenvia
 * sem risco de importar metade dos registros duas vezes.
 */
abstract class SpreadsheetImport
{
    /** Colunas do modelo, na ordem: campo => rótulo do cabeçalho. */
    abstract public function columns(): array;

    /** Cabeçalhos aceitos na leitura: slug => campo. */
    abstract public function aliases(): array;

    /** Linha de exemplo do arquivo modelo: campo => valor. */
    abstract public function example(): array;

    /** Nome do arquivo modelo oferecido para download. */
    abstract public function templateName(): string;

    /** Regras aplicadas a cada linha já preparada. */
    abstract protected function rules(): array;

    /** Converte a linha lida nos campos esperados por rules(). */
    abstract protected function prepare(array $row, int $line): array;

    /** Grava uma linha validada. */
    abstract protected function persist(array $data);

    /** Nomes amigáveis dos campos nas mensagens de erro. */
    protected function attributes(): array
    {
        return [];
    }

    /**
     * Conferências que dependem da linha já validada. Devolve as mensagens
     * de erro encontradas.
     */
    protected function validateRow(array $data): array
    {
        return [];
    }

    /**
     * @return array{imported: int, errors: array<int, string>, records: Collection}
     *
     * @throws SpreadsheetImportException
     */
    public function import(string $path): array
    {
        ['fields' => $fields, 'rows' => $rows] = XlsxReader::read($path, $this->aliases());

        $this->assertHeader($fields);

        if (empty($rows)) {
            throw new SpreadsheetImportException('A planilha não tem nenhuma linha preenchida abaixo do cabeçalho.');
        }

        $errors = [];
        $validated = [];

        foreach ($rows as $row) {
            try {
                $data = $this->prepare($row['data'], $row['line']);
            } catch (ImportRowException $e) {
                $errors[] = $this->rowError($row['line'], $e->getMessage());

                continue;
            }

            $validator = Validator::make($data, $this->rules(), [], $this->attributes());

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = $this->rowError($row['line'], $message);
                }

                continue;
            }

            $data = $validator->validated();

            if ($messages = $this->validateRow($data)) {
                foreach ($messages as $message) {
                    $errors[] = $this->rowError($row['line'], $message);
                }

                continue;
            }

            $validated[] = $data;
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors, 'records' => collect()];
        }

        $records = DB::transaction(function () use ($validated) {
            return collect($validated)->map(fn(array $data) => $this->persist($data));
        });

        return ['imported' => $records->count(), 'errors' => [], 'records' => $records];
    }

    /** O modelo em branco, já com cabeçalho e uma linha de exemplo. */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = $this->buildTemplate();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $this->templateName(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Colunas cujo conteúdo deve ser guardado como texto no modelo — CPF e
     * afins perdem os zeros à esquerda se o Excel tratar como número.
     */
    protected function textColumns(): array
    {
        return [];
    }

    protected function buildTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Modelo');

        $fields = array_keys($this->columns());
        $example = $this->example();
        $textColumns = $this->textColumns();

        foreach (array_values($this->columns()) as $index => $label) {
            $column = $sheet->getCell([$index + 1, 1])->getColumn();
            $field = $fields[$index];

            $sheet->setCellValue([$index + 1, 1], $label);
            $sheet->getColumnDimension($column)->setAutoSize(true);

            if (in_array($field, $textColumns, true)) {
                $sheet->getStyle($column)->getNumberFormat()->setFormatCode('@');
            }

            $sheet->setCellValueExplicit(
                [$index + 1, 2],
                (string) ($example[$field] ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }

        $lastColumn = $sheet->getCell([count($fields), 1])->getColumn();
        $header = $sheet->getStyle('A1:' . $lastColumn . '1');
        $header->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFA00001');
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /**
     * Toda coluna do modelo precisa estar presente — uma coluna faltando
     * viraria um campo vazio em todas as linhas, com erro repetido e confuso.
     *
     * @throws SpreadsheetImportException
     */
    protected function assertHeader(array $fields): void
    {
        $missing = [];

        foreach ($this->columns() as $field => $label) {
            if (!in_array($field, $fields, true)) {
                $missing[] = $label;
            }
        }

        if ($missing) {
            throw new SpreadsheetImportException(
                'A planilha está sem as colunas: ' . implode(', ', $missing)
                . '. Baixe o arquivo modelo e preencha a partir dele.'
            );
        }
    }

    protected function rowError(int $line, string $message): string
    {
        return 'Linha ' . $line . ': ' . $message;
    }
}
