<?php

namespace App\Imports;

use App\Exceptions\ImportRowException;
use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Support\Collection;

/**
 * Registro em massa de serviços a partir do modelo .xlsx.
 *
 * O vínculo com o freelancer é feito pelo CPF: o id interno não aparece na
 * planilha, quem preenche só precisa conhecer o CPF de quem já está cadastrado.
 */
class FreelancerServiceImport extends SpreadsheetImport
{
    private ?Collection $freelancersByCpf = null;

    private ?Collection $functionsByName = null;

    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function columns(): array
    {
        return [
            'cpf' => 'CPF do freelancer *',
            'function' => 'Função *',
            'location' => 'Evento / Local *',
            'start_date' => 'Data (dd/mm/aaaa) *',
            'start_time' => 'Início (HH:MM) *',
            'end_time' => 'Término (HH:MM) *',
            'description' => 'Descrição / Justificativa',
        ];
    }

    public function aliases(): array
    {
        return [
            'cpf_do_freelancer' => 'cpf',
            'cpf' => 'cpf',
            'funcao' => 'function',
            'function' => 'function',
            'evento_local' => 'location',
            'evento' => 'location',
            'local' => 'location',
            'location' => 'location',
            'data_dd_mm_aaaa' => 'start_date',
            'data' => 'start_date',
            'data_de_inicio' => 'start_date',
            'start_date' => 'start_date',
            'inicio_hh_mm' => 'start_time',
            'inicio' => 'start_time',
            'start_time' => 'start_time',
            'termino_hh_mm' => 'end_time',
            'termino' => 'end_time',
            'fim' => 'end_time',
            'end_time' => 'end_time',
            'descricao_justificativa' => 'description',
            'descricao' => 'description',
            'justificativa' => 'description',
            'description' => 'description',
        ];
    }

    public function example(): array
    {
        // Exemplo com dados reais do sistema, quando houver: assim quem
        // preenche vê logo o formato de CPF e o nome exato de uma função.
        return [
            'cpf' => Freelancer::orderBy('name')->value('cpf') ?? '12345678901',
            'function' => FunctionFreelancer::orderBy('name')->value('name') ?? 'Garçom',
            'location' => 'Festa de Confraternização - Salão Nobre',
            'start_date' => now()->format('d/m/Y'),
            'start_time' => '18:00',
            'end_time' => '23:00',
            'description' => 'Reforço de equipe por conta do público acima do previsto.',
        ];
    }

    public function templateName(): string
    {
        return 'modelo-importacao-servicos.xlsx';
    }

    protected function textColumns(): array
    {
        return ['cpf'];
    }

    /** As mesmas regras do registro individual, sem o campo de auditoria. */
    protected function rules(): array
    {
        $rules = (new StoreFreelancerServiceRequest())->rules();
        unset($rules['created_by'], $rules['status_id']);

        return $rules;
    }

    protected function attributes(): array
    {
        return [
            'location' => 'evento/local',
            'description' => 'descrição/justificativa',
            'freelancer_id' => 'freelancer',
            'function_freelancer_id' => 'função',
            'start_date' => 'data de início',
            'start_time' => 'horário de início',
            'end_time' => 'horário de término',
        ];
    }

    protected function prepare(array $row, int $line): array
    {
        $cpf = ImportValues::cpf($row['cpf'] ?? '');

        if ($cpf === '') {
            throw new ImportRowException('informe o CPF do freelancer.');
        }

        $freelancer = $this->freelancers()->get($cpf);

        if ($freelancer === null) {
            throw new ImportRowException(
                'nenhum freelancer cadastrado com o CPF ' . $cpf . '. Cadastre-o antes de importar os serviços.'
            );
        }

        // Contrato não é gerado enquanto o cadastro do freelancer estiver
        // incompleto — o mesmo bloqueio do registro individual e do tablet.
        if (!$freelancer->hasCompleteContractData()) {
            throw new ImportRowException(
                'o cadastro de ' . $freelancer->name . ' (CPF ' . $cpf . ') está incompleto: '
                . 'faltam ' . implode(', ', $freelancer->missingContractFieldLabels())
                . '. Complete o cadastro antes de gerar o contrato.'
            );
        }

        $functionName = trim($row['function'] ?? '');
        $functionId = $this->functions()->get(mb_strtolower($functionName));

        if ($functionId === null) {
            throw new ImportRowException(
                'função "' . $functionName . '" não existe. Use exatamente um dos nomes cadastrados em Funções.'
            );
        }

        return [
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $functionId,
            'location' => $row['location'] ?? '',
            'description' => blank($row['description'] ?? '') ? null : $row['description'],
            'start_date' => ImportValues::date($row['start_date'] ?? '', 'data'),
            'start_time' => ImportValues::time($row['start_time'] ?? '', 'horário de início'),
            'end_time' => ImportValues::time($row['end_time'] ?? '', 'horário de término'),
        ];
    }

    /**
     * As checagens de duração que o formulário faz em `withValidator` — aqui
     * não há FormRequest, então são aplicadas sobre a linha já validada.
     */
    protected function validateRow(array $data): array
    {
        $error = FreelancerService::scheduleError($data['start_time'], $data['end_time']);

        return $error ? [$error] : [];
    }

    protected function persist(array $data)
    {
        return $this->freelancerService->createService($data);
    }

    /** CPF => freelancer, carregado uma vez por importação. */
    private function freelancers(): Collection
    {
        return $this->freelancersByCpf ??= Freelancer::all()
            ->keyBy(fn(Freelancer $f) => ImportValues::cpf((string) $f->cpf));
    }

    /** Nome em minúsculas => id, para casar sem depender de caixa. */
    private function functions(): Collection
    {
        return $this->functionsByName ??= FunctionFreelancer::pluck('id', 'name')
            ->mapWithKeys(fn($id, $name) => [mb_strtolower(trim((string) $name)) => $id]);
    }
}
