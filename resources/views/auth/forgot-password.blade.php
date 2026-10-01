<x-guest-layout>
    <x-auth-card title="Nova senha" :lead="__('Esqueceu sua senha? Sem problemas. Informe seu e-mail abaixo que enviaremos um link para que você possa criar uma nova.')">
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <a href="{{ route('login') }}" class="text-sm font-bold text-grena-ink hover:underline">Voltar ao login</a>
                <x-primary-button>
                    {{ __('Enviar Link para nova senha') }}
                </x-primary-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
