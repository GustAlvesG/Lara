<?php

namespace App\Services\Ticket;

use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketSeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro dos mapas de lugar marcado no painel (`ingressos.gerenciar`):
 * criar (em branco ou copiando outro mapa, de qualquer evento), renomear,
 * apagar e gravar o desenho — lugares e áreas de orientação.
 *
 * Recusa de verdade só onde gravar estragaria o que foi vendido: lugar que já
 * teve ingresso não é apagado nem muda de rótulo ou categoria (o rótulo está
 * nos ingressos e no PDF) — desative; mapa usado por tipo ou com lugar
 * vendido não é apagado.
 */
class TicketMapService
{
    public const MAX_SEATS = 1000;
    public const MAX_AREAS = 100;

    /**
     * @param  array{name: string, width?: int, height?: int, sort_order?: int, background?: ?string}  $data
     */
    public function saveMap(TicketEvent $event, array $data, ?TicketMap $map = null, ?TicketMap $copyFrom = null): TicketMap
    {
        if ($map) {
            $width = (int) ($data['width'] ?? $map->width);
            $height = (int) ($data['height'] ?? $map->height);
            $outside = $map->seats()->where(fn ($q) => $q->where('x', '>', $width)->orWhere('y', '>', $height))->orderBy('label')->pluck('label');
            if ($outside->isNotEmpty()) {
                throw ValidationException::withMessages(['width' => 'Os lugares ' . $outside->take(10)->implode(', ') . ' ficariam fora do mapa: mova-os antes de diminuir.']);
            }
            $map->update($data);

            return $map;
        }

        return DB::transaction(function () use ($event, $data, $copyFrom) {
            if ($copyFrom) {
                $data += ['width' => $copyFrom->width, 'height' => $copyFrom->height];
                $data['background'] ??= $copyFrom->background;
                $data['areas'] = $copyFrom->areas;
            }

            $map = $event->maps()->create($data + ['sort_order' => $event->maps()->count() + 1]);

            if ($copyFrom) {
                foreach ($copyFrom->seats as $seat) {
                    // A mesa cativa vem junto (o Réveillon do ano que vem nasce com as
                    // cativas); a tela avisa para conferir.
                    $map->seats()->create($seat->only(['label', 'category', 'shape', 'x', 'y', 'w', 'h', 'places', 'active', 'captive_title', 'captive_name']));
                }
            }

            return $map;
        });
    }

    public function deleteMap(TicketMap $map): void
    {
        $type = $map->types()->first();
        if ($type) {
            throw ValidationException::withMessages(['map' => "O tipo {$type->name} vende os lugares deste mapa: apague o tipo ou tire o mapa dele antes."]);
        }

        if (TicketSeat::where('ticket_map_id', $map->id)->whereHas('tickets')->exists()) {
            throw ValidationException::withMessages(['map' => 'O mapa tem lugar com ingresso: não pode ser apagado.']);
        }

        $map->delete();
    }

    /**
     * Grava o desenho inteiro que o editor mandou: lugares (com `id` = os que
     * já existem; sem = novos; os que faltam são apagados) e áreas.
     *
     * @param  list<array<string, mixed>>  $seats
     * @param  list<array<string, mixed>>  $areas
     * @return array{created: int, updated: int, deleted: int}
     */
    public function saveLayout(TicketMap $map, array $seats, array $areas): array
    {
        if (count($seats) > self::MAX_SEATS) {
            throw ValidationException::withMessages(['layout' => 'O mapa aceita até ' . self::MAX_SEATS . ' lugares.']);
        }
        if (count($areas) > self::MAX_AREAS) {
            throw ValidationException::withMessages(['layout' => 'O mapa aceita até ' . self::MAX_AREAS . ' áreas.']);
        }

        $rows = array_map(fn ($seat) => $this->seatRow($map, (array) $seat), array_values($seats));
        $areas = array_map(fn ($area) => $this->areaRow($map, (array) $area), array_values($areas));

        $labels = [];
        foreach ($rows as $row) {
            $key = Str::lower($row['label']);
            if (isset($labels[$key])) {
                throw ValidationException::withMessages(['layout' => "O rótulo {$row['label']} aparece em dois lugares: cada lugar do mapa tem o seu."]);
            }
            $labels[$key] = true;
        }

        return DB::transaction(function () use ($map, $rows, $areas) {
            $existing = $map->seats()->withCount('tickets')->get()->keyBy('id');
            $kept = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            foreach ($kept as $id) {
                if (! $existing->has($id)) {
                    throw ValidationException::withMessages(['layout' => 'Um dos lugares não é mais deste mapa. Recarregue a tela e desenhe de novo.']);
                }
            }

            $removed = $existing->except($kept);
            $sold = $removed->filter(fn (TicketSeat $s) => $s->tickets_count > 0);
            if ($sold->isNotEmpty()) {
                throw ValidationException::withMessages(['layout' => 'Os lugares ' . $sold->pluck('label')->implode(', ') . ' já tiveram ingresso: desative em vez de apagar.']);
            }

            foreach ($rows as $row) {
                $seat = $row['id'] ? $existing[$row['id']] : null;
                if ($seat && $seat->tickets_count > 0 && ($seat->label !== $row['label'] || $seat->category !== $row['category'])) {
                    throw ValidationException::withMessages(['layout' => "O lugar {$seat->label} já teve ingresso: o rótulo e a categoria não mudam (estão nos ingressos). Desative e crie outro, se preciso."]);
                }
            }

            TicketSeat::whereIn('id', $removed->keys())->delete();

            // Rótulos trocados entre dois lugares não batem no índice único no meio do caminho.
            $renamed = collect($rows)->filter(fn ($row) => $row['id'] && $existing[$row['id']]->label !== $row['label']);
            foreach ($renamed as $row) {
                TicketSeat::whereKey($row['id'])->update(['label' => '~' . $row['id']]);
            }

            $created = $updated = 0;
            foreach ($rows as $row) {
                $id = $row['id'];
                unset($row['id']);
                if ($id) {
                    TicketSeat::whereKey($id)->update($row);
                    $updated++;
                } else {
                    $map->seats()->create($row);
                    $created++;
                }
            }

            $map->update(['areas' => $areas]);

            return ['created' => $created, 'updated' => $updated, 'deleted' => $removed->count()];
        });
    }

    /** @return array<string, mixed> */
    private function seatRow(TicketMap $map, array $seat): array
    {
        $label = trim(preg_replace('/\s+/', ' ', (string) ($seat['label'] ?? '')));
        if ($label === '' || mb_strlen($label) > 20 || str_starts_with($label, '~')) {
            throw ValidationException::withMessages(['layout' => 'Todo lugar precisa de um rótulo de até 20 caracteres (ex.: M12).']);
        }

        $category = trim((string) ($seat['category'] ?? ''));
        $shape = (string) ($seat['shape'] ?? TicketSeat::SHAPE_CIRCLE);
        $places = $seat['places'] ?? null;

        return [
            'id' => ! empty($seat['id']) ? (int) $seat['id'] : null,
            'label' => $label,
            'category' => $category === '' ? null : mb_substr($category, 0, 60),
            'shape' => array_key_exists($shape, TicketSeat::SHAPES) ? $shape : TicketSeat::SHAPE_CIRCLE,
            'x' => $this->clamp($seat['x'] ?? 0, 0, $map->width),
            'y' => $this->clamp($seat['y'] ?? 0, 0, $map->height),
            'w' => $this->clamp($seat['w'] ?? 40, 10, 1000),
            'h' => $this->clamp($seat['h'] ?? 40, 10, 1000),
            'places' => $places === null || $places === '' ? null : $this->clamp($places, 1, 255),
            'active' => filter_var($seat['active'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    /** @return array{label: string, shape: string, x: int, y: int, w: int, h: int, color: string} */
    private function areaRow(TicketMap $map, array $area): array
    {
        $shape = (string) ($area['shape'] ?? 'rect');
        $color = (string) ($area['color'] ?? 'service');

        return [
            'label' => mb_substr(trim((string) ($area['label'] ?? '')), 0, 60),
            'shape' => array_key_exists($shape, TicketMap::AREA_SHAPES) ? $shape : 'rect',
            'x' => $this->clamp($area['x'] ?? 0, 0, $map->width),
            'y' => $this->clamp($area['y'] ?? 0, 0, $map->height),
            'w' => $this->clamp($area['w'] ?? 100, 10, $map->width),
            'h' => $this->clamp($area['h'] ?? 60, 10, $map->height),
            'color' => array_key_exists($color, TicketMap::AREA_COLORS) ? $color : 'service',
        ];
    }

    private function clamp($value, int $min, int $max): int
    {
        return max($min, min($max, (int) round((float) $value)));
    }
}
