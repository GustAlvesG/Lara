{{--
    Página de erro (4xx.blade.php e 5xx.blade.php). Fica de fora do layout do
    sistema de propósito: o erro pode ser justamente algo que o layout usa.

    Tudo o que consulta dados está no try: banco fora do ar não pode virar um
    erro dentro da página de erro — fica só sem o "Verificar com o HAL".
--}}
@php
    use App\Authorization\Permissions;
    use App\Services\Lara\ScreenError;
    use App\View\Navigation;
    use Illuminate\Support\Facades\Gate;

    $status = $exception->getStatusCode();

    [$titulo, $explicacao] = match (true) {
        $status === 403 => ['Acesso negado', 'Esta tela ou ação exige uma permissão que você não tem.'],
        $status === 404 => ['Página não encontrada', 'O endereço pode estar errado ou o registro pode ter sido removido.'],
        $status === 419 => ['Sua sessão expirou', 'Volte, recarregue a página e tente de novo.'],
        $status === 429 => ['Muitas tentativas', 'Espere um pouco antes de tentar de novo.'],
        $status === 503 => ['O Lara está em manutenção', 'Tente de novo em alguns minutos.'],
        $status >= 500 => ['Algo deu errado do nosso lado', 'Não foi por algo que você fez. Tente de novo; se continuar, avise a TI.'],
        default => ['Não foi possível concluir', 'Volte e tente de novo.'],
    };

    // Mensagem escrita para a pessoa (abort(403, 'texto')). O filtro é o mesmo
    // do que vai para o HAL: erro do sistema e mensagem técnica não aparecem.
    $detalhe = ScreenError::from($status, $exception->getMessage())->texto;

    $podeLara = false;
    try {
        if ($podeLara = auth()->check() && Gate::allows(Permissions::LARA)) {
            $navCurrent = Navigation::build(auth()->user(), request())['current'];
        }
    } catch (\Throwable $e) {
        $podeLara = false;
    }

    $anterior = url()->previous();
@endphp
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if ($podeLara)
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <title>{{ $titulo }} — {{ config('app.name', 'Laravel') }}</title>
    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="flex min-h-screen items-center justify-center bg-canvas p-4 font-sans text-ink antialiased">
    <main class="w-full max-w-md rounded-2xl border border-line bg-surface p-8 text-center shadow-pop">
        <p class="text-sm font-bold text-ink-3">Erro {{ $status }}</p>
        <h1 class="mt-1 font-display text-xl font-bold">{{ $titulo }}</h1>
        <p class="mt-3 text-sm text-ink-2">{{ $explicacao }}</p>
        @if ($detalhe)
            <p class="mt-3 rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger">{{ $detalhe }}</p>
        @endif

        <div class="mt-6 flex flex-wrap justify-center gap-2">
            @if ($podeLara)
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('lara-verificar-erro'))"
                    class="rounded-full bg-grena px-4 py-2 text-sm font-bold text-white transition hover:bg-grena-hover">
                    Verificar com o HAL
                </button>
            @endif
            <a href="{{ $anterior !== url()->current() ? $anterior : url('/') }}"
                class="rounded-full border border-line px-4 py-2 text-sm font-bold text-ink-2 transition hover:bg-subtle hover:text-ink">
                Voltar
            </a>
        </div>
    </main>

    @if ($podeLara)
        @include('partials.lara-widget', ['laraErroPagina' => ['status' => $status, 'texto' => $detalhe]])
    @endif
</body>
</html>
