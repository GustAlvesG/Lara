<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Information;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use App\Listeners\UpdateLastLoginAt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Resources\Json\JsonResource;


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
         * A API do Placar Clube tem contratos de payload explícitos (ex.:
         * GET /placar/jogos/{id} devolve `jogo`/`time_casa`/`time_fora` no
         * nível raiz) — o `data` que o JsonResource embrulha por padrão
         * quebraria isso. Desligado globalmente porque o único Resource
         * pré-existente (UserResource) não é usado em lugar nenhum hoje —
         * não há nada para essa mudança quebrar fora do Placar.
         */
        JsonResource::withoutWrapping();

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
         * Acompanhamento dos lotes — tela de leitura do setor Comercial, que
         * registra os contratos e responde ao freelancer por onde o pagamento
         * dele parou. Vínculo de setor em qualquer papel, pela mesma razão do
         * Gate acima: não é nível de acesso, é de quem é a atribuição.
         */
        Gate::define(
            'track-freelancer-batches',
            fn (User $user) => $user->canTrackFreelancerBatches(),
        );

        /**
         * Placar Clube — telas de cadastro (equipes/times/jogadores/
         * competições/jogos/escalação) e de scout (súmula/artilharia/perfil).
         * Mesma regra hoje (setor Esporte, qualquer papel — ver
         * User::canAccessPlacar()), dois Gates porque cadastro escreve e
         * scout só lê, e podem divergir depois sem precisar tocar em rota.
         */
        Gate::define(
            'manage-placar-cadastro',
            fn (User $user) => $user->canAccessPlacar(),
        );

        Gate::define(
            'view-placar-scout',
            fn (User $user) => $user->canAccessPlacar(),
        );
    }
}
