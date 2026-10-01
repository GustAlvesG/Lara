<?php

namespace Database\Seeders;

use App\Models\Aviso;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Aviso de leitura obrigatória apresentando o novo visual do LARA.
 *
 *   php artisan db:seed --class=AvisoNovoDesignSeeder
 *
 * Público: todo mundo vê em tela cheia na próxima tela que abrir e só segue
 * depois de confirmar. Pode rodar de novo sem duplicar — o aviso é achado
 * pelo título, e quem já confirmou continua confirmado.
 *
 * O autor é alguém de um setor de acesso total (TI, Diretoria, Gerência);
 * sem ninguém assim, o primeiro usuário. O autor não recebe o próprio aviso.
 */
class AvisoNovoDesignSeeder extends Seeder
{
    public const TITLE = 'O LARA está de cara nova';

    /** Dias em que o aviso fica ativo; depois disso deixa de ser cobrado. */
    private const DAYS_ACTIVE = 30;

    public function run(): void
    {
        $author = $this->author();

        $aviso = Aviso::withTrashed()->updateOrCreate(
            ['title' => self::TITLE],
            [
                'content' => $this->content(),
                'privacy' => Aviso::PRIVACY_PUBLICO,
                'mandatory' => true,
                'expires_at' => today()->addDays(self::DAYS_ACTIVE),
                'created_by' => $author->id,
            ]
        );

        // Se alguém tinha removido o aviso, rodar o seeder o traz de volta.
        if ($aviso->trashed()) {
            $aviso->restore();
        }

        $this->command?->info('Aviso "' . self::TITLE . '" publicado como leitura obrigatória (autor: ' . $author->name . ').');
    }

    private function author(): User
    {
        $sectorIds = Sector::where('full_access', true)->pluck('id');

        $author = User::whereHas('sectors', fn ($q) => $q->whereIn('sectors.id', $sectorIds))->orderBy('id')->first()
            ?? User::orderBy('id')->first();

        if (! $author) {
            throw new RuntimeException('Não há usuário para assinar o aviso. Cadastre um usuário antes de rodar o seeder.');
        }

        return $author;
    }

    private function content(): string
    {
        return <<<'HTML'
<p>O sistema ganhou um visual novo. As telas e as funções são as mesmas; o que mudou foi a aparência e o jeito de chegar em cada lugar.</p>
<p><b>O que mudou</b></p>
<ul>
<li><b>Módulos:</b> o botão <b>Módulos</b>, no alto da tela, mostra tudo o que você pode abrir. Cada módulo tem a sua cor.</li>
<li><b>Favoritos:</b> a estrela no alto de cada módulo guarda a página nos seus atalhos. Eles ficam na sua conta e aparecem em qualquer computador.</li>
<li><b>Busca de páginas:</b> a lupa (ou <b>Ctrl K</b>) acha qualquer tela pelo nome.</li>
<li><b>Busca nas listas:</b> toda tela que lista registros tem um campo de busca.</li>
<li><b>Tema escuro:</b> no menu da sua conta dá para escolher claro, escuro ou seguir o computador.</li>
<li><b>Entrada:</b> a tela de login abre na matrícula; o e-mail continua disponível na outra aba.</li>
</ul>
<p><b>Prefere o menu de antes?</b> No menu da sua conta, em Navegação, escolha <b>Lateral</b> ou <b>Superior</b>.</p>
<p>Se alguma tela estiver estranha ou algo não funcionar como antes, avise a Informática.</p>
HTML;
    }
}
