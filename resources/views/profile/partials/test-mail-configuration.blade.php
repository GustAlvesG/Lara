@php
    $result = session('mail_test');

    $badge = [
        'ok' => ['label' => 'OK', 'class' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
        'warn' => ['label' => 'Atenção', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'],
        'fail' => ['label' => 'Falhou', 'class' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'],
    ];
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Teste de E-mail') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Confere as configurações de envio e abre a conexão com o servidor SMTP para validar host, porta, criptografia e credenciais. Nenhuma mensagem é enviada.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.email-test') }}" class="mt-6"
          x-data="{ testing: false }" x-on:submit="testing = true">
        @csrf

        <div class="flex items-center gap-4">
            <x-primary-button x-bind:disabled="testing">
                <span x-show="!testing">{{ __('Testar configuração') }}</span>
                <span x-show="testing" x-cloak>{{ __('Testando…') }}</span>
            </x-primary-button>

            @if ($result)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Último teste:') }} {{ $result['tested_at'] }}
                </span>
            @endif
        </div>
    </form>

    @if ($result)
        <div class="mt-6 space-y-4">
            <div class="rounded-lg p-4 {{ $result['ok']
                ? 'bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800'
                : 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800' }}">
                <p class="font-semibold {{ $result['ok'] ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                    {{ $result['ok']
                        ? __('Configuração de e-mail válida.')
                        : __('A configuração de e-mail tem problemas.') }}
                </p>
                <p class="mt-1 text-sm {{ $result['ok'] ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                    {{ __('Mailer') }}: <span class="font-mono">{{ $result['mailer'] }}</span>
                </p>
            </div>

            <ul class="space-y-3">
                @foreach ($result['checks'] as $check)
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 shrink-0 px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide {{ $badge[$check['status']]['class'] }}">
                            {{ $badge[$check['status']]['label'] }}
                        </span>
                        <span class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $check['label'] }}</span>
                            <span class="block text-gray-600 dark:text-gray-400 break-words">{{ $check['detail'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Configuração atual') }}</h3>
                <dl class="mt-2 space-y-1">
                    @foreach ($result['settings'] as $label => $value)
                        <div class="flex gap-2 text-sm">
                            <dt class="w-32 shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="font-mono text-gray-800 dark:text-gray-200 break-all">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif
</section>
