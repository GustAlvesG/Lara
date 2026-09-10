<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Sector;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class CompTimeService
{
    /** Validade de um lançamento, em dias: `due_date = entry_date + 180`. */
    const MAX_VALID_DAYS = 180;

    public const TYPE_CREDIT = 'CREDIT';
    public const TYPE_DEBIT  = 'DEBIT';

    /**
     * Crédito acima deste teto (em minutos) só entra se a coluna de observação
     * do espelho autorizar explicitamente ("Creditar"). Regra do RH: sobra
     * grande de um dia só precisa de aval, senão vira banco de horas por
     * acidente.
     */
    private const CREDIT_APPROVAL_THRESHOLD_MINUTES = 120;

    /**
     * Posições fixas das colunas no espelho de ponto. O relatório não tem
     * cabeçalho legível por máquina — a leitura é por índice de `<td>` mesmo.
     * Concentradas aqui porque, quando o layout do relatório mudar, é este
     * bloco que muda, e não cinco lugares espalhados pelo parser.
     */
    private const COL_CREDIT_FALLBACK = 4;
    private const COL_OBSERVATION     = 8;
    private const COL_DEBIT           = 9;
    private const COL_CREDIT          = 10;
    private const COL_APPROVAL        = 11;

    /**
     * Memória do último arquivo lido, para não pagar o parse duas vezes dentro
     * do mesmo job. A detecção de duplicatas e a gravação liam o mesmo arquivo
     * do zero, uma atrás da outra.
     *
     * @var array{key: string, rows: array}|null
     */
    private ?array $parsedCache = null;

    /** Cache de setores resolvidos por nome normalizado, dentro de uma importação. */
    private array $sectorCache = [];

    // ------------------------------------------------------------------
    // Acesso
    // ------------------------------------------------------------------

    /**
     * O que este usuário pode enxergar no Banco de Horas.
     *
     *   ['type' => 'all']                            RH (setor ou permissão)
     *   ['type' => 'sectors', 'values' => [1, 2]]    coordenador dos setores
     *   ['type' => 'employee_code', 'value' => '42'] colaborador: só ele
     *   ['type' => 'none']                           nem matrícula tem
     *
     * A regra de coordenador passou a devolver **ids de setor**, e não nomes de
     * departamento. Antes o acesso dependia de `employees.department` bater
     * como texto com `sectors.name`: um acento a mais no sistema de ponto e o
     * coordenador deixava de enxergar a própria equipe, sem erro na tela.
     */
    public function accessFor(User $user): array
    {
        if ($user->canManageCompTime()) {
            return ['type' => 'all'];
        }

        $sectorIds = $user->coordinatorSectors()->pluck('sectors.id')->all();
        if (!empty($sectorIds)) {
            return ['type' => 'sectors', 'values' => $sectorIds];
        }

        if ($user->matricula) {
            return ['type' => 'employee_code', 'value' => $user->matricula];
        }

        return ['type' => 'none'];
    }

    /** Aplica a restrição de acesso a uma consulta de funcionários. */
    public function applyAccessScope($query, array $access)
    {
        return match ($access['type']) {
            'all'           => $query,
            'sectors'       => $query->whereIn('sector_id', $access['values']),
            'employee_code' => $query->where('employee_code', $access['value']),
            default         => $query->whereRaw('1 = 0'),
        };
    }

    /** Este usuário pode abrir a ficha deste funcionário? */
    public function canViewEmployee(array $access, Employee $employee): bool
    {
        return match ($access['type']) {
            'all'           => true,
            'sectors'       => in_array($employee->sector_id, $access['values']),
            'employee_code' => $employee->employee_code === $access['value'],
            default         => false,
        };
    }

    // ------------------------------------------------------------------
    // Importação
    // ------------------------------------------------------------------

    /**
     * Confere o arquivo contra o banco antes de gravar nada.
     *
     * Duas consultas no total: todos os funcionários do arquivo de uma vez e
     * todos os lançamentos já existentes no intervalo de datas do arquivo.
     * O confronto acontece em memória.
     */
    public function detectDuplicates(string $filePath): array
    {
        $rows = $this->parseFileRows($filePath);

        if (empty($rows)) {
            return ['new_entries' => [], 'duplicate_entries' => []];
        }

        $employees     = $this->employeesForRows($rows);
        $existingIndex = $this->existingEntryIndex($rows, $employees);

        $newEntries       = [];
        $duplicateEntries = [];

        foreach ($rows as $row) {
            $employee = $employees->get($row['employee_code']);

            // Funcionário que ainda não existe no banco não tem como colidir.
            if (!$employee) {
                $newEntries[] = $row;
                continue;
            }

            $existing = $existingIndex[$this->entryKey($employee->id, $row['entry_date'], $row['type'])] ?? null;

            if ($existing) {
                $duplicateEntries[] = array_merge($row, [
                    'existing_entry_id'   => $existing->id,
                    'old_amount_minutes'  => $existing->amount_minutes,
                    'old_balance_minutes' => $existing->balance_minutes,
                ]);
            } else {
                $newEntries[] = $row;
            }
        }

        return ['new_entries' => $newEntries, 'duplicate_entries' => $duplicateEntries];
    }

    /**
     * Importa o arquivo inteiro, sobrescrevendo o que já existir.
     * Usada quando a detecção não encontrou nenhuma duplicata para revisar.
     */
    public function importFile(string $filePath): array
    {
        return $this->applyRows($this->parseFileRows($filePath), null);
    }

    /**
     * Importa depois da revisão humana: lançamentos novos entram sempre,
     * duplicatas só sobrescrevem se o id vier marcado em `$acceptedEntryIds`.
     */
    public function importWithDecisions(string $filePath, array $acceptedEntryIds): array
    {
        return $this->applyRows($this->parseFileRows($filePath), $acceptedEntryIds);
    }

    /**
     * Grava as linhas do arquivo.
     *
     * `$acceptedEntryIds === null` sobrescreve toda duplicata; um array
     * restringe a sobrescrita aos ids marcados na tela de revisão.
     *
     * O saldo **não** é calculado linha a linha durante a gravação, como era
     * antes. Grava-se tudo primeiro e recalcula-se cada funcionário afetado uma
     * vez, no fim. Compensação linha a linha depende da ordem em que o arquivo
     * chega para dar o resultado certo; a passada única, não.
     */
    private function applyRows(array $rows, ?array $acceptedEntryIds): array
    {
        if (empty($rows)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'employees' => 0];
        }

        $acceptedIds = $acceptedEntryIds === null
            ? null
            : array_flip(array_map('intval', $acceptedEntryIds));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $affectedEmployeeIds = [];

        DB::beginTransaction();
        try {
            $employeeCache = [];
            foreach ($rows as $row) {
                $employee = $this->upsertEmployee($row, $employeeCache);
                $affectedEmployeeIds[$employee->id] = true;
            }

            // Só agora, com todos os funcionários garantidos no banco, dá para
            // montar o índice do que já existe — inclusive para quem acabou de
            // ser criado nesta mesma importação.
            $employees     = collect($employeeCache);
            $existingIndex = $this->existingEntryIndex($rows, $employees);

            foreach ($rows as $row) {
                $employee = $employeeCache[$row['employee_code']];
                $key      = $this->entryKey($employee->id, $row['entry_date'], $row['type']);
                $existing = $existingIndex[$key] ?? null;

                if (!$existing) {
                    $entry = TimeEntry::create([
                        'employee_id'     => $employee->id,
                        'entry_date'      => $row['entry_date'],
                        'reference_time'  => $row['reference_time'],
                        'entry_times'     => $row['entry_times'],
                        'type'            => $row['type'],
                        'amount_minutes'  => $row['amount_minutes'],
                        'balance_minutes' => $row['amount_minutes'],
                        'due_date'        => $row['due_date'],
                    ]);
                    $existingIndex[$key] = $entry;
                    $created++;
                    continue;
                }

                if ($acceptedIds !== null && !isset($acceptedIds[$existing->id])) {
                    // Duplicata que o usuário deixou desmarcada na revisão.
                    $skipped++;
                    continue;
                }

                $existing->amount_minutes  = $row['amount_minutes'];
                $existing->balance_minutes = $row['amount_minutes'];
                $existing->save();
                $updated++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $employeeIds = array_keys($affectedEmployeeIds);
        $this->recalculateAllBalances($employeeIds);

        return [
            'created'   => $created,
            'updated'   => $updated,
            'skipped'   => $skipped,
            'employees' => count($employeeIds),
        ];
    }

    /** Todos os funcionários citados no arquivo, indexados pela matrícula. */
    private function employeesForRows(array $rows)
    {
        return Employee::whereIn('employee_code', array_unique(array_column($rows, 'employee_code')))
            ->get()
            ->keyBy('employee_code');
    }

    /**
     * Lançamentos que já existem no banco dentro do intervalo de datas do
     * arquivo, indexados por `employee_id|data|tipo`.
     */
    private function existingEntryIndex(array $rows, $employees): array
    {
        $employeeIds = $employees->pluck('id')->filter()->values()->all();
        if (empty($employeeIds)) {
            return [];
        }

        $dates = array_map(fn ($r) => $r['entry_date']->format('Y-m-d'), $rows);

        // Limite superior no fim do dia, e não na meia-noite: registro antigo
        // gravado com hora ('2026-06-01 00:00:00') ficaria fora do intervalo
        // num arquivo de um dia só. Continua sendo comparação de faixa, então
        // o índice de `entry_date` segue valendo — `whereDate` o descartaria.
        $index = [];
        TimeEntry::whereIn('employee_id', $employeeIds)
            ->whereBetween('entry_date', [min($dates), max($dates) . ' 23:59:59'])
            ->get()
            ->each(function (TimeEntry $entry) use (&$index) {
                $index[$this->entryKey($entry->employee_id, $entry->entry_date, $entry->type)] = $entry;
            });

        return $index;
    }

    /** Chave de identidade de um lançamento: funcionário + dia + tipo. */
    private function entryKey($employeeId, $date, string $type): string
    {
        return $employeeId . '|' . Carbon::parse($date)->format('Y-m-d') . '|' . $type;
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** Setores visíveis para o usuário, no formato `[id => nome]`. */
    public function getSectors(array $access = ['type' => 'all'])
    {
        $employeeSectorIds = $this->applyAccessScope(Employee::query(), $access)
            ->whereNotNull('sector_id')
            ->distinct()
            ->pluck('sector_id');

        return Sector::whereIn('id', $employeeSectorIds)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function filterEmployees(array $filters, array $access = ['type' => 'all'])
    {
        $query = $this->applyAccessScope(Employee::query(), $access);

        if (!empty($filters['sector_id'])) {
            $query->where('sector_id', $filters['sector_id']);
        }

        if (!empty($filters['employee_name'])) {
            $query->where('name', 'like', '%' . $filters['employee_name'] . '%');
        }

        if (!empty($filters['employee_code'])) {
            $query->where('employee_code', $filters['employee_code']);
        }

        return $query->with('sector')->orderBy('name')->get();
    }

    public function getTimeEntriesForEmployees($employees, $filters)
    {
        if ($employees->isEmpty()) {
            return [];
        }

        // Uma consulta para todos os funcionários, agrupada em memória. Antes
        // era uma consulta por funcionário dentro do laço.
        $entries = $this->applyEntryFilters(
            TimeEntry::whereIn('employee_id', $employees->pluck('id')),
            $filters
        )->orderBy('entry_date')->get()->groupBy('employee_id');

        $response = [];
        foreach ($employees as $employee) {
            $employeeEntries = $entries->get($employee->id);
            if (!$employeeEntries || $employeeEntries->isEmpty()) {
                continue;
            }

            $response[] = [
                'employee' => $employee,
                'entries'  => $employeeEntries,
                'summary'  => $this->buildEmployeeSummary($employeeEntries),
            ];
        }

        return $response;
    }

    private function applyEntryFilters($query, array $filters)
    {
        if (!empty($filters['period_start'])) {
            $query->where('entry_date', '>=', Carbon::parse($filters['period_start'])->toDateString());
        }

        if (!empty($filters['period_end'])) {
            $query->where('entry_date', '<=', Carbon::parse($filters['period_end'])->toDateString());
        }

        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'with_balance'    => $query->where('balance_minutes', '>', 0),
                'without_balance' => $query->where('balance_minutes', 0),
                'credit_only'     => $query->where('type', self::TYPE_CREDIT),
                'debit_only'      => $query->where('type', self::TYPE_DEBIT),
                default           => null,
            };
        }

        return $query;
    }

    public function getTimeEntryDetails($employeeId, $periodStart = null, $periodEnd = null)
    {
        $query = TimeEntry::where('employee_id', $employeeId);

        if ($periodStart) {
            $query->where('entry_date', '>=', Carbon::parse($periodStart)->toDateString());
        }

        if ($periodEnd) {
            $query->where('entry_date', '<=', Carbon::parse($periodEnd)->toDateString());
        }

        $timeEntries = $query->orderBy('entry_date', 'asc')->get();
        $employee    = Employee::with(['sector', 'absences'])->find($employeeId);

        $active = $timeEntries->where('written_off', false);

        $dashboard = [
            'total_credit_minutes' => $active->where('type', self::TYPE_CREDIT)->sum('amount_minutes'),
            'total_debit_minutes'  => $active->where('type', self::TYPE_DEBIT)->sum('amount_minutes'),
        ];
        $dashboard['net_balance_minutes'] = $dashboard['total_credit_minutes'] - $dashboard['total_debit_minutes'];
        $dashboard['next_expiring_entries'] = $active
            ->filter(fn ($entry) => $entry->balance_minutes > 0)
            ->sortBy('due_date')
            ->take(3)
            ->values();

        $this->attachAdjustments($timeEntries);

        return ['employee' => $employee, 'timeEntries' => $timeEntries, 'dashboard' => $dashboard];
    }

    /**
     * Lançamentos de um dia de um funcionário, com os ajustes de compensação
     * dos dois lados (o que este dia abateu e o que abateu este dia).
     *
     * O controller chamava `getDayDetails($day)` — método que não existia, com
     * um argumento a menos que o `showDayDetails($employeeId, $day)` que
     * existia. A rota `comp-time.show.day.details` era erro fatal garantido.
     */
    public function getDayDetails(int $employeeId, string $day)
    {
        $timeEntries = TimeEntry::where('employee_id', $employeeId)
            ->whereDate('entry_date', Carbon::parse($day)->toDateString())
            ->orderBy('id')
            ->get();

        $this->attachAdjustments($timeEntries, bothSides: true);

        return $timeEntries;
    }

    /**
     * Pendura em cada lançamento os ajustes que o envolvem, resolvendo de
     * quebra a data do lançamento do outro lado da compensação.
     */
    private function attachAdjustments($timeEntries, bool $bothSides = false): void
    {
        if ($timeEntries->isEmpty()) {
            return;
        }

        $entryIds = $timeEntries->pluck('id');

        $query = TimeAdjustment::whereIn('entry_time_to_adjust_id', $entryIds);
        if ($bothSides) {
            $query->orWhereIn('entry_time_adjusted_id', $entryIds);
        }

        $adjustments = $query->orderByDesc('before_adjustment_minutes')->get();

        $counterpartDates = TimeEntry::whereIn('id', $adjustments->pluck('entry_time_adjusted_id')->unique())
            ->pluck('entry_date', 'id');

        $byEntry = $adjustments->groupBy('entry_time_to_adjust_id');

        foreach ($timeEntries as $entry) {
            $entryAdjustments = $byEntry->get($entry->id, collect());
            foreach ($entryAdjustments as $adjustment) {
                $adjustment->adjustment_date = $counterpartDates->get($adjustment->entry_time_adjusted_id);
            }
            $entry->adjustments = $entryAdjustments;
        }
    }

    private function buildEmployeeSummary($entries): array
    {
        $active       = $entries->where('written_off', false);
        $totalCredits = $active->where('type', self::TYPE_CREDIT)->sum('balance_minutes');
        $totalDebits  = $active->where('type', self::TYPE_DEBIT)->sum('balance_minutes');
        $today        = now()->startOfDay();

        $expiredWithBalance = $active->filter(fn ($e) =>
            $e->balance_minutes > 0 && $e->due_date && Carbon::parse($e->due_date)->lt($today)
        );

        $nextExpiry = $active->filter(fn ($e) =>
            $e->balance_minutes > 0 && $e->due_date && Carbon::parse($e->due_date)->gte($today)
        )->sortBy('due_date')->first();

        return [
            'total_credits_minutes'   => $totalCredits,
            'total_debits_minutes'    => $totalDebits,
            'net_balance_minutes'     => $totalCredits - $totalDebits,
            'expired_balance_minutes' => $expiredWithBalance->sum('balance_minutes'),
            'expired_count'           => $expiredWithBalance->count(),
            'written_off_count'       => $entries->where('written_off', true)->count(),
            'next_expiry_entry'       => $nextExpiry,
            'days_to_expiry'          => $nextExpiry
                ? (int) now()->diffInDays(Carbon::parse($nextExpiry->due_date), false)
                : null,
        ];
    }

    // ------------------------------------------------------------------
    // Parsing
    // ------------------------------------------------------------------

    /**
     * Lê o espelho de ponto e devolve uma linha por lançamento.
     *
     * Memorizado por caminho + mtime: dentro de um mesmo job o arquivo era
     * lido e reprocessado do zero mais de uma vez (detectar e depois gravar).
     */
    private function parseFileRows(string $filePath): array
    {
        $key = $filePath . '|' . (@filemtime($filePath) ?: 0);

        if ($this->parsedCache !== null && $this->parsedCache['key'] === $key) {
            return $this->parsedCache['rows'];
        }

        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $crawler     = new Crawler($content);
        $tables      = $crawler->filter('table');
        $totalTables = $tables->count();

        // O relatório repete um bloco de 4 tabelas por funcionário: a primeira
        // traz o cabeçalho com os dados dele, a segunda o espelho de ponto, e
        // as outras duas são rodapé que não interessa aqui.
        $rows = [];
        for ($i = 0; $i < $totalTables; $i += 4) {
            if ($i + 1 >= $totalTables) {
                break;
            }

            $infoTable = $tables->eq($i)->filter('[data-bind="with: InfoFuncionario"]');
            if ($infoTable->count() === 0) {
                continue;
            }

            $rows = array_merge($rows, $this->parseEmployeeRows($infoTable, $tables->eq($i + 1)));
        }

        $rows = $this->dedupeRows($rows);

        $this->parsedCache = ['key' => $key, 'rows' => $rows];

        return $rows;
    }

    /**
     * Remove colisões **dentro do próprio arquivo**.
     *
     * A detecção de duplicatas só confrontava o arquivo com o banco. Duas
     * linhas do mesmo funcionário, dia e tipo no mesmo arquivo passavam
     * batidas pela revisão e, na gravação, a segunda sobrescrevia a primeira
     * calada. Aqui a última ocorrência vence — mesmo resultado de antes, só
     * que uma vez, antes de qualquer confronto com o banco.
     */
    private function dedupeRows(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['employee_code'] . '|' . $row['entry_date']->format('Y-m-d') . '|' . $row['type']] = $row;
        }

        return array_values($byKey);
    }

    private function parseEmployeeRows(Crawler $infoTable, Crawler $scheduleTable): array
    {
        $admissionRaw = trim($infoTable->filter('[data-bind="text: DataAdmissao"]')->text());

        $employeeData = [
            'name'          => trim($infoTable->filter('[data-bind="text: Nome"]')->text()),
            'position'      => trim($infoTable->filter('[data-bind="text: Cargo"]')->text()),
            'employeeCode'  => trim($infoTable->filter('[data-bind="text: Matricula"]')->text()),
            'department'    => trim($infoTable->filter('[data-bind="text: Estrutura"]')->text()),
            'cpf'           => trim($infoTable->filter('[data-bind="text: CPF"]')->text()),
            'admissionDate' => Carbon::createFromFormat('d/m/Y', $admissionRaw)->format('Y-m-d'),
        ];

        $rows = [];
        $scheduleTable->filter('.relatorioEspelhoPontoBodyRow')->each(function (Crawler $row) use ($employeeData, &$rows) {
            $rows = array_merge($rows, $this->parseSingleDayRow($row, $employeeData));
        });

        return $rows;
    }

    private function parseSingleDayRow(Crawler $row, array $employeeData): array
    {
        $dateText      = trim(explode(' ', $row->filter('[data-bind="text: Data"]')->text())[0]);
        $entryDate     = Carbon::createFromFormat('d/m/Y', $dateText);
        $referenceTime = trim($row->filter('[data-bind="text: Horario"]')->text());

        if (Str::contains($referenceTime, ['Feriado'])) {
            return [];
        }

        try {
            $entryTimes = trim($row->filter('[data-bind="html: Apontamentos"]')->text());
        } catch (\Exception $e) {
            $entryTimes = 'Não registrado';
        }

        $cells       = $row->filter('td');
        $observation = trim($cells->eq(self::COL_OBSERVATION)->text());

        $debitText  = trim($cells->eq(self::COL_DEBIT)->text()) !== ''
            ? trim($cells->eq(self::COL_DEBIT)->text())
            : explode(' ', $observation)[0];

        $creditText = trim($cells->eq(self::COL_CREDIT)->text()) !== ''
            ? trim($cells->eq(self::COL_CREDIT)->text())
            : trim($cells->eq(self::COL_CREDIT_FALLBACK)->text());

        $debitMinutes  = $this->timeToMinutes($debitText);
        $creditMinutes = $this->timeToMinutes($creditText);

        $baseRow = [
            'employee_code'  => $employeeData['employeeCode'],
            'employee_name'  => $employeeData['name'],
            'position'       => $employeeData['position'],
            'department'     => $employeeData['department'],
            'cpf'            => $employeeData['cpf'],
            'admission_date' => $employeeData['admissionDate'],
            'entry_date'     => $entryDate,
            'reference_time' => $referenceTime,
            'entry_times'    => $entryTimes,
            'due_date'       => (clone $entryDate)->addDays(self::MAX_VALID_DAYS),
        ];

        $result = [];

        // DSR não vira débito: descanso semanal remunerado não é hora devida.
        if ($debitMinutes > 0 && !Str::contains($observation, 'DSR')) {
            $result[] = array_merge($baseRow, [
                'type'           => self::TYPE_DEBIT,
                'amount_minutes' => $debitMinutes,
            ]);
        }

        if ($creditMinutes > 0) {
            $needsApproval = $creditMinutes > self::CREDIT_APPROVAL_THRESHOLD_MINUTES;
            // "reditar" e não "Creditar": a coluna aparece ora capitalizada,
            // ora não, e a busca por substring cobre os dois casos.
            $approved = Str::contains(trim($cells->eq(self::COL_APPROVAL)->text()), 'reditar');

            if (!$needsApproval || $approved) {
                $result[] = array_merge($baseRow, [
                    'type'           => self::TYPE_CREDIT,
                    'amount_minutes' => $creditMinutes,
                ]);
            }
        }

        // Dia sem crédito nem débito não gera lançamento. Antes gerava um
        // registro de tipo 'Padrão' com zero minuto — uma linha por dia
        // trabalhado, por funcionário, que não entrava em conta nenhuma e só
        // engordava a tabela.
        return $result;
    }

    private function timeToMinutes($timeStr): int
    {
        $parts = explode(':', (string) $timeStr);
        if (count($parts) !== 2) {
            return 0;
        }

        return (intval($parts[0]) * 60) + intval($parts[1]);
    }

    // ------------------------------------------------------------------
    // Cadastro
    // ------------------------------------------------------------------

    private function upsertEmployee(array $row, array &$cache): Employee
    {
        $code = $row['employee_code'];

        if (!isset($cache[$code])) {
            $cache[$code] = Employee::updateOrCreate(
                ['employee_code' => $code],
                [
                    'name'           => $row['employee_name'],
                    'position'       => $row['position'],
                    'department'     => $row['department'],
                    'sector_id'      => $this->resolveSectorId($row['department']),
                    'cpf'            => $row['cpf'],
                    'admission_date' => $row['admission_date'],
                ]
            );
        }

        return $cache[$code];
    }

    /**
     * Encontra (ou cria) o setor correspondente à "Estrutura" do espelho de
     * ponto. Assim cada departamento que aparece na importação passa a existir
     * como setor de verdade, pronto para receber um coordenador na tela de
     * Setores.
     *
     * A comparação ignora maiúsculas e espaços nas bordas, como
     * User::belongsToSectorNamed() — senão "Manutenção" e "manutenção "
     * virariam dois setores, e o coordenador de um não enxergaria a equipe do
     * outro.
     */
    private function resolveSectorId(?string $department): ?int
    {
        $name = trim((string) $department);
        if ($name === '') {
            return null;
        }

        $key = mb_strtolower($name);

        if (!isset($this->sectorCache[$key])) {
            $sector = Sector::whereRaw('LOWER(TRIM(name)) = ?', [$key])->first()
                ?? Sector::create([
                    'name'        => $name,
                    'description' => 'Departamento importado do espelho de ponto (Banco de Horas).',
                ]);

            $this->sectorCache[$key] = $sector->id;
        }

        return $this->sectorCache[$key];
    }

    // ------------------------------------------------------------------
    // Saldo
    // ------------------------------------------------------------------

    /**
     * Refaz do zero a compensação de crédito × débito.
     *
     * Destrutivo por natureza: apaga os ajustes do funcionário, devolve cada
     * lançamento ao valor bruto e recompensa tudo em ordem de data. É o que dá
     * um resultado independente da ordem em que os arquivos foram importados.
     *
     * @param int|int[]|null $employeeIds Um id, vários, ou null para todos.
     */
    public function recalculateAllBalances($employeeIds = null): void
    {
        $ids = $employeeIds === null
            ? Employee::pluck('id')->all()
            : (array) $employeeIds;

        foreach ($ids as $employeeId) {
            $this->recalculateEmployeeBalance((int) $employeeId);
        }
    }

    private function recalculateEmployeeBalance(int $employeeId): void
    {
        $entryIds = TimeEntry::where('employee_id', $employeeId)->pluck('id');
        if ($entryIds->isEmpty()) {
            return;
        }

        // Apaga por funcionário, e não só pelos lançamentos ativos: ajuste que
        // apontava para um lançamento baixado sobrevivia à limpeza antiga e
        // ficava órfão, exibindo saldo de uma conta que não existe mais.
        TimeAdjustment::whereIn('entry_time_to_adjust_id', $entryIds)
            ->orWhereIn('entry_time_adjusted_id', $entryIds)
            ->delete();

        TimeEntry::where('employee_id', $employeeId)
            ->update(['balance_minutes' => DB::raw('amount_minutes')]);

        $activeEntries = TimeEntry::where('employee_id', $employeeId)
            ->where('written_off', false)
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($activeEntries as $entry) {
            $this->updateBalance($employeeId, $entry);
        }
    }

    /**
     * Abate este lançamento contra os do tipo oposto, do mais antigo para o
     * mais novo (FIFO), dentro da janela de validade de 180 dias.
     */
    private function updateBalance(int $employeeId, TimeEntry $currentEntry): void
    {
        if ($currentEntry->written_off) {
            return;
        }

        // Relê o saldo antes de compensar. O laço que chama este método
        // carregou todos os lançamentos de uma vez, e um lançamento pode ter
        // sido zerado por outro no meio do caminho — sem esta releitura o
        // objeto em memória ainda anuncia o saldo cheio e a mesma hora era
        // compensada duas vezes, uma de cada lado.
        $currentEntry->refresh();

        $balance    = $currentEntry->balance_minutes;
        $minDate    = Carbon::parse($currentEntry->entry_date)->subDays(self::MAX_VALID_DAYS);
        $targetType = $currentEntry->type === self::TYPE_CREDIT ? self::TYPE_DEBIT : self::TYPE_CREDIT;

        $compensables = TimeEntry::where('employee_id', $employeeId)
            ->where('type', $targetType)
            ->where('balance_minutes', '>', 0)
            ->where('written_off', false)
            ->where('entry_date', '>=', $minDate->toDateString())
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($compensables as $target) {
            if ($balance <= 0) {
                break;
            }

            $targetBalanceBefore  = $target->balance_minutes;
            $currentBalanceBefore = $balance;

            $deduction         = min($balance, $targetBalanceBefore);
            $balance          -= $deduction;
            $targetBalanceAfter = $targetBalanceBefore - $deduction;

            $target->update(['balance_minutes' => $targetBalanceAfter]);

            // Um ajuste de cada lado: a ficha de qualquer um dos dois
            // lançamentos precisa saber contra quem ele foi compensado.
            $this->createAdjustment($currentEntry->id, $target->id, $deduction, $currentBalanceBefore, $balance);
            $this->createAdjustment($target->id, $currentEntry->id, $deduction, $targetBalanceBefore, $targetBalanceAfter);
        }

        $currentEntry->update(['balance_minutes' => $balance]);
    }

    private function createAdjustment($currentId, $targetId, $amount, $before, $after): void
    {
        TimeAdjustment::create([
            'entry_time_to_adjust_id'   => $currentId,
            'entry_time_adjusted_id'    => $targetId,
            'amount_minutes'            => $amount,
            'before_adjustment_minutes' => $before,
            'after_adjustment_minutes'  => $after,
            'reason'                    => '',
        ]);
    }
}
