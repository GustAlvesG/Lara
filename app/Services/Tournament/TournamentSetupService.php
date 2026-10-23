<?php

namespace App\Services\Tournament;

use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate;
use App\Models\Tournament\TournamentRegistration;
use Illuminate\Validation\ValidationException;

/**
 * Montagem do torneio pelo organizador: situação e categorias.
 *
 * Situações e passagens permitidas:
 *   rascunho → publicado (precisa de ao menos uma categoria)
 *   publicado → rascunho (só sem nenhuma inscrição)
 *   publicado → em andamento → encerrado
 *   qualquer uma, menos encerrado → cancelado (não estorna sozinho: o
 *   organizador decide caso a caso, inscrição por inscrição)
 *
 * A categoria do torneio copia nome, tamanho, sexo e idade do catálogo do
 * grupo do torneio. Só sai do torneio enquanto não tem inscrição.
 */
class TournamentSetupService
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        Tournament::STATUS_DRAFT => [Tournament::STATUS_PUBLISHED, Tournament::STATUS_CANCELLED],
        Tournament::STATUS_PUBLISHED => [Tournament::STATUS_DRAFT, Tournament::STATUS_IN_PROGRESS, Tournament::STATUS_CANCELLED],
        Tournament::STATUS_IN_PROGRESS => [Tournament::STATUS_FINISHED, Tournament::STATUS_CANCELLED],
        Tournament::STATUS_FINISHED => [],
        Tournament::STATUS_CANCELLED => [],
    ];

    public function changeStatus(Tournament $tournament, string $status): void
    {
        if (! in_array($status, self::TRANSITIONS[$tournament->status] ?? [], true)) {
            $this->fail('status', 'Esta mudança de situação não é permitida.');
        }

        if ($status === Tournament::STATUS_PUBLISHED && ! $tournament->categories()->exists()) {
            $this->fail('status', 'Ligue ao menos uma categoria antes de publicar.');
        }

        if ($status === Tournament::STATUS_DRAFT && $this->hasRegistrations($tournament)) {
            $this->fail('status', 'O torneio já tem inscrições: não volta para rascunho.');
        }

        $tournament->update(['status' => $status]);
    }

    /** @param  array<string, mixed>  $data  campos do vínculo (ver linkRules) */
    public function attachCategory(Tournament $tournament, TournamentCategoryTemplate $template, array $data): TournamentCategory
    {
        if ($template->place_group_id !== $tournament->place_group_id || ! $template->active) {
            $this->fail('category_template_id', 'Escolha uma categoria ativa do catálogo do grupo do torneio.');
        }

        if ($tournament->categories()->where('category_template_id', $template->id)->exists()) {
            $this->fail('category_template_id', 'Esta categoria já está no torneio.');
        }

        return $tournament->categories()->create(array_merge($this->linkFields($data), [
            'category_template_id' => $template->id,
            'name' => $template->name,
            'team_size' => $template->team_size,
            'gender' => $template->gender,
            'min_age' => $template->min_age,
            'max_age' => $template->max_age,
            'sort_order' => (int) $tournament->categories()->max('sort_order') + 1,
        ]));
    }

    /**
     * Preço novo não muda inscrição já feita: cada uma guarda o valor. Com
     * chaveamento gerado, o formato da disputa não muda (desfaça antes).
     */
    public function updateCategory(TournamentCategory $category, array $data): void
    {
        $fields = $this->linkFields($data);

        if ($category->stages()->exists()) {
            foreach (['format', 'groups_count', 'qualifiers_per_group', 'third_place_match'] as $key) {
                if ($category->{$key} != $fields[$key]) {
                    $this->fail('format', 'O chaveamento desta categoria já foi gerado: desfaça o chaveamento antes de mudar o formato, os grupos ou a disputa de 3º lugar.');
                }
            }
        }

        $category->update($fields);
    }

    public function detachCategory(TournamentCategory $category): void
    {
        if ($category->registrations()->exists()) {
            $this->fail('category', 'A categoria já tem inscrições e não pode sair do torneio. Cancele as inscrições antes, se for o caso.');
        }

        $category->delete();
    }

    /** Regras de validação dos campos do vínculo torneio × categoria. */
    public static function linkRules(): array
    {
        return [
            'price' => 'required|numeric|min:0|max:99999',
            'max_registrations' => 'nullable|integer|min:1|max:9999',
            'team_formation' => 'required|in:' . implode(',', array_keys(TournamentCategory::FORMATIONS)),
            'format' => 'required|in:' . implode(',', array_keys(TournamentCategory::FORMATS)),
            'groups_count' => 'nullable|required_if:format,' . TournamentCategory::FORMAT_GROUPS_KNOCKOUT . '|integer|min:1|max:64',
            'qualifiers_per_group' => 'nullable|required_if:format,' . TournamentCategory::FORMAT_GROUPS_KNOCKOUT . '|integer|min:1|max:16',
            'third_place_match' => 'nullable|boolean',
            'sets_to_win' => 'required|integer|min:1|max:5',
        ];
    }

    private function linkFields(array $data): array
    {
        $groups = ($data['format'] ?? null) === TournamentCategory::FORMAT_GROUPS_KNOCKOUT;

        return [
            'price' => round((float) $data['price'], 2),
            'max_registrations' => ($data['max_registrations'] ?? null) ?: null,
            'team_formation' => $data['team_formation'],
            'format' => $data['format'],
            'groups_count' => $groups ? (int) $data['groups_count'] : null,
            'qualifiers_per_group' => $groups ? (int) $data['qualifiers_per_group'] : null,
            'third_place_match' => ! empty($data['third_place_match']),
            'sets_to_win' => (int) $data['sets_to_win'],
        ];
    }

    private function hasRegistrations(Tournament $tournament): bool
    {
        return TournamentRegistration::whereIn('tournament_category_id', $tournament->categories()->pluck('id'))->exists();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
