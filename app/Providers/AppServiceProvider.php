<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Information;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use App\Listeners\UpdateLastLoginAt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
         * Acompanhamento do trâmite dos freelancers (aba só leitura). Mesmo
         * raciocínio do Gate acima: vínculo de setor (Comercial, em qualquer
         * papel), não permissão do Spatie — a role `admin` não dá acesso.
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

        /**
         * Banco de Horas em modo administrador — importar o espelho de ponto,
         * recalcular saldos e mexer no cadastro dos funcionários (férias,
         * afastamento, rescisão).
         *
         * Meio Gate de setor, meio permissão: libera quem está no setor **RH**
         * (qualquer papel) ou quem tem a permissão `import comp time` — ver
         * User::canManageCompTime(). O acesso de leitura NÃO passa por aqui:
         * coordenador enxerga o próprio setor e colaborador enxerga a própria
         * ficha, e isso é decidido em CompTimeService::accessFor().
         */
        Gate::define(
            'manage-comp-time',
            fn (User $user) => $user->canManageCompTime(),
        );

        /**
         * Tem alguma coisa para ver no Banco de Horas — RH, coordenador de
         * algum setor, ou qualquer um com matrícula. Só decide se o menu
         * aparece; o recorte do que a pessoa enxerga é de
         * CompTimeService::accessFor().
         *
         * Existe para que a navegação pergunte por Gate e não chame métodos do
         * model direto: ela é renderizada em quase toda tela do app.
         */
        Gate::define(
            'view-comp-time',
            fn (User $user) => $user->canViewCompTime(),
        );

        /**
         * Teto de envio da Poli Digital: 60 requisições por minuto por
         * APLICAÇÃO. Um limitador só, sem chave por destinatário, porque a
         * cota é da conta inteira — qualquer outro fluxo que passe a enviar
         * pela Poli deve usar ESTE mesmo nome no middleware, e não criar o
         * seu, sob pena de dois baldes de 60 estourarem o limite real.
         *
         * Apoia-se no cache store da aplicação (hoje `database`), e não em
         * Redis: não há Redis servindo de cache ou fila neste projeto.
         */
        RateLimiter::for(
            (string) config('poli.rate_limit.name', 'poli-outbound'),
            fn () => Limit::perMinute((int) config('poli.rate_limit.per_minute', 60)),
        );
    }
}
