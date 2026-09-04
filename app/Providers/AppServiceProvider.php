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

        /**
         * Acompanhamento do trâmite dos freelancers (aba só leitura). Mesmo
         * raciocínio do Gate acima: vínculo de setor (Comercial, em qualquer
         * papel), não permissão do Spatie — a role `admin` não dá acesso.
         */
        Gate::define(
            'track-freelancer-batches',
            fn (User $user) => $user->canTrackFreelancerBatches(),
        );

        /**
         * Mapa de cotação. Mesmo raciocínio dos dois acima: o acesso ao módulo
         * é vínculo com o setor **Contabilidade**, não permissão do Spatie — a
         * role `admin` não dá acesso.
         *
         * É a PORTA do módulo, não o que se faz dentro dele: as permissões
         * `cotacao.*` continuam separando ver, cotar, decidir e exportar. A
         * policy exige as duas coisas.
         *
         * Existe como Gate também por causa do menu: o layout renderiza em toda
         * tela, e `can()` funciona com o usuário mockado dos testes, enquanto
         * chamar o método do model direto na view iria ao banco.
         */
        Gate::define(
            'acessar-cotacao',
            fn (User $user) => $user->canAccessCotacao(),
        );
    }
}
