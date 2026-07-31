<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('PIN de Assinatura') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('6 dígitos usados no tablet de contratos (Kiosk): destravam a sessão e confirmam cada assinatura.') }}
        </p>

        <p class="mt-2 text-sm font-medium {{ $user->hasPin() ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
            {{ $user->hasPin()
                ? __('PIN definido. Preencha abaixo para trocá-lo.')
                : __('Você ainda não tem um PIN cadastrado.') }}
        </p>

        @if (blank($user->matricula))
            {{-- A entrada no tablet é matrícula + PIN: sem matrícula, o PIN sozinho não abre. --}}
            <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                {{ __('A entrada no tablet é feita com matrícula e PIN — preencha sua matrícula nos dados do perfil.') }}
            </p>
        @endif
    </header>

    <form method="post" action="{{ route('profile.pin.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_pin_current_password" :value="__('Current Password')" />
            <x-text-input id="update_pin_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePin->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_pin_pin" :value="__('Novo PIN (6 dígitos)')" />
            <x-text-input id="update_pin_pin" name="pin" type="password" class="mt-1 block w-full tracking-[0.5em] font-mono"
                inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="off" placeholder="••••••" />
            <x-input-error :messages="$errors->updatePin->get('pin')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_pin_pin_confirmation" :value="__('Confirmar PIN')" />
            <x-text-input id="update_pin_pin_confirmation" name="pin_confirmation" type="password" class="mt-1 block w-full tracking-[0.5em] font-mono"
                inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="off" placeholder="••••••" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Salvar PIN') }}</x-primary-button>

            @if (session('status') === 'pin-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
