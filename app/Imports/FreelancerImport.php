<?php

namespace App\Imports;

use App\Exceptions\ImportRowException;
use App\Http\Requests\StoreFreelancerRequest;
use App\Services\FreelancerService as FreelancerServiceManager;

/**
 * Cadastro em massa de freelancers a partir do modelo .xlsx.
 */
class FreelancerImport extends SpreadsheetImport
{
    /** CPFs já vistos no arquivo => linha em que apareceram. */
    private array $seenCpfs = [];

    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function columns(): array
    {
        return [
            'name' => 'Nome *',
            'cpf' => 'CPF *',
            'rg' => 'RG *',
            'email' => 'E-mail',
            'telephone' => 'Telefone *',
            'nacionality' => 'Nacionalidade *',
            'civil_status' => 'Estado civil *',
            'address' => 'Endereço *',
            'pix_key' => 'Chave PIX',
        ];
    }

    public function aliases(): array
    {
        return [
            'nome' => 'name',
            'name' => 'name',
            'cpf' => 'cpf',
            'rg' => 'rg',
            'e_mail' => 'email',
            'email' => 'email',
            'telefone' => 'telephone',
            'telephone' => 'telephone',
            'nacionalidade' => 'nacionality',
            'nacionality' => 'nacionality',
            'estado_civil' => 'civil_status',
            'civil_status' => 'civil_status',
            'endereco' => 'address',
            'address' => 'address',
            'chave_pix' => 'pix_key',
            'pix' => 'pix_key',
            'pix_key' => 'pix_key',
        ];
    }

    public function example(): array
    {
        return [
            'name' => 'Maria da Silva',
            'cpf' => '12345678901',
            'rg' => 'MG1234567',
            'email' => 'maria.silva@exemplo.com',
            'telephone' => '31999998888',
            'nacionality' => 'Brasileira',
            'civil_status' => 'Solteira',
            'address' => 'Rua das Flores, 100 - Belo Horizonte/MG',
            'pix_key' => '12345678901',
        ];
    }

    public function templateName(): string
    {
        return 'modelo-importacao-freelancers.xlsx';
    }

    protected function textColumns(): array
    {
        return ['cpf', 'rg', 'telephone', 'pix_key'];
    }

    /** As mesmas regras do cadastro individual, sem o campo de auditoria. */
    protected function rules(): array
    {
        $rules = (new StoreFreelancerRequest())->rules();
        unset($rules['created_by']);

        return $rules;
    }

    protected function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'telephone' => 'telefone',
            'nacionality' => 'nacionalidade',
            'civil_status' => 'estado civil',
            'address' => 'endereço',
            'pix_key' => 'chave PIX',
        ];
    }

    protected function prepare(array $row, int $line): array
    {
        $cpf = ImportValues::cpf($row['cpf'] ?? '');

        // A regra `unique` só enxerga o banco; CPF repetido dentro do próprio
        // arquivo passaria despercebido até o insert estourar.
        if ($cpf !== '' && isset($this->seenCpfs[$cpf])) {
            throw new ImportRowException('CPF ' . $cpf . ' repetido (já informado na linha ' . $this->seenCpfs[$cpf] . ').');
        }

        if ($cpf !== '') {
            $this->seenCpfs[$cpf] = $line;
        }

        return [
            'name' => $row['name'] ?? '',
            'cpf' => $cpf,
            'rg' => $row['rg'] ?? '',
            'email' => blank($row['email'] ?? '') ? null : $row['email'],
            'telephone' => $row['telephone'] ?? '',
            'nacionality' => $row['nacionality'] ?? '',
            'civil_status' => $row['civil_status'] ?? '',
            'address' => $row['address'] ?? '',
            'pix_key' => blank($row['pix_key'] ?? '') ? null : $row['pix_key'],
        ];
    }

    protected function persist(array $data)
    {
        return $this->freelancerService->create($data);
    }
}
