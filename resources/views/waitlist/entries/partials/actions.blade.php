{{--
    As operações de uma inscrição que aguarda (pessoa × turma): observação da
    fila, registrar ligação, histórico de ligações e tirar da fila. Usado no
    cartão da ficha da pessoa e no acordeão da fila da turma (19/11/2026).

    Parâmetros: $entry (com person e contacts.user carregados) e $back
    ('turma' para a resposta voltar à fila da turma; null volta à ficha).
    Todo formulário leva `entry_id`, para o acordeão certo reabrir quando a
    validação falha.

    Sem CPF, as ações dão lugar a um lembrete (o WaitlistService recusa do
    mesmo jeito).
--}}
@php
    $back = $back ?? null;
    $person = $entry->person;
    $hasCpf = $person->hasCpf();
    $selectClass = 'h-11 w-full rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $mine = (int) old('entry_id') === $entry->id;
@endphp

{{-- Na ficha os erros já aparecem no alto da página; na fila da turma, aqui. --}}
@if ($back && $mine && $errors->any())
    <div class="border-b border-line bg-danger-soft px-5 py-3 text-sm text-danger sm:px-6" role="alert">
        @foreach ($errors->all() as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif

@if ($hasCpf)
    <form method="POST" action="{{ route('waitlist.entries.notes', $entry) }}" class="grid grid-cols-1 items-end gap-3 border-b border-line p-5 sm:grid-cols-[1fr_auto] sm:px-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="entry_id" value="{{ $entry->id }}">
        @if ($back)<input type="hidden" name="back" value="{{ $back }}">@endif
        <div>
            <x-input-label for="entry-notes-{{ $entry->id }}" value="Observação da fila" class="mb-1.5" />
            <textarea name="entry_notes" id="entry-notes-{{ $entry->id }}" rows="2" maxlength="2000"
                class="w-full rounded-2xl border border-line-strong bg-surface px-4 py-3 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint"
                placeholder="Ex.: só pode à tarde; prefere a turma de sábado">{{ $mine ? old('entry_notes', $entry->notes) : $entry->notes }}</textarea>
            <p class="mt-1 text-xs text-ink-3">Vale só para esta turma. Aparece na fila, abaixo do nome.</p>
        </div>
        <x-secondary-button type="submit" class="justify-center"><x-icon name="check" /> Salvar observação</x-secondary-button>
    </form>

    <form method="POST" action="{{ route('waitlist.entries.contact', $entry) }}" class="grid grid-cols-1 items-end gap-3 border-b border-line p-5 sm:grid-cols-[minmax(0,16rem)_1fr_auto] sm:px-6">
        @csrf
        <input type="hidden" name="entry_id" value="{{ $entry->id }}">
        @if ($back)<input type="hidden" name="back" value="{{ $back }}">@endif
        <div>
            <x-input-label for="result-{{ $entry->id }}" value="Resultado da ligação" class="mb-1.5" />
            <select name="result" id="result-{{ $entry->id }}" required class="{{ $selectClass }}">
                <option value="">Escolha…</option>
                @foreach (\App\Models\Waitlist\WaitlistContact::RESULTS as $value => $text)
                    <option value="{{ $value }}">{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="notes-{{ $entry->id }}" value="Observação da ligação" class="mb-1.5" />
            <x-text-input type="text" name="notes" id="notes-{{ $entry->id }}" maxlength="255" placeholder="Ex.: ligar depois das 18h" />
        </div>
        <x-primary-button class="justify-center"><x-icon name="chat" /> Registrar ligação</x-primary-button>
    </form>
@else
    @if ($entry->notes)
        <p class="whitespace-pre-line border-b border-line px-5 py-3 text-sm text-ink-2 sm:px-6"><strong class="text-ink">Observação da fila:</strong> {{ $entry->notes }}</p>
    @endif
    <p class="border-b border-line px-5 py-3 text-sm text-warn sm:px-6">
        {{ $person->name }} está sem CPF. Preencha o CPF em
        <a href="{{ route('waitlist.people.show', $person) }}#cadastro" class="font-bold underline">Editar cadastro</a>
        para registrar ligação, alterar a observação ou tirar da fila.
    </p>
@endif

@if ($entry->contacts->isNotEmpty())
    <ul class="divide-y divide-line border-b border-line">
        @foreach ($entry->contacts as $contact)
            <li class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-3 text-sm sm:px-6">
                <span class="text-ink">
                    <strong>{{ $contact->resultLabel() }}</strong>
                    @if ($contact->notes) — {{ $contact->notes }} @endif
                </span>
                <span class="text-xs text-ink-3">
                    {{ $contact->created_at->format('d/m/Y H:i') }}@if ($contact->user) · {{ $contact->user->name }}@endif
                </span>
            </li>
        @endforeach
    </ul>
@else
    <p class="border-b border-line px-5 py-3 text-sm text-ink-3 sm:px-6">Nenhuma ligação registrada.</p>
@endif

@if ($hasCpf)
    <details class="p-5 sm:px-6" @if ($mine && $errors->hasAny(['status', 'close_reason'])) open @endif>
        <summary class="cursor-pointer text-sm font-bold text-grena-ink">Tirar da fila (matriculou, desistiu ou remover)</summary>
        <form method="POST" action="{{ route('waitlist.entries.close', $entry) }}" class="mt-4 grid grid-cols-1 items-end gap-3 sm:grid-cols-[minmax(0,14rem)_1fr_auto]">
            @csrf
            <input type="hidden" name="entry_id" value="{{ $entry->id }}">
            @if ($back)<input type="hidden" name="back" value="{{ $back }}">@endif
            <div>
                <x-input-label for="status-{{ $entry->id }}" value="Situação" class="mb-1.5" />
                <select name="status" id="status-{{ $entry->id }}" required class="{{ $selectClass }}">
                    <option value="enrolled">Matriculado</option>
                    <option value="withdrawn">Desistiu</option>
                    <option value="removed">Removido (diga o motivo)</option>
                </select>
            </div>
            <div>
                <x-input-label for="reason-{{ $entry->id }}" value="Motivo / observação" class="mb-1.5" />
                <x-text-input type="text" name="close_reason" id="reason-{{ $entry->id }}" maxlength="255" />
            </div>
            <x-danger-button class="justify-center" onclick="return confirm('Tirar {{ addslashes($person->name) }} da fila desta turma?');">
                <x-icon name="check" /> Confirmar
            </x-danger-button>
        </form>
        <p class="mt-2 text-xs text-ink-3">Dá para reativar depois, na ficha da pessoa: ela volta para a mesma posição.</p>
    </details>
@endif
