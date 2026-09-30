<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Authorization\Permissions;
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
         * Permissões do catálogo (App\Authorization\Permissions): o acesso
         * efetivo do usuário decide — setor de acesso total, permissão do
         * setor ou permissão individual. Ver User::access().
         *
         * Responde SÓ por nomes do catálogo. Qualquer outra ability — policy,
         * Gate de cargo — devolve null aqui e segue o caminho normal do
         * Laravel. É isso que impede o acesso total de passar por cima de
         * "mapa fechado não se edita" ou de "quem aprova o lote é o
         * coordenador da Gerência".
         */
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User || ! Permissions::exists($ability)) {
                return null;
            }

            return $user->hasAccess($ability);
        });

        /*
         * Daqui para baixo, regras de CARGO: dependem de quem a pessoa é no
         * setor, não de permissão. O acesso total não as alcança, e elas não
         * aparecem na tela de Setores.
         */

        /**
         * Validação dos contratos da redação 2 pela web — o que substituiu, para
         * esses contratos, a assinatura do coordenador desenhada no tablet. É do
         * mesmo cargo que assinava: o coordenador do setor Comercial.
         */
        Gate::define(
            'validate-freelancer-contracts',
            fn (User $user) => $user->isCoordinatorOfSectorNamed(User::COMMERCIAL_SECTOR),
        );

        /**
         * Cadastro da diretoria (nome, e-mail que recebe os códigos e a imagem
         * da assinatura). Só o coordenador da Gerência: é ele quem envia o lote
         * ao diretor e digita o código ditado — o destinatário é dele.
         */
        Gate::define(
            'manage-freelancer-director',
            fn (User $user) => $user->isManagementCoordinator(),
        );

        /**
         * "Meu setor": coordenador de ao menos um setor. Ele adiciona e remove
         * colaboradores e cadastra gente nova, só nos setores que coordena —
         * ver MySectorController, que confere o setor em cada ação.
         */
        Gate::define(
            'coordinate-sector',
            fn (User $user) => $user->isCoordinator(),
        );

        /**
         * Tem alguma coisa para ver no Banco de Horas — administrador do banco
         * de horas, coordenador de algum setor, ou qualquer um com matrícula.
         * Só decide se o menu aparece; o recorte do que a pessoa enxerga é de
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
         * Teto de envio da Poli Digital, por APLICAÇÃO (ver config/poli.php).
         * Um limitador só, sem chave por destinatário, porque a cota é da
         * conta inteira — qualquer outro fluxo que passe a enviar pela Poli
         * deve usar ESTE mesmo nome no middleware, e não criar o seu, sob
         * pena de dois baldes estourarem o limite real.
         *
         * Apoia-se no cache store da aplicação (hoje `database`), e não em
         * Redis: não há Redis servindo de cache ou fila neste projeto.
         */
        RateLimiter::for(
            (string) config('poli.rate_limit.name', 'poli-outbound'),
            fn () => Limit::perMinute((int) config('poli.rate_limit_per_minute', 50)),
        );
    }
}
