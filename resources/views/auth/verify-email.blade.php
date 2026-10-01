<x-guest-layout>
    <x-auth-card title="Confirme seu e-mail" :lead="__('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.')">
        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 rounded-2xl bg-ok-soft p-3 text-sm font-medium text-ok">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="mt-4 flex items-center justify-between gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="rounded-full px-2 text-sm font-bold text-ink-2 underline transition hover:text-ink focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </x-auth-card>
</x-guest-layout>
