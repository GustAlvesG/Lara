{{--
    Mensagens de retorno (sucesso, erro, atenção) vindas da sessão — ou de
    ?success= / ?error= na URL, para telas que redirecionam por JavaScript.
    Os ids ficam: há telas que removem o aviso por script.
--}}
@php
    $alerts = array_filter([
        'success' => (session('success') || isset($_GET['success']))
            ? ['ok', 'check', 'Sucesso!', [session('success') ?? ($_GET['success'] ?? '')]]
            : null,
        'error' => (session('error') || isset($_GET['error']))
            ? ['danger', 'x', 'Erro!', ['Por favor, tente novamente ou entre em contato com a TI.', session('error') ?? '']]
            : null,
        'warning' => session('warning')
            ? ['warn', 'clock', 'Atenção', [session('warning')]]
            : null,
    ]);
    $tones = [
        'ok' => 'bg-ok-soft text-ok',
        'danger' => 'bg-danger-soft text-danger',
        'warn' => 'bg-warn-soft text-warn',
    ];
@endphp

@foreach ($alerts as $id => [$tone, $glyph, $title, $lines])
    <div id="{{ $id }}-alert" class="mb-6 animate-fadeIn" role="{{ $tone === 'ok' ? 'status' : 'alert' }}">
        <div class="flex items-start justify-between gap-4 rounded-2xl px-5 py-4 {{ $tones[$tone] }}">
            <div class="flex items-start gap-3">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-surface/70">
                    <x-icon :name="$glyph" class="h-4 w-4" />
                </span>
                <div>
                    <p class="font-bold leading-tight">{{ $title }}</p>
                    @foreach (array_filter($lines, 'filled') as $line)
                        <p class="mt-1 text-sm text-ink-2">{{ $line }}</p>
                    @endforeach
                    {{-- Atalho opcional para o próximo passo (ex.: "montar o elenco") --}}
                    @if ($id === 'success' && is_array(session('success_link')))
                        <a href="{{ session('success_link')['url'] }}" class="mt-2 inline-block text-sm font-bold underline hover:no-underline">
                            {{ session('success_link')['label'] }} →
                        </a>
                    @endif
                </div>
            </div>
            <button type="button" onclick="document.getElementById('{{ $id }}-alert').remove()" aria-label="Fechar aviso"
                class="grid h-8 w-8 shrink-0 place-items-center rounded-full opacity-70 transition hover:bg-surface/70 hover:opacity-100">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>
    </div>
@endforeach

{{-- Regra que só avisa (App\Exceptions\ConfirmationRequired): confirmar
     reenvia o mesmo formulário com force=1. --}}
@if (is_array($confirmation = session('confirmation')))
    @php
        $hidden = [];
        $flatten = function (array $values, string $prefix = '') use (&$flatten, &$hidden) {
            foreach ($values as $key => $value) {
                $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
                is_array($value) ? $flatten($value, $name) : $hidden[] = [$name, (string) $value];
            }
        };
        $flatten($confirmation['input'] ?? []);
        $method = strtoupper($confirmation['method'] ?? 'POST');
    @endphp
    <div id="confirmation-alert" class="mb-6 animate-fadeIn" role="alert">
        <div class="flex flex-col gap-3 rounded-2xl bg-warn-soft px-5 py-4 text-warn">
            <div class="flex items-start gap-3">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-surface/70">
                    <x-icon name="clock" class="h-4 w-4" />
                </span>
                <div>
                    <p class="font-bold leading-tight">Atenção: confira antes de seguir</p>
                    @foreach ($confirmation['warnings'] ?? [] as $line)
                        <p class="mt-1 text-sm text-ink-2">{{ $line }}</p>
                    @endforeach
                </div>
            </div>
            <form method="POST" action="{{ $confirmation['action'] }}" class="flex flex-wrap gap-2 sm:pl-11">
                @csrf
                @if ($method !== 'POST') @method($method) @endif
                @foreach ($hidden as [$name, $value])
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <input type="hidden" name="force" value="1">
                <x-primary-button>Confirmar mesmo assim</x-primary-button>
                <x-secondary-button type="button" onclick="document.getElementById('confirmation-alert').remove()">Não fazer</x-secondary-button>
            </form>
        </div>
    </div>
@endif
