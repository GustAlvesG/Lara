@props(['title' => 'Assinatura de Cachê', 'heading' => 'Assinatura', 'employee' => null])

{{--
    Página da assinatura do funcionário — fora do painel e fora do layout do
    app: quem abre esta tela não tem login no sistema. Mobile-first e com alvos
    grandes, porque é usada no celular ou no tablet do setor.
--}}
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    {{-- Esta página não passa pelo bundle do painel (não há sessão de usuário
         aqui), então o Alpine vem pelo CDN, como o Tailwind acima. --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="h-full bg-gray-100 text-gray-900 antialiased">
    <div class="min-h-full flex flex-col">
        <header class="bg-[#A00001] text-white px-5 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-widest opacity-80">Cachê</p>
                <h1 class="text-lg font-extrabold leading-tight">{{ $heading }}</h1>
            </div>

            @if($employee)
                <div class="flex items-center gap-4">
                    <div class="text-right leading-tight">
                        <p class="text-sm font-bold">{{ $employee->name }}</p>
                        <p class="text-xs opacity-80">{{ $employee->employee_code }}</p>
                    </div>
                    <form method="POST" action="{{ route('employee-caches.sign.logout') }}">
                        @csrf
                        <button type="submit" class="text-xs font-bold underline opacity-90">Sair</button>
                    </form>
                </div>
            @endif
        </header>

        <main class="flex-1 p-5 max-w-3xl w-full mx-auto space-y-5">
            @if(session('success'))
                <div class="bg-green-600 text-white rounded-2xl px-5 py-4 font-semibold shadow-lg">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-600 text-white rounded-2xl px-5 py-4 font-semibold shadow-lg">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-600 text-white rounded-2xl px-5 py-4 shadow-lg">
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
