@php
    $logDateTime = explode(' ', $log['entry_date']);
    $logTime = $logDateTime[0] ?? '';
    $logDate = $logDateTime[1] ?? '';
    $drivers = $log['access'] ?? [];
    $driverCount = is_countable($drivers) ? count($drivers) : 0;
    $th = 'px-3 py-2.5 text-left text-xs font-bold text-ink-3';
    // Foto da câmera da portaria, trazida do FTP na hora da busca. `file`
    // vem falso quando o FTP não respondeu: fica só o substituto.
    $imageUrl = ! empty($log['file']) ? asset('storage/img_car/' . $log['file']) : null;
@endphp

<div class="overflow-hidden rounded-2xl border border-line" data-search="{{ $logTime }} {{ $logDate }}">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line bg-subtle px-4 py-3">
        <span class="inline-flex items-center gap-1.5 font-mono text-sm font-semibold text-ink">
            <x-icon name="clock" class="h-4 w-4 text-grena-ink" />{{ $logTime }}
        </span>
        <span class="font-mono text-sm text-ink-2">{{ $logDate }}</span>
        <span class="ml-auto">
            <x-pill :kind="$driverCount ? 'ok' : 'off'" :icon="false">{{ $driverCount }} {{ $driverCount === 1 ? 'condutor' : 'condutores' }}</x-pill>
        </span>
    </div>

    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-12">
        {{-- A foto é o que confirma a leitura da placa; clicar abre em tamanho cheio. --}}
        <div class="md:col-span-4">
            @if ($imageUrl)
                <a href="{{ $imageUrl }}" target="_blank" rel="noopener" class="group relative block overflow-hidden rounded-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                    <x-media :src="$imageUrl" :alt="'Foto do veículo em ' . $logDate . ' ' . $logTime" area="portaria" icon="car" ratio="sq" />
                    <span class="absolute bottom-2 right-2 rounded-full bg-ink/70 px-2.5 py-1 text-[11px] font-bold text-canvas opacity-0 transition group-hover:opacity-100">Ampliar</span>
                </a>
            @else
                <div class="overflow-hidden rounded-xl">
                    <x-media :alt="'Sem foto deste acesso'" area="portaria" icon="car" ratio="sq" />
                </div>
                <p class="mt-1 text-xs text-ink-3">Sem foto deste acesso.</p>
            @endif
        </div>

        <div class="md:col-span-8">
        @if ($driverCount)
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-line">
                            <th class="{{ $th }}">Mat.</th>
                            <th class="{{ $th }}">Nome</th>
                            <th class="{{ $th }}">Telefone</th>
                            <th class="{{ $th }}">Horário</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($drivers as $driver)
                            <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                <td class="whitespace-nowrap px-3 py-2.5 font-mono font-semibold text-ink">{{ $driver->TitleCode }}</td>
                                <td class="px-3 py-2.5 text-ink">{{ $driver->Name }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 font-mono text-ink-2">
                                    <a href="tel:{{ preg_replace('/\D/', '', $driver->Telephone) }}" class="text-ink-2 no-underline hover:text-grena-ink">{{ $driver->Telephone }}</a>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 font-mono font-semibold text-ink">{{ $driver->date }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state icon="user">Nenhum condutor associado a este acesso.</x-empty-state>
        @endif
        </div>
    </div>
</div>
