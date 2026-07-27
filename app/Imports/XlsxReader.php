<?php

namespace App\Imports;

use App\Exceptions\SpreadsheetImportException;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Leitura crua de um .xlsx: transforma a primeira aba em linhas associativas.
 *
 * Todo valor volta como string já normalizada (datas em Y-m-d, horários em
 * H:i:s), para que os importadores lidem sempre com texto, independentemente
 * de a célula ter sido digitada como número, data ou texto no Excel.
 */
class XlsxReader
{
    /**
     * @param  array<string, string>  $aliases  slug do cabeçalho => nome do campo
     * @return array{fields: array<int, string>, rows: array<int, array{line: int, data: array<string, string>}>}
     *
     * @throws SpreadsheetImportException
     */
    public static function read(string $path, array $aliases): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($path);
        } catch (Throwable $e) {
            throw new SpreadsheetImportException(
                'Não foi possível ler a planilha. Envie um arquivo .xlsx gerado a partir do modelo.'
            );
        }

        $sheet = $spreadsheet->getActiveSheet();
        $columns = [];

        foreach ($sheet->getRowIterator(1, 1) as $headerRow) {
            foreach ($headerRow->getCellIterator() as $cell) {
                $slug = static::slug(static::value($cell));

                if ($slug !== '') {
                    $columns[$cell->getColumn()] = $aliases[$slug] ?? $slug;
                }
            }
        }

        if (empty($columns)) {
            throw new SpreadsheetImportException('A primeira linha da planilha deve conter o cabeçalho das colunas.');
        }

        $result = ['fields' => array_values($columns), 'rows' => []];

        if ($sheet->getHighestDataRow() < 2) {
            $spreadsheet->disconnectWorksheets();

            return $result;
        }

        foreach ($sheet->getRowIterator(2) as $row) {
            $data = [];

            foreach ($row->getCellIterator() as $cell) {
                $field = $columns[$cell->getColumn()] ?? null;

                if ($field !== null) {
                    $data[$field] = static::value($cell);
                }
            }

            // Linha totalmente em branco costuma ser sobra de formatação.
            if (implode('', $data) === '') {
                continue;
            }

            $result['rows'][] = ['line' => $row->getRowIndex(), 'data' => $data];
        }

        $spreadsheet->disconnectWorksheets();

        return $result;
    }

    /**
     * Rótulo do cabeçalho reduzido a um identificador comparável:
     * "Estado Civil *" => "estado_civil".
     */
    public static function slug(string $label): string
    {
        return (string) Str::of($label)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private static function value(Cell $cell): string
    {
        try {
            $value = $cell->getCalculatedValue();
        } catch (Throwable $e) {
            $value = $cell->getValue();
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        // Célula formatada como data/hora chega como número serial do Excel.
        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            $date = ExcelDate::excelToDateTimeObject((float) $value);

            // Serial menor que 1 não tem parte de data: é só um horário.
            return (float) $value < 1 ? $date->format('H:i:s') : $date->format('Y-m-d');
        }

        return trim((string) $value);
    }
}
