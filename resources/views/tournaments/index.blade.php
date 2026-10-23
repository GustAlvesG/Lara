{{-- Torneios: lista com busca pelo nome e filtro de situação. --}}
@php
    use App\Models\Tournament\Tournament;

    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $kind = fn (string $status) => match ($status) {
        Tournament::STATUS_PUBLISHED => 'ok',
        Tournament::STATUS_IN_PROGRESS => 'info',
        Tournament::STATUS_CANCELLED => 'danger',
        default => 'off',
    };
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Torneios">
            Torneios vendidos no site de locação: categorias, inscrições individuais e pagamento pela eRede.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('tournaments.categories.index') }}"><x-icon name="tag" /> Categorias</x-secondary-button-a>
                <x-primary-button-a href="{{ route('tournaments.create') }}"><x-icon name="plus" /> Novo torneio</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar :filters="['status']" placeholder="Nome do torneio">
            <x-slot:controls>
                <label for="filtro-situacao" class="sr-only">Situação</label>
                <select id="filtro-situacao" name="status" class="{{ $field }} w-full sm:w-52">
                    <option value="">Todas as situações</option>
                    @foreach (Tournament::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if ($tournaments->isEmpty())
            <x-empty-state icon="trophy">Nenhum torneio encontrado.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Torneio</th>
                                <th class="{{ $th }}">Datas</th>
                                <th class="{{ $th }}">Inscrições</th>
                                <th class="{{ $th }} text-center">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($tournaments as $tournament)
                                <tr>
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('tournaments.show', $tournament) }}" class="font-semibold text-ink no-underline hover:underline">{{ $tournament->title }}</a>
                                        <p class="text-xs text-ink-3">{{ $tournament->placeGroup?->name }} · {{ $tournament->categories_count }} categoria(s)</p>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-ink-2">{{ $tournament->starts_on->format('d/m/Y') }}@unless ($tournament->ends_on->isSameDay($tournament->starts_on)) a {{ $tournament->ends_on->format('d/m/Y') }}@endunless</td>
                                    <td class="px-5 py-3.5 text-ink-2">
                                        {{ $tournament->registration_starts_at->format('d/m H:i') }} a {{ $tournament->registration_ends_at->format('d/m H:i') }}
                                        @if ($tournament->registrationOpen())<x-pill kind="ok">abertas</x-pill>@endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center"><x-pill :kind="$kind($tournament->status)">{{ Tournament::STATUS_LABELS[$tournament->status] ?? $tournament->status }}</x-pill></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $tournaments->links() }}
        @endif
    </x-page>
</x-app-layout>
