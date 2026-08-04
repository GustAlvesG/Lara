<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Information;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use App\Listeners\UpdateLastLoginAt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            Login::class,
            UpdateLastLoginAt::class
        );

        /**
         * Financeiro dos freelancers. É um Gate, e não uma permissão do Spatie,
         * porque a regra é vínculo de setor (Contabilidade ou Gerência) e não
         * algo que se conceda na tela de permissões — em particular, a role
         * `admin` não dá acesso. Repare no hífen: as permissões do Spatie neste
         * app usam espaço (`manage freelancers`), os Gates usam hífen.
         */
        Gate::define(
            'manage-freelancer-payments',
            fn (User $user) => $user->canManageFreelancerPayments(),
        );
    }
}
