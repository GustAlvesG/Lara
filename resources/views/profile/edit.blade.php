{{-- Perfil: dados, senha, PIN e (para quem gerencia usuários) o teste de e-mail. --}}
@php
    $section = 'rounded-card bg-surface p-5 shadow-card sm:p-7';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Perfil">
            Seus dados de acesso ao painel.
        </x-page-title>

        <div class="{{ $section }}">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="{{ $section }}">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="{{ $section }}">
            <div class="max-w-xl">
                @include('profile.partials.update-pin-form')
            </div>
        </div>

        @can('usuarios.gerenciar')
            <div class="{{ $section }}">
                <div class="max-w-xl">
                    @include('profile.partials.test-mail-configuration')
                </div>
            </div>
        @endcan

        <div class="{{ $section }}">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </x-page>
</x-app-layout>
