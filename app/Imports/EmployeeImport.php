<?php

namespace App\Imports;

use App\Exceptions\ImportRowException;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use App\Models\Sector;
use App\Support\Cpf;

/**
 * Carga em massa de funcionários pelo modelo .xlsx (módulo Funcionários).
 *
 * A matrícula é a chave: matrícula nova cria o funcionário, matrícula que já
 * existe atualiza nome, CPF, setor, cargo e admissão — assim o RH reenvia a
 * planilha inteira sempre que quiser, sem duplicar ninguém. Célula opcional
 * em branco NÃO apaga o que está gravado (planilha parcial não destrói
 * cadastro).
 *
 * Tudo-ou-nada, como toda importação do sistema (ver SpreadsheetImport):
 * qualquer linha com erro e nada é gravado.
 *
 * O setor é escrito pelo nome e precisa já existir — a planilha não cria
 * setor, porque setor dá acesso ao painel e isso é decisão da tela de
 * Setores. Rescisão, férias e afastamento ficam de fora: são eventos com
 * data e motivo, registrados na ficha.
 */
class EmployeeImport extends SpreadsheetImport
{
    /** matrícula => linha, CPF => linha: repetições dentro do próprio arquivo. */
    private array $seenCodes = [];
    private array $seenCpfs = [];

    /** nome normalizado do setor => id (ou null se não existe). */
    private array $sectorCache = [];

    public function columns(): array
    {
        return [
            'employee_code' => 'Matrícula *',
            'name' => 'Nome *',
            'cpf' => 'CPF *',
            'sector' => 'Setor *',
            'position' => 'Cargo',
            'admission_date' => 'Data de admissão',
        ];
    }

    public function aliases(): array
    {
        return [
            'matricula' => 'employee_code',
            'employee_code' => 'employee_code',
            'codigo' => 'employee_code',
            'nome' => 'name',
            'name' => 'name',
            'nome_completo' => 'name',
            'cpf' => 'cpf',
            'setor' => 'sector',
            'sector' => 'sector',
            'departamento' => 'sector',
            'cargo' => 'position',
            'funcao' => 'position',
            'position' => 'position',
            'data_de_admissao' => 'admission_date',
            'data_admissao' => 'admission_date',
            'admissao' => 'admission_date',
        ];
    }

    public function example(): array
    {
        return [
            'employee_code' => '12345',
            'name' => 'Maria da Silva',
            'cpf' => '12345678909',
            'sector' => 'Portaria',
            'position' => 'Porteira',
            'admission_date' => '15/03/2024',
        ];
    }

    public function templateName(): string
    {
        return 'modelo-importacao-funcionarios.xlsx';
    }

    protected function optionalColumns(): array
    {
        return ['position', 'admission_date'];
    }

    protected function textColumns(): array
    {
        // Matrícula "01234" e CPF perdem o zero à esquerda se o Excel tratar
        // a célula como número.
        return ['employee_code', 'cpf'];
    }

    protected function rules(): array
    {
        $rules = EmployeeRequest::rulesFor();

        // A unicidade é conferida em validateRow(): matrícula que já existe
        // aqui quer dizer "atualizar", não erro.
        $rules['employee_code'] = ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/'];
        $rules['cpf'] = ['required', 'string', EmployeeRequest::validCpf()];

        unset($rules['termination_date'], $rules['termination_reason']);

        return $rules;
    }

    protected function attributes(): array
    {
        return EmployeeRequest::labels();
    }

    /**
     * @throws ImportRowException
     */
    protected function prepare(array $row, int $line): array
    {
        $code = trim((string) ($row['employee_code'] ?? ''));
        $cpf = ImportValues::cpf((string) ($row['cpf'] ?? ''));
        $sectorName = trim((string) ($row['sector'] ?? ''));

        if ($code !== '' && isset($this->seenCodes[$code])) {
            throw new ImportRowException("Matrícula {$code} repetida (já informada na linha {$this->seenCodes[$code]}).");
        }

        if ($cpf !== '' && isset($this->seenCpfs[$cpf])) {
            throw new ImportRowException('CPF ' . Cpf::format($cpf) . " repetido (já informado na linha {$this->seenCpfs[$cpf]}).");
        }

        if ($sectorName === '') {
            throw new ImportRowException('Setor não informado.');
        }

        $sectorId = $this->resolveSector($sectorName);

        if ($sectorId === null) {
            throw new ImportRowException("Setor \"{$sectorName}\" não existe. Crie-o na tela de Setores ou corrija o nome.");
        }

        if ($code !== '') {
            $this->seenCodes[$code] = $line;
        }

        if ($cpf !== '') {
            $this->seenCpfs[$cpf] = $line;
        }

        $admission = ImportValues::date((string) ($row['admission_date'] ?? ''), 'Data de admissão');

        return [
            'employee_code' => $code,
            'name' => trim((string) ($row['name'] ?? '')),
            'cpf' => $cpf,
            'sector_id' => $sectorId,
            'position' => blank($row['position'] ?? '') ? null : trim((string) $row['position']),
            'admission_date' => $admission === '' ? null : $admission,
        ];
    }

    protected function validateRow(array $data): array
    {
        $existing = Employee::withTrashed()->where('employee_code', $data['employee_code'])->first();

        if ($existing?->trashed()) {
            return ["A matrícula {$data['employee_code']} é de um funcionário excluído ({$existing->name}). Restaure-o na tela antes de importar."];
        }

        $cpfOwner = Employee::where('cpf', $data['cpf'])
            ->when($existing, fn ($q) => $q->where('id', '<>', $existing->id))
            ->first();

        if ($cpfOwner) {
            return ['CPF ' . Cpf::format($data['cpf']) . " já pertence a {$cpfOwner->name} (matrícula {$cpfOwner->employee_code})."];
        }

        return [];
    }

    protected function persist(array $data)
    {
        $employee = Employee::where('employee_code', $data['employee_code'])->first();

        if (!$employee) {
            return Employee::create($data + ['source' => Employee::SOURCE_SPREADSHEET]);
        }

        // Opcional em branco não apaga o que já está gravado.
        $employee->update(array_filter($data, fn ($value) => $value !== null));

        return $employee;
    }

    private function resolveSector(string $name): ?int
    {
        $key = mb_strtolower(trim($name));

        if (!array_key_exists($key, $this->sectorCache)) {
            $this->sectorCache[$key] = Sector::whereRaw('LOWER(TRIM(name)) = ?', [$key])->value('id');
        }

        return $this->sectorCache[$key];
    }
}
