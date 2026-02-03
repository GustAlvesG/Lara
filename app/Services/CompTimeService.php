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
        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        $crawler = new Crawler($content);
        $tables = $crawler->filter('table');
        $totalTables = $tables->count();

        DB::beginTransaction();

        try {
            for ($i = 0; $i < $totalTables; $i += 4) {
                if ($i + 1 >= $totalTables) break;


                $tabelaInfo = $tables->eq($i);
                $tabelaHorario = $tables->eq($i + 1);

                $tabelaInfo = $tabelaInfo->filter('[data-bind="with: InfoFuncionario"]');

                // dd($tabelaInfo->html(), $tabelaHorario->html());

                $this->processEmployeeData($tabelaInfo, $tabelaHorario);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getStructures()
    {
        $employees = Employee::all();

        $structures = $employees->pluck('department')->unique()->values()->all();

        return $structures;
    }

    public function filterEmployees(array $filters)
    {
        $query = Employee::query();

        if (!empty($filters['structure'])) {
            $query->where('department', $filters['structure']);
        }

        if (!empty($filters['employee_name'])) {
            $query->where('name', 'like', '%' . $filters['employee_name'] . '%');
        }

        if (!empty($filters['employee_code'])) {
            $query->where('employee_code', $filters['employee_code']);
        }

        // Additional filters for period can be implemented here if needed

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


        //Array with employee and their time entries
        $response = [];
        foreach ($employees as $employee) {
            // Clone the query to avoid modifying the original instance for the next iteration
            $entries = (clone $query)->where('employee_id', $employee->id)->get();
            if ($entries->isEmpty()) {
                continue;
            }
            $response[] = [
                'employee' => $employee,
                'entries' => $entries
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

        $dashboard['total_credit_minutes'] = $timeEntries->where('type', 'CREDIT')->sum('amount_minutes');
        $dashboard['total_debit_minutes'] = $timeEntries->where('type', 'DEBIT')->sum('amount_minutes');
        $dashboard['net_balance_minutes'] = $dashboard['total_credit_minutes'] - $dashboard['total_debit_minutes'];
        $dashboard['next_expiring_entries'] = $timeEntries->filter(function ($entry) {
            return $entry->balance_minutes > 0;
        })->values()->sortBy('due_date')->take(3);

        foreach($timeEntries as $entry) {
            $adjustments = TimeAdjustment::
                 where('entry_time_to_adjust_id', $entry->id)
                // ->orWhere('entry_time_adjusted_id', $entry->id)
                ->orderBy('before_adjustment_minutes', 'desc')
                ->get();
            foreach($adjustments as $adj) {
                $adj->adjustment_date = TimeEntry::where('id', $adj->entry_time_adjusted_id)->value('entry_date');
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

    public function recalculateAllBalances()
    {
        $employees = Employee::all();

        foreach ($employees as $employee) {
            $timeEntries = TimeEntry::where('employee_id', $employee->id)
                ->orderBy('entry_date', 'asc')
                ->get();

            //Reset Adjustments
            TimeAdjustment::whereIn('entry_time_to_adjust_id', $timeEntries->pluck('id'))->delete();
            TimeAdjustment::whereIn('entry_time_adjusted_id', $timeEntries->pluck('id'))->delete();

            // Reset all balances
            foreach ($timeEntries as $entry) {
                $entry->balance_minutes = $entry->amount_minutes;
                $entry->save();
            }

            // Recalculate balances
            foreach ($timeEntries as $entry) {
                $this->updateBalance($employee, $entry);
            }
        }
    }

    private function processEmployeeData(Crawler $infoTable, Crawler $scheduleTable)
    {

        // De-para dos campos extraídos do HTML para as novas colunas
        $name = trim($infoTable->filter('[data-bind="text: Nome"]')->text());
        $position = trim($infoTable->filter('[data-bind="text: Cargo"]')->text());
        $employeeCode = trim($infoTable->filter('[data-bind="text: Matricula"]')->text());
        $department = trim($infoTable->filter('[data-bind="text: Estrutura"]')->text());
        $cpf = trim($infoTable->filter('[data-bind="text: CPF"]')->text());
        $dummyAdmission = trim($infoTable->filter('[data-bind="text: DataAdmissao"]')->text());

        //Convert dummyAdmission for DD/MM/YYYY to YYYY-MM-DD
        $dummyAdmission = Carbon::createFromFormat('d/m/Y', $dummyAdmission)->format('Y-m-d');

        // AVISO: A migration exige CPF e Admission Date, mas o HTML original não tinha isso.
        // Estamos inserindo valores "dummy" para não quebrar a importação.
        // O ideal é ajustar o crawler se essa informação existir no HTML ou mudar a migration para nullable.


        $employee = Employee::updateOrCreate(
            ['employee_code' => $employeeCode],
            [
                'name' => $name, 
                'position' => $position, 
                'department' => $department,
                'cpf' => $cpf,
                'admission_date' => $dummyAdmission
            ]
        );

        $rows = $scheduleTable->filter('.relatorioEspelhoPontoBodyRow');

        $rows->each(function (Crawler $row) use ($employee) {
            $this->processSingleDay($row, $employee);
        });
    }

    private function processSingleDay(Crawler $row, Employee $employee)
    {
        $dataTexto = trim(explode(' ', $row->filter('[data-bind="text: Data"]')->text())[0]);
        $entryDate = Carbon::createFromFormat('d/m/Y', $dataTexto);

        $referenceTime = trim($row->filter('[data-bind="text: Horario"]')->text());
        
        // $ignoreTerms = ["Compensado", "Descanso Semanal", "Folga", "Afastamento", "Férias", "Feriado"];
        $ignoreTerms = ["Feriado"];
        if (Str::contains($referenceTime, $ignoreTerms)) {
            return;
        }



        try {
            $entryTimes = trim($row->filter('[data-bind="html: Apontamentos"]')->text());
        } catch (\Exception $e) {
            $entryTimes = 'Não registrado';
        }

        $allTd = $row->filter('td');
        
        // Índices baseados na estrutura do HTML original
        $debbuugg = '';
        foreach($allTd as $index => $td) {
            $debbuugg .= "Index $index: " . trim($td->textContent) . "\n";
        }

        $debitoStr = trim($allTd->eq(9)->text()) !== '' ? trim($allTd->eq(9)->text()) : explode(" ",trim($allTd->eq(8)->text()))[0];
        $creditoStr = trim($allTd->eq(10)->text()) !== '' ? trim($allTd->eq(10)->text()) : trim($allTd->eq(4)->text());

        $debitoMinutes = $this->timeToMinutes($debitoStr);
        $creditoMinutes = $this->timeToMinutes($creditoStr);
        

        if ($debitoMinutes > 0) {
            if (!Str::contains(trim($allTd->eq(8)->text()), "DSR")){
                $this->insertTimeEntry($employee, $entryDate, $referenceTime, $entryTimes, 'DEBIT', $debitoMinutes);
            }
        }

        if ($creditoMinutes > 0) {
            if ($creditoMinutes > 120) {
                if (!Str::contains(trim($allTd->eq(11)->text()), "reditar")) { //"Creditar"
                    // Se o crédito for maior que 120 minutos e o campo específico estiver vazio,
                    // assumimos que é um erro de extração e zeramos o crédito.
                    $creditoMinutes = 0;
                }
            }
            $this->insertTimeEntry($employee, $entryDate, $referenceTime, $entryTimes, 'CREDIT', $creditoMinutes);
        }

        if ($creditoMinutes === 0 && $debitoMinutes === 0) {
            // Nenhum débito ou crédito, não faz nada
            $this->insertTimeEntry($employee, $entryDate, $referenceTime, $entryTimes, 'Padrão', $creditoMinutes);
        }
    }

    private function insertTimeEntry(Employee $employee, Carbon $date, $ref, $registros, $type, $minutes)
    {
        $dueDate = (clone $date)->addDays(self::MAX_VALID_DAYS);

        //Check if entry already exists
        $existingEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereDate('entry_date', $date)
            ->where('type', $type)
            ->first();

        if (!$existingEntry) {
            // Update existing entry
            $timeEntry = TimeEntry::create([
                'employee_id' => $employee->id,
                'entry_date' => $date,
                'reference_time' => $ref,
                'entry_times' => $registros,
                'type' => $type,
                'amount_minutes' => $minutes,
                'balance_minutes' => $minutes, // Saldo inicial igual ao total
                'due_date' => $dueDate
            ]);
        } else {
            // Update existing entry
            
            $existingEntry->amount_minutes = $minutes;
            $existingEntry->balance_minutes = $minutes;
            $existingEntry->save();
            $timeEntry = $existingEntry;
        }

        $this->updateBalance($employee, $timeEntry);
    }

    private function updateBalance(Employee $employee, TimeEntry $currentEntry)
    {
        $balance = $currentEntry->balance_minutes;
        $referenceDate = Carbon::parse($currentEntry->entry_date);
        $minDate = (clone $referenceDate)->subDays(self::MAX_VALID_DAYS);

        $targetType = ($currentEntry->type === 'CREDIT') ? 'DEBIT' : 'CREDIT';

        $compensables = TimeEntry::where('employee_id', $employee->id)
            ->where('type', $targetType)
            ->where('balance_minutes', '>', 0)
            ->where('entry_date', '>=', $minDate)
            ->orderBy('entry_date', 'asc')
            ->get();

        foreach ($compensables as $targetRow) {
            if ($balance <= 0) break;

            $auxRowBalance = $targetRow->balance_minutes;
            $originalTargetBalance = $auxRowBalance; // Guarda o valor antes da operação
            $originalCurrentBalance = $balance;      // Guarda o valor antes da operação

            if ($balance >= $auxRowBalance) {
                // Cenário 1: O registro ATUAL cobre TOTALMENTE o ANTIGO
                // Ex: Atual (Débito 60m) vs Antigo (Crédito 20m)
                // Deduz 20m do atual. Antigo zera.
                
                $deduction = $auxRowBalance;
                $balance -= $auxRowBalance;
                
                $targetRow->update(['balance_minutes' => 0]);

                // Ajuste 1: No registro ATUAL (Devedor) - Dizendo que usou saldo do Antigo
                $this->createAdjustment(
                    $currentEntry->id,      // Quem está sendo ajustado (Atual)
                    $targetRow->id,         // A fonte do ajuste (Antigo)
                    $deduction,
                    $originalCurrentBalance, // Antes
                    $balance,                // Depois (reduzido)
                    ""
                );

                // Ajuste 2: No registro ANTIGO (Pagador) - Dizendo que pagou o Atual
                $this->createAdjustment(
                    $targetRow->id,         // Quem está sendo ajustado (Antigo)
                    $currentEntry->id,      // O destino do ajuste (Atual)
                    $deduction,
                    $originalTargetBalance,  // Antes
                    0,                       // Depois (Zerou)
                    ""
                );

            } else {
                // Cenário 2: O registro ATUAL cobre PARCIALMENTE o ANTIGO
                // Ex: Atual (Débito 10m) vs Antigo (Crédito 50m)
                // Deduz 10m do antigo. Atual zera.

                $deduction = $balance;
                $newRowBalance = $auxRowBalance - $balance;
                
                $targetRow->update(['balance_minutes' => $newRowBalance]);

                // Ajuste 1: No registro ATUAL (Devedor) - Zerou usando parte do Antigo
                $this->createAdjustment(
                    $currentEntry->id,
                    $targetRow->id,
                    $deduction,
                    $originalCurrentBalance,
                    0, // Zerou
                    ""
                );

                // Ajuste 2: No registro ANTIGO (Pagador) - Reduziu pagando o Atual
                $this->createAdjustment(
                    $targetRow->id,
                    $currentEntry->id,
                    $deduction,
                    $originalTargetBalance,
                    $newRowBalance, // Sobrou saldo
                    ""
                );

                $balance = 0;
            }
        }

        $currentEntry->update(['balance_minutes' => $balance]);
    }

    private function createAdjustment($currentId, $targetId, $amount, $before, $after, $reason)
    {
        TimeAdjustment::create([
            'entry_time_to_adjust_id' => $currentId,
            'entry_time_adjusted_id' => $targetId,
            'amount_minutes' => $amount,
            'before_adjustment_minutes' => $before,
            'after_adjustment_minutes' => $after,
            'reason' => $reason
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
