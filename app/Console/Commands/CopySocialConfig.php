<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Copia a configuração dos espaços sociais de um banco para outro —
 * tipicamente da homologação (`lara_hml`) para a produção (`lara`), para não
 * recadastrar tudo à mão.
 *
 * Copia: grupos sociais (com os dias da semana), os espaços deles, os
 * conflitos entre espaços e o catálogo de bebidas e mobiliário. Não copia
 * reservas, convidados nem pagamentos: são dados de teste da homologação.
 *
 * - **Por nome, sem duplicar.** Grupo social casa pelo nome; espaço, pelo nome
 *   dentro do grupo; item do catálogo, pelo nome e categoria. O que já existe
 *   no destino é atualizado, o que não existe é criado. Pode rodar de novo.
 * - **Nunca apaga** nada no destino. Excluído (soft delete) na origem não é
 *   copiado.
 * - Conflito com espaço de fora (uma quadra) casa pelo nome do espaço e do
 *   grupo; se não achar no destino, avisa e pula.
 * - Imagens vão como estão (nome do arquivo ou link). O arquivo precisa estar
 *   em `public/images` do servidor de destino — o comando avisa o que falta.
 * - `--dry-run` só lê os dois bancos e mostra o que faria: não escreve nada
 *   no destino (nem transação desfeita, que no MySQL ainda avançaria os ids).
 *
 * `--from`/`--to` são nomes de banco no mesmo servidor e usuário da conexão
 * `mysql` (ou o nome de uma conexão já configurada).
 */
class CopySocialConfig extends Command
{
    protected $signature = 'social:copy-config
        {--from=lara_hml : Banco (ou conexão) de origem}
        {--to=lara : Banco (ou conexão) de destino}
        {--dry-run : Só mostra o que faria; não grava nada}
        {--force : Não pergunta antes de gravar}';

    protected $description = 'Copia grupos, espaços e catálogo dos espaços sociais de um banco para outro (ex.: homologação → produção).';

    /** Colunas que nunca vão: identidade e carimbos do registro. */
    private const SKIP = ['id', 'created_at', 'updated_at', 'deleted_at'];

    private Connection $from;

    private Connection $to;

    private bool $dryRun = false;

    /** Ids provisórios (negativos) dos registros que a simulação criaria. */
    private int $fakeId = 0;

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $report = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        if ($this->option('from') === $this->option('to')) {
            $this->error('Origem e destino são o mesmo banco.');

            return self::FAILURE;
        }

        $this->from = $this->connection((string) $this->option('from'));
        $this->to = $this->connection((string) $this->option('to'));
        $dryRun = $this->dryRun = (bool) $this->option('dry-run');

        $this->line(sprintf('Origem: <info>%s</info>  →  Destino: <info>%s</info>%s',
            $this->from->getDatabaseName(), $this->to->getDatabaseName(), $dryRun ? '  <comment>(simulação)</comment>' : ''));

        if (! $this->assertSchema()) {
            return self::FAILURE;
        }

        if (! $dryRun && ! $this->option('force')
            && ! $this->confirm("Gravar no banco {$this->to->getDatabaseName()}?", false)) {
            $this->warn('Nada gravado.');

            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->to->beginTransaction();
        }

        try {
            $groupMap = $this->copyGroups();
            $placeMap = $this->copyPlaces($groupMap);
            $this->copyConflicts($placeMap);
            $this->copyCatalog();
            $this->checkImages();
        } catch (\Throwable $e) {
            if (! $dryRun) {
                $this->to->rollBack();
            }
            $this->error('Falhou, nada gravado: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->to->commit();
        }

        $this->table(['Tipo', 'Nome', 'Ação'], $this->report);
        foreach ($this->warnings as $warning) {
            $this->warn($warning);
        }
        $this->info($dryRun ? 'Simulação: nada foi gravado. Rode sem --dry-run para valer.' : 'Configuração copiada.');

        return self::SUCCESS;
    }

    /** Conexão já configurada com esse nome, ou a `mysql` apontando para esse banco. */
    private function connection(string $name): Connection
    {
        if (! config("database.connections.{$name}")) {
            config(["database.connections.{$name}" => array_merge(config('database.connections.mysql'), ['database' => $name])]);
        }

        return DB::connection($name);
    }

    private function assertSchema(): bool
    {
        foreach (['place_groups', 'places', 'place_conflicts', 'social_beverages'] as $table) {
            foreach (['from', 'to'] as $side) {
                if (! $this->{$side}->getSchemaBuilder()->hasTable($table)) {
                    $this->error("Tabela {$table} não existe em {$this->{$side}->getDatabaseName()}: rode as migrations antes.");

                    return false;
                }
            }
        }

        // Os dias da semana vão por id: os dois lados precisam do mesmo cadastro.
        $fromDays = $this->from->table('weekdays')->orderBy('id')->pluck('name', 'id')->all();
        $toDays = $this->to->table('weekdays')->orderBy('id')->pluck('name', 'id')->all();
        if ($fromDays != $toDays) {
            $this->error('A tabela weekdays é diferente entre origem e destino; os dias dos grupos não podem ser copiados por id.');

            return false;
        }

        return true;
    }

    /** @return array<int, int> id do grupo na origem => id no destino */
    private function copyGroups(): array
    {
        $map = [];
        $groups = $this->from->table('place_groups')->where('category', 'social')->whereNull('deleted_at')->orderBy('id')->get();

        foreach ($groups as $group) {
            $data = $this->columns('place_groups', $group);
            $existing = $this->to->table('place_groups')->where('category', 'social')->where('name', $group->name)->whereNull('deleted_at')->first();

            $map[$group->id] = $this->upsert('place_groups', $existing, $data, 'Grupo', $group->name);

            $days = $this->from->table('week_days_place_group')->where('place_group_id', $group->id)->pluck('weekday_id')->unique()->values();
            if ($this->dryRun) {
                continue;
            }
            $this->to->table('week_days_place_group')->where('place_group_id', $map[$group->id])->delete();
            $now = Carbon::now();
            $this->to->table('week_days_place_group')->insert($days->map(fn ($day) => [
                'weekday_id' => $day, 'place_group_id' => $map[$group->id], 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        }

        return $map;
    }

    /** @return array<int, int> id do espaço na origem => id no destino */
    private function copyPlaces(array $groupMap): array
    {
        $map = [];
        $places = $this->from->table('places')->whereIn('place_group_id', array_keys($groupMap))->whereNull('deleted_at')->orderBy('id')->get();

        foreach ($places as $place) {
            $data = $this->columns('places', $place);
            $data['place_group_id'] = $groupMap[$place->place_group_id];
            // Espaço social não tem contator (a reserva do dia inteiro deixaria a luz acesa).
            $data['contactor_id'] = null;
            $data['self_service_lighting'] = 0;

            $existing = $this->to->table('places')->where('place_group_id', $data['place_group_id'])->where('name', $place->name)->whereNull('deleted_at')->first();

            $map[$place->id] = $this->upsert('places', $existing, $data, 'Espaço', $place->name);
        }

        return $map;
    }

    /** Pares em que ao menos um lado é espaço social copiado; o outro casa por id copiado ou por nome. */
    private function copyConflicts(array $placeMap): void
    {
        $pairs = $this->from->table('place_conflicts')
            ->whereIn('place_id', array_keys($placeMap))
            ->orWhereIn('conflicting_place_id', array_keys($placeMap))
            ->get();

        foreach ($pairs as $pair) {
            $a = $this->targetPlaceId((int) $pair->place_id, $placeMap);
            $b = $this->targetPlaceId((int) $pair->conflicting_place_id, $placeMap);

            if (! $a || ! $b) {
                continue;
            }

            $exists = $this->to->table('place_conflicts')
                ->where(fn ($q) => $q->where('place_id', $a)->where('conflicting_place_id', $b))
                ->orWhere(fn ($q) => $q->where('place_id', $b)->where('conflicting_place_id', $a))
                ->exists();

            if (! $exists && ! $this->dryRun) {
                $now = Carbon::now();
                $this->to->table('place_conflicts')->insert(['place_id' => $a, 'conflicting_place_id' => $b, 'created_at' => $now, 'updated_at' => $now]);
            }

            $this->report[] = ['Conflito', $this->placeName($pair->place_id) . ' × ' . $this->placeName($pair->conflicting_place_id), $exists ? 'já existe' : ($this->dryRun ? 'seria criado' : 'criado')];
        }
    }

    private function targetPlaceId(int $sourceId, array $placeMap): ?int
    {
        if (isset($placeMap[$sourceId])) {
            return $placeMap[$sourceId];
        }

        // Espaço de fora do social (uma quadra): o mesmo nome, no grupo de mesmo nome.
        $source = $this->from->table('places')->join('place_groups', 'places.place_group_id', '=', 'place_groups.id')
            ->where('places.id', $sourceId)->first(['places.name', 'place_groups.name as group_name']);

        $target = $source ? $this->to->table('places')->join('place_groups', 'places.place_group_id', '=', 'place_groups.id')
            ->where('places.name', $source->name)->where('place_groups.name', $source->group_name)
            ->whereNull('places.deleted_at')->value('places.id') : null;

        if (! $target) {
            $this->warnings[] = 'Conflito pulado: "' . ($source->name ?? "#{$sourceId}") . '" não existe no destino (cadastre o vínculo pela tela do espaço).';
        }

        return $target ? (int) $target : null;
    }

    private function placeName($id): string
    {
        return (string) ($this->from->table('places')->where('id', $id)->value('name') ?? "#{$id}");
    }

    private function copyCatalog(): void
    {
        $items = $this->from->table('social_beverages')->whereNull('deleted_at')->orderBy('id')->get();

        foreach ($items as $item) {
            $existing = $this->to->table('social_beverages')->where('name', $item->name)->where('category', $item->category)->whereNull('deleted_at')->first();
            $this->upsert('social_beverages', $existing, $this->columns('social_beverages', $item), $item->category === 'mobiliario' ? 'Mobiliário' : 'Bebida', $item->name);
        }
    }

    /** Imagens que são arquivo precisam existir em public/images deste servidor. */
    private function checkImages(): void
    {
        $files = collect()
            // Lidas da origem: são os mesmos valores que vão para o destino (e assim vale na simulação).
            ->merge($this->from->table('place_groups')->where('category', 'social')->whereNull('deleted_at')->get(['image_vertical', 'image_horizontal'])->flatMap(fn ($g) => [$g->image_vertical, $g->image_horizontal]))
            ->merge($this->from->table('places')->whereNull('deleted_at')->whereIn('place_group_id', $this->from->table('place_groups')->where('category', 'social')->whereNull('deleted_at')->pluck('id'))->pluck('image'))
            ->filter(fn ($value) => filled($value) && ! preg_match('#^https?://#i', $value))
            ->unique();

        foreach ($files as $file) {
            if (! is_file(public_path('images/' . $file))) {
                $this->warnings[] = "Imagem não encontrada neste servidor: public/images/{$file} (copie o arquivo ou troque por link na tela).";
            }
        }
    }

    /** Colunas da origem que existem no destino, sem identidade nem carimbos. */
    private function columns(string $table, object $row): array
    {
        $target = $this->to->getSchemaBuilder()->getColumnListing($table);

        return collect((array) $row)->only($target)->except(self::SKIP)->all();
    }

    private function upsert(string $table, ?object $existing, array $data, string $type, string $name): int
    {
        $now = Carbon::now();

        if ($existing) {
            if (! $this->dryRun) {
                $this->to->table($table)->where('id', $existing->id)->update($data + ['updated_at' => $now]);
            }
            $this->report[] = [$type, $name, $this->dryRun ? 'seria atualizado' : 'atualizado'];

            return (int) $existing->id;
        }

        if ($this->dryRun) {
            $this->report[] = [$type, $name, 'seria criado'];

            return --$this->fakeId;
        }

        $id = (int) $this->to->table($table)->insertGetId($data + ['created_at' => $now, 'updated_at' => $now]);
        $this->report[] = [$type, $name, 'criado'];

        return $id;
    }
}
