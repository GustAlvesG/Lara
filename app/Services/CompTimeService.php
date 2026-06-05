<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\TimeAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;


class CompTimeService
{
    const MAX_VALID_DAYS = 180;

    public function importFile(string $filePath)
    {
        $rows = $this->parseFileRows($filePath);

        DB::beginTransaction();
        try {
            $employeeCache = [];
            foreach ($rows as $row) {
                $employee = $this->upsertEmployee($row, $employeeCache);
                $this->insertTimeEntry(
                    $employee,
                    $row['entry_date'],
                    $row['reference_time'],
                    $row['entry_times'],
                    $row['type'],
                    $row['amount_minutes']
                );
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Versão otimizada: 2 queries totais ao banco (vs. N queries por linha na versão original).
     * Batch de employees + batch de TimeEntries por range de datas.
     */
    public function detectDuplicatesFast(string $filePath): array
    {
        $rows = $this->parseFileRows($filePath);

        if (empty($rows)) {
            return ['new_entries' => [], 'duplicate_entries' => []];
        }

        // Query 1: busca todos os employees do arquivo de uma vez
        $uniqueCodes = array_unique(array_column($rows, 'employee_code'));
        $employees = Employee::whereIn('employee_code', $uniqueCodes)
            ->get()
            ->keyBy('employee_code');

        $employeeIds = $employees->pluck('id')->filter()->values()->toArray();

        // Query 2: busca todas as TimeEntries relevantes de uma vez (range de datas do arquivo)
        $existingIndex = [];
        if (!empty($employeeIds)) {
            $dates   = array_map(fn($r) => $r['entry_date']->format('Y-m-d'), $rows);
            $minDate = min($dates);
            $maxDate = max($dates);

            TimeEntry::whereIn('employee_id', $employeeIds)
                ->whereBetween('entry_date', [$minDate, $maxDate])
                ->get()
                ->each(function ($entry) use (&$existingIndex) {
                    $key = $entry->employee_id
                        . '|' . Carbon::parse($entry->entry_date)->format('Y-m-d')
                        . '|' . $entry->type;
                    $existingIndex[$key] = $entry;
                });
        }

        $newEntries       = [];
        $duplicateEntries = [];

        foreach ($rows as $row) {
            $employee = $employees->get($row['employee_code']);

            if (!$employee) {
                // Funcionário ainda não existe no banco — é entrada nova
                $newEntries[] = $row;
                continue;
            }

            $key      = $employee->id . '|' . $row['entry_date']->format('Y-m-d') . '|' . $row['type'];
            $existing = $existingIndex[$key] ?? null;

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

    public function detectDuplicates(string $filePath): array
    {
        $rows = $this->parseFileRows($filePath);

        $newEntries = [];
        $duplicateEntries = [];

        $employeeCache = [];
        foreach ($rows as $row) {
            $code = $row['employee_code'];
            if (!isset($employeeCache[$code])) {
                $employeeCache[$code] = Employee::where('employee_code', $code)->first();
            }
            $employee = $employeeCache[$code];

            $existing = $employee
                ? TimeEntry::where('employee_id', $employee->id)
                    ->whereDate('entry_date', $row['entry_date'])
                    ->where('type', $row['type'])
                    ->first()
                : null;

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

    public function importWithDecisions(string $filePath, array $acceptedEntryIds): void
    {
        $rows = $this->parseFileRows($filePath);
        $acceptedIdsSet = array_flip(array_map('intval', $acceptedEntryIds));

        $employeesToRecalculate = [];

        DB::beginTransaction();
        try {
            $employeeCache = [];
            foreach ($rows as $row) {
                $employee = $this->upsertEmployee($row, $employeeCache);

                $existing = TimeEntry::where('employee_id', $employee->id)
                    ->whereDate('entry_date', $row['entry_date'])
                    ->where('type', $row['type'])
                    ->first();

                if (!$existing) {
                    $this->insertTimeEntry(
                        $employee,
                        $row['entry_date'],
                        $row['reference_time'],
                        $row['entry_times'],
                        $row['type'],
                        $row['amount_minutes']
                    );
                } elseif (isset($acceptedIdsSet[$existing->id])) {
                    $existing->amount_minutes = $row['amount_minutes'];
                    $existing->balance_minutes = $row['amount_minutes'];
                    $existing->save();
                    $employeesToRecalculate[$employee->id] = $employee;
                }
                // rejected duplicate: skip
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        foreach ($employeesToRecalculate as $employee) {
            $this->recalculateAllBalances($employee->id);
        }
    }

    public function getStructures(array $access = ['type' => 'all'])
    {
        $query = Employee::distinct()->orderBy('department');

        if ($access['type'] === 'departments') {
            $query->whereIn('department', $access['values']);
        } elseif ($access['type'] === 'employee_code') {
            $query->where('employee_code', $access['value']);
        }

        return $query->pluck('department')->filter()->values()->all();
    }

    public function filterEmployees(array $filters, array $access = ['type' => 'all'])
    {
        $query = Employee::query();

        if ($access['type'] === 'departments') {
            $query->whereIn('department', $access['values']);
        } elseif ($access['type'] === 'employee_code') {
            $query->where('employee_code', $access['value']);
        }

        if (!empty($filters['structure'])) {
            $query->where('department', $filters['structure']);
        }

        if (!empty($filters['employee_name'])) {
            $query->where('name', 'like', '%' . $filters['employee_name'] . '%');
        }

        if (!empty($filters['employee_code'])) {
            $query->where('employee_code', $filters['employee_code']);
        }

        return $query->get();
    }

    public function getTimeEntriesForEmployees($employees, $filters)
    {
        $employeeIds = $employees->pluck('id')->toArray();

        $query = TimeEntry::whereIn('employee_id', $employeeIds);

        if (!empty($filters['period_start'])) {
            $startDate = Carbon::createFromFormat('Y-m-d', $filters['period_start']);
            $query->where('entry_date', '>=', $startDate);
        }

        if (!empty($filters['period_end'])) {
            $endDate = Carbon::createFromFormat('Y-m-d', $filters['period_end']);
            $query->where('entry_date', '<=', $endDate);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'with_balance') {
                $query->where('balance_minutes', '>', 0);
            } elseif ($filters['status'] === 'without_balance') {
                $query->where('balance_minutes', 0);
            } else if ($filters['status'] === 'credit_only') {
                $query->where('type', 'CREDIT');
            } elseif ($filters['status'] === 'debit_only') {
                $query->where('type', 'DEBIT');
            }
        }

        $query->orderBy('employee_id', 'asc');

        $response = [];
        foreach ($employees as $employee) {
            $entries = (clone $query)->where('employee_id', $employee->id)->get();
            if ($entries->isEmpty()) {
                continue;
            }
            $response[] = [
                'employee' => $employee,
                'entries' => $entries,
                'summary' => $this->buildEmployeeSummary($entries),
            ];
        }

        return $response;
    }

    public function getTimeEntryDetails($employeeId, $periodStart = null, $periodEnd = null)
    {
        $query = TimeEntry::where('employee_id', $employeeId);

        if ($periodStart) {
            $startDate = Carbon::createFromFormat('Y-m-d', $periodStart);
            $query->where('entry_date', '>=', $startDate);
        }

        if ($periodEnd) {
            $endDate = Carbon::createFromFormat('Y-m-d', $periodEnd);
            $query->where('entry_date', '<=', $endDate);
        }

        $timeEntries = $query->orderBy('entry_date', 'asc')->get();

        $employee = Employee::find($employeeId);

        $dashboard = [];

        $active = $timeEntries->where('written_off', false);
        $dashboard['total_credit_minutes'] = $active->where('type', 'CREDIT')->sum('amount_minutes');
        $dashboard['total_debit_minutes'] = $active->where('type', 'DEBIT')->sum('amount_minutes');
        $dashboard['net_balance_minutes'] = $dashboard['total_credit_minutes'] - $dashboard['total_debit_minutes'];
        $dashboard['next_expiring_entries'] = $active->filter(function ($entry) {
            return $entry->balance_minutes > 0;
        })->values()->sortBy('due_date')->take(3);

        $entryIds = $timeEntries->pluck('id');
        $adjustmentsByEntry = TimeAdjustment::whereIn('entry_time_to_adjust_id', $entryIds)
            ->orderBy('before_adjustment_minutes', 'desc')
            ->get()
            ->groupBy('entry_time_to_adjust_id');

        $adjustedEntryDates = TimeEntry::whereIn('id',
            $adjustmentsByEntry->flatten()->pluck('entry_time_adjusted_id')->unique()
        )->pluck('entry_date', 'id');

        foreach ($timeEntries as $entry) {
            $adjustments = $adjustmentsByEntry->get($entry->id, collect());
            foreach ($adjustments as $adj) {
                $adj->adjustment_date = $adjustedEntryDates->get($adj->entry_time_adjusted_id);
            }
            $entry->adjustments = $adjustments;
        }

        return ['employee' => $employee, 'timeEntries' => $timeEntries, 'dashboard' => $dashboard];
    }

    public function showDayDetails($employeeId, $day)
    {
        $date = Carbon::createFromFormat('Y-m-d', $day);

        $timeEntries = TimeEntry::where('employee_id', $employeeId)
            ->whereDate('entry_date', $date)
            ->orderBy('entry_date', 'asc')
            ->get();

        foreach($timeEntries as $entry) {
            $adjustments = TimeAdjustment::where('entry_time_to_adjust_id', $entry->id)
                ->orWhere('entry_time_adjusted_id', $entry->id)
                ->orderBy('before_adjustment_minutes', 'asc')
                ->get();
            $entry->adjustments = $adjustments;
        }

        return $timeEntries;
    }

    public function recalculateAllBalances(?int $employeeId = null): void
    {
        $employees = $employeeId
            ? Employee::where('id', $employeeId)->get()
            : Employee::all();

        foreach ($employees as $employee) {
            $activeEntries = TimeEntry::where('employee_id', $employee->id)
                ->where('written_off', false)
                ->orderBy('entry_date', 'asc')
                ->get();

            TimeAdjustment::whereIn('entry_time_to_adjust_id', $activeEntries->pluck('id'))->delete();
            TimeAdjustment::whereIn('entry_time_adjusted_id', $activeEntries->pluck('id'))->delete();

            foreach ($activeEntries as $entry) {
                $entry->balance_minutes = $entry->amount_minutes;
                $entry->save();
            }

            foreach ($activeEntries as $entry) {
                $this->updateBalance($employee, $entry);
            }
        }
    }

    private function buildEmployeeSummary($entries): array
    {
        $active = $entries->where('written_off', false);
        $totalCredits = $active->where('type', 'CREDIT')->sum('balance_minutes');
        $totalDebits  = $active->where('type', 'DEBIT')->sum('balance_minutes');
        $netBalance   = $totalCredits - $totalDebits;
        $today = now()->startOfDay();

        $expiredWithBalance = $active->filter(fn($e) =>
            $e->balance_minutes > 0 &&
            $e->due_date &&
            Carbon::parse($e->due_date)->lt($today)
        );

        $nextExpiry = $active->filter(fn($e) =>
            $e->balance_minutes > 0 &&
            $e->due_date &&
            Carbon::parse($e->due_date)->gte($today)
        )->sortBy('due_date')->first();

        return [
            'total_credits_minutes'   => $totalCredits,
            'total_debits_minutes'    => $totalDebits,
            'net_balance_minutes'     => $netBalance,
            'expired_balance_minutes' => $expiredWithBalance->sum('balance_minutes'),
            'expired_count'           => $expiredWithBalance->count(),
            'written_off_count'       => $entries->where('written_off', true)->count(),
            'next_expiry_entry'       => $nextExpiry,
            'days_to_expiry'          => $nextExpiry
                ? (int) now()->diffInDays(Carbon::parse($nextExpiry->due_date), false)
                : null,
        ];
    }

    private function parseFileRows(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $crawler = new Crawler($content);
        $tables = $crawler->filter('table');
        $totalTables = $tables->count();

        $rows = [];
        for ($i = 0; $i < $totalTables; $i += 4) {
            if ($i + 1 >= $totalTables) break;

            $tabelaInfo = $tables->eq($i)->filter('[data-bind="with: InfoFuncionario"]');
            $tabelaHorario = $tables->eq($i + 1);

            $employeeRows = $this->parseEmployeeRows($tabelaInfo, $tabelaHorario);
            $rows = array_merge($rows, $employeeRows);
        }

        return $rows;
    }

    private function parseEmployeeRows(Crawler $infoTable, Crawler $scheduleTable): array
    {
        $name           = trim($infoTable->filter('[data-bind="text: Nome"]')->text());
        $position       = trim($infoTable->filter('[data-bind="text: Cargo"]')->text());
        $employeeCode   = trim($infoTable->filter('[data-bind="text: Matricula"]')->text());
        $department     = trim($infoTable->filter('[data-bind="text: Estrutura"]')->text());
        $cpf            = trim($infoTable->filter('[data-bind="text: CPF"]')->text());
        $admissionRaw   = trim($infoTable->filter('[data-bind="text: DataAdmissao"]')->text());
        $admissionDate  = Carbon::createFromFormat('d/m/Y', $admissionRaw)->format('Y-m-d');

        $employeeData = compact('name', 'position', 'employeeCode', 'department', 'cpf', 'admissionDate');

        $rows = [];
        $scheduleTable->filter('.relatorioEspelhoPontoBodyRow')->each(function (Crawler $row) use ($employeeData, &$rows) {
            $parsed = $this->parseSingleDayRow($row, $employeeData);
            $rows = array_merge($rows, $parsed);
        });

        return $rows;
    }

    private function parseSingleDayRow(Crawler $row, array $employeeData): array
    {
        $dataTexto    = trim(explode(' ', $row->filter('[data-bind="text: Data"]')->text())[0]);
        $entryDate    = Carbon::createFromFormat('d/m/Y', $dataTexto);
        $referenceTime = trim($row->filter('[data-bind="text: Horario"]')->text());

        $ignoreTerms = ["Feriado"];
        if (Str::contains($referenceTime, $ignoreTerms)) {
            return [];
        }

        try {
            $entryTimes = trim($row->filter('[data-bind="html: Apontamentos"]')->text());
        } catch (\Exception $e) {
            $entryTimes = 'Não registrado';
        }

        $allTd = $row->filter('td');

        $debitoStr  = trim($allTd->eq(9)->text()) !== '' ? trim($allTd->eq(9)->text()) : explode(" ", trim($allTd->eq(8)->text()))[0];
        $creditoStr = trim($allTd->eq(10)->text()) !== '' ? trim($allTd->eq(10)->text()) : trim($allTd->eq(4)->text());

        $debitoMinutes  = $this->timeToMinutes($debitoStr);
        $creditoMinutes = $this->timeToMinutes($creditoStr);
        $dueDate        = (clone $entryDate)->addDays(self::MAX_VALID_DAYS);

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
            'due_date'       => $dueDate,
        ];

        $result = [];

        if ($debitoMinutes > 0) {
            if (!Str::contains(trim($allTd->eq(8)->text()), "DSR")) {
                $result[] = array_merge($baseRow, ['type' => 'DEBIT', 'amount_minutes' => $debitoMinutes]);
            }
        }

        if ($creditoMinutes > 0) {
            if ($creditoMinutes > 120) {
                if (!Str::contains(trim($allTd->eq(11)->text()), "reditar")) {
                    $creditoMinutes = 0;
                }
            }
            $result[] = array_merge($baseRow, ['type' => 'CREDIT', 'amount_minutes' => $creditoMinutes]);
        }

        if ($creditoMinutes === 0 && $debitoMinutes === 0) {
            $result[] = array_merge($baseRow, ['type' => 'Padrão', 'amount_minutes' => 0]);
        }

        return $result;
    }

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
                    'cpf'            => $row['cpf'],
                    'admission_date' => $row['admission_date'],
                ]
            );
        }
        return $cache[$code];
    }

    private function insertTimeEntry(Employee $employee, Carbon $date, $ref, $registros, $type, $minutes)
    {
        $dueDate = (clone $date)->addDays(self::MAX_VALID_DAYS);

        $existingEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereDate('entry_date', $date)
            ->where('type', $type)
            ->first();

        if (!$existingEntry) {
            $timeEntry = TimeEntry::create([
                'employee_id'    => $employee->id,
                'entry_date'     => $date,
                'reference_time' => $ref,
                'entry_times'    => $registros,
                'type'           => $type,
                'amount_minutes' => $minutes,
                'balance_minutes' => $minutes,
                'due_date'       => $dueDate
            ]);
        } else {
            $existingEntry->amount_minutes  = $minutes;
            $existingEntry->balance_minutes = $minutes;
            $existingEntry->save();
            $timeEntry = $existingEntry;
        }

        $this->updateBalance($employee, $timeEntry);
    }

    private function updateBalance(Employee $employee, TimeEntry $currentEntry)
    {
        if ($currentEntry->written_off) return;

        $balance = $currentEntry->balance_minutes;
        $referenceDate = Carbon::parse($currentEntry->entry_date);
        $minDate = (clone $referenceDate)->subDays(self::MAX_VALID_DAYS);

        $targetType = ($currentEntry->type === 'CREDIT') ? 'DEBIT' : 'CREDIT';

        $compensables = TimeEntry::where('employee_id', $employee->id)
            ->where('type', $targetType)
            ->where('balance_minutes', '>', 0)
            ->where('written_off', false)
            ->where('entry_date', '>=', $minDate)
            ->orderBy('entry_date', 'asc')
            ->get();

        foreach ($compensables as $targetRow) {
            if ($balance <= 0) break;

            $auxRowBalance = $targetRow->balance_minutes;
            $originalTargetBalance  = $auxRowBalance;
            $originalCurrentBalance = $balance;

            if ($balance >= $auxRowBalance) {
                $deduction = $auxRowBalance;
                $balance -= $auxRowBalance;

                $targetRow->update(['balance_minutes' => 0]);

                $this->createAdjustment(
                    $currentEntry->id, $targetRow->id,
                    $deduction, $originalCurrentBalance, $balance, ""
                );
                $this->createAdjustment(
                    $targetRow->id, $currentEntry->id,
                    $deduction, $originalTargetBalance, 0, ""
                );
            } else {
                $deduction     = $balance;
                $newRowBalance = $auxRowBalance - $balance;

                $targetRow->update(['balance_minutes' => $newRowBalance]);

                $this->createAdjustment(
                    $currentEntry->id, $targetRow->id,
                    $deduction, $originalCurrentBalance, 0, ""
                );
                $this->createAdjustment(
                    $targetRow->id, $currentEntry->id,
                    $deduction, $originalTargetBalance, $newRowBalance, ""
                );

                $balance = 0;
            }
        }

        $currentEntry->update(['balance_minutes' => $balance]);
    }

    private function createAdjustment($currentId, $targetId, $amount, $before, $after, $reason)
    {
        TimeAdjustment::create([
            'entry_time_to_adjust_id'  => $currentId,
            'entry_time_adjusted_id'   => $targetId,
            'amount_minutes'           => $amount,
            'before_adjustment_minutes' => $before,
            'after_adjustment_minutes'  => $after,
            'reason'                   => $reason
        ]);
    }

    private function timeToMinutes($timeStr)
    {
        try {
            $parts = explode(':', $timeStr);
            if (count($parts) !== 2) return 0;
            return (intval($parts[0]) * 60) + intval($parts[1]);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
