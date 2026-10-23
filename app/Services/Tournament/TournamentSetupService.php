<?php

namespace App\Services\Tournament;

use App\Exceptions\ConfirmationRequired;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate;
use App\Models\Tournament\TournamentRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Montagem do torneio pelo organizador: situação e categorias.
 *
 * Caminho normal das situações (TRANSITIONS):
 *   rascunho → publicado → em andamento → encerrado
 *   publicado → rascunho; qualquer uma, menos encerrado → cancelado (não
 *   estorna sozinho: o organizador decide caso a caso)
 *
 * Na interface tudo é permitido, mediante aviso (ConfirmationRequired;
 * confirmado com `$force`): situação fora do caminho normal, publicar sem
 * categoria, voltar para rascunho com inscrição, ligar categoria inativa ou
 * com as inscrições do site fechadas, mudar o formato com chaveamento gerado
 * (desfaz o chaveamento), vagas abaixo das pagas e tirar categoria com
 * inscrição sem pagamento (apaga as inscrições).
 *
 * Única recusa de regra: tirar categoria com inscrição que passou por
 * pagamento — ela guarda o histórico financeiro.
 *
 * A categoria do torneio copia nome, tamanho, sexo e idade do catálogo do
 * grupo do torneio.
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

    public function __construct(private TournamentBracketService $brackets)
    {
    }

    public function changeStatus(Tournament $tournament, string $status, bool $force = false): void
    {
        if ($status === $tournament->status) {
            $this->fail('status', 'O torneio já está nesta situação.');
        }

        $from = Tournament::STATUS_LABELS[$tournament->status] ?? $tournament->status;
        $to = Tournament::STATUS_LABELS[$status] ?? $status;

        ConfirmationRequired::unless($force, [
            in_array($status, self::TRANSITIONS[$tournament->status] ?? [], true) ? null
                : "Mudança fora do caminho normal: de {$from} para {$to}.",
            $status === Tournament::STATUS_PUBLISHED && ! $tournament->categories()->exists()
                ? 'O torneio não tem categoria: aparece no site sem onde se inscrever.' : null,
            $status === Tournament::STATUS_DRAFT && $this->hasRegistrations($tournament)
                ? 'O torneio já tem inscrições: em rascunho ele some do site, também para quem já se inscreveu.' : null,
        ]);

        $tournament->update(['status' => $status]);
    }

    /** @param  array<string, mixed>  $data  campos do vínculo (ver linkRules) */
    public function attachCategory(Tournament $tournament, TournamentCategoryTemplate $template, array $data, bool $force = false): TournamentCategory
    {
        if ($template->place_group_id !== $tournament->place_group_id) {
            $this->fail('category_template_id', 'Escolha uma categoria do catálogo do grupo do torneio.');
        }

        if ($tournament->categories()->where('category_template_id', $template->id)->exists()) {
            $this->fail('category_template_id', 'Esta categoria já está no torneio.');
        }

        $siteClosed = in_array($tournament->status, [Tournament::STATUS_IN_PROGRESS, Tournament::STATUS_FINISHED, Tournament::STATUS_CANCELLED], true)
            || ($tournament->status === Tournament::STATUS_PUBLISHED && $tournament->registration_ends_at?->isPast());

        ConfirmationRequired::unless($force, [
            $template->active ? null : "A categoria {$template->name} está inativa no catálogo.",
            $siteClosed ? 'As inscrições pelo site deste torneio já fecharam: nesta categoria só entra quem for inscrito pelo balcão.' : null,
        ]);

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
     * chaveamento gerado, mudar o formato, os grupos ou o 3º lugar avisa e,
     * confirmado, desfaz o chaveamento (e os resultados, se houver).
     */
    public function updateCategory(TournamentCategory $category, array $data, bool $force = false): void
    {
        $fields = $this->linkFields($data);

        $formatChanged = $category->stages()->exists() && collect(['format', 'groups_count', 'qualifiers_per_group', 'third_place_match'])
            ->contains(fn ($key) => $category->{$key} != $fields[$key]);
        $limit = $fields['max_registrations'];
        $paid = $limit === null ? 0 : $category->registrations()->where('status', TournamentRegistration::STATUS_PAID)->count();

        ConfirmationRequired::unless($force, [
            $formatChanged ? 'O chaveamento desta categoria já foi gerado: mudar o formato, os grupos ou o 3º lugar desfaz o chaveamento (gere de novo depois).' : null,
            $formatChanged ? $this->brackets->resultsWarning($category, 'desfazer') : null,
            $limit !== null && $paid > $limit
                ? "A categoria já tem {$paid} inscrição(ões) paga(s), mais que as {$limit} vaga(s) novas. Ninguém sai; só não entra mais ninguém pelo site." : null,
        ]);

        if ($formatChanged) {
            $this->brackets->clear($category, true);
        }

        $category->update($fields);
    }

    /**
     * Tira a categoria do torneio. Com inscrição sem pagamento (ou times e
     * chaveamento), avisa e, confirmado, apaga tudo junto. Com inscrição que
     * passou por pagamento, recusa: é o histórico financeiro.
     */
    public function detachCategory(TournamentCategory $category, bool $force = false): void
    {
        if ($category->registrations()->whereNotNull('tournament_payment_id')->exists()) {
            $this->fail('category', 'A categoria tem inscrição com pagamento registrado e guarda o histórico financeiro: não sai do torneio. Cancele (e estorne) as inscrições e, se quiser, deixe a categoria sem vagas.');
        }

        $registrations = $category->registrations()->count();

        ConfirmationRequired::unless($force, [
            $registrations > 0 ? "A categoria tem {$registrations} inscrição(ões) sem pagamento: elas serão apagadas, com os times." : null,
            $category->stages()->exists() ? 'O chaveamento da categoria (e os resultados, se houver) será apagado.' : null,
        ]);

        DB::transaction(function () use ($category) {
            $category->registrations()->delete();
            $category->delete();
        });
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
