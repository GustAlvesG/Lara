{{--
    "Não enviar para": quem pediu para sair das campanhas. Vale para todas,
    inclusive as que já estão enviando.
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-2.5';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Não enviar para">
            Quem pediu para não receber mais as campanhas. Nenhuma campanha manda para estes endereços, nem as que já estão enviando.
        </x-page-title>

        @include('partials.alerts')

        <form method="POST" action="{{ route('email-suppressions.store') }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div class="md:col-span-2">
                    <x-input-label for="emails" class="mb-1.5">E-mails <span class="text-danger">*</span></x-input-label>
                    <textarea name="emails" id="emails" rows="4" required
                              class="w-full rounded-xl border border-line-strong bg-surface px-3.5 py-2.5 font-mono text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint"
                              placeholder="um@exemplo.com.br&#10;outro@exemplo.com.br">{{ old('emails') }}</textarea>
                    <p class="mt-1.5 text-xs text-ink-3">Um por linha, ou separados por vírgula ou ponto e vírgula.</p>
                    <x-input-error :messages="$errors->get('emails')" class="mt-1" />
                </div>
                <div class="flex flex-col">
                    <x-input-label for="reason" value="Motivo" class="mb-1.5" />
                    <x-text-input type="text" name="reason" id="reason" maxlength="255" value="{{ old('reason') }}" placeholder="Ex.: respondeu DESCADASTRAR" />
                    <div class="mt-auto pt-4">
                        <x-primary-button><x-icon name="ban" /> Não enviar mais</x-primary-button>
                    </div>
                </div>
            </div>
        </form>

        <x-search-bar placeholder="Buscar e-mail" />

        @if ($suppressions->isEmpty())
            <x-empty-state icon="mail">
                @if ($filters['q'] !== '')
                    Nenhum e-mail com essa busca.
                    <a href="{{ route('email-suppressions.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Ninguém pediu para sair ainda.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">E-mail</th>
                                <th class="{{ $th }}">Motivo</th>
                                <th class="{{ $th }}">Incluído por</th>
                                <th class="{{ $th }} text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($suppressions as $suppression)
                                <tr class="border-b border-line last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }} font-mono text-[13px] text-ink">{{ $suppression->email }}</td>
                                    <td class="{{ $td }} text-ink-2">{{ $suppression->reason ?? '—' }}</td>
                                    <td class="{{ $td }} text-ink-2">
                                        {{ $suppression->author?->name ?? '—' }}
                                        <span class="text-xs text-ink-3">em {{ $suppression->created_at?->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="{{ $td }} text-right">
                                        <form method="POST" action="{{ route('email-suppressions.destroy', $suppression) }}"
                                              onsubmit="return confirm('{{ $suppression->email }} volta a receber as campanhas. Confirmar?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Voltar a enviar" aria-label="Voltar a enviar para {{ $suppression->email }}"
                                                    class="inline-grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-danger">
                                                <x-icon name="trash" />
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $suppressions->links() }}
        @endif
    </x-page>
</x-app-layout>
