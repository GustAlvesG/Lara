<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traz para o freelancer a foto que está no cadastro de terceirizado dele.
 *
 * Contexto: até o cadastro de freelancer ganhar foto, quem precisava ser
 * reconhecido na portaria era cadastrado também como terceirizado
 * (`company_workers`), e é lá que as fotos estão. O vínculo entre os dois
 * cadastros é o CPF — comparado só pelos dígitos, porque há documento de
 * terceirizado gravado com máscara.
 *
 * Copia só o CAMINHO: o freelancer passa a apontar para o mesmo arquivo em
 * `public/images`. Nada no sistema apaga foto de terceirizado, então o arquivo
 * compartilhado não some por baixo de ninguém.
 *
 * Idempotente: freelancer que já tem foto não é tocado (a menos de
 * `--sobrescrever`), então rodar de novo não desfaz uma foto tirada depois
 * pelo formulário. Grava direto na tabela, sem mexer em `updated_at` e
 * `updated_by` — não foi ninguém que editou o cadastro.
 */
class MigrarFotosFreelancersCommand extends Command
{
    protected $signature = 'freelancers:migrar-fotos
        {--dry-run : Só lista o que seria feito, sem gravar nada}
        {--sobrescrever : Troca também a foto de quem já tem uma}';

    protected $description = 'Copia para os freelancers o caminho da foto do cadastro de terceirizado com o mesmo CPF';

    public function handle(): int
    {
        if (!Schema::hasColumn('freelancers', 'image')) {
            $this->error('A coluna freelancers.image não existe. Rode `php artisan migrate` antes.');

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $sobrescrever = (bool) $this->option('sobrescrever');

        $fotosPorCpf = $this->fotosDeTerceirizadosPorCpf();

        $migrados = [];
        $jaTinham = 0;
        $semFoto = 0;
        $arquivoAusente = [];
        $conflitos = [];

        $freelancers = DB::table('freelancers')->orderBy('name')->get(['id', 'name', 'cpf', 'image']);

        foreach ($freelancers as $freelancer) {
            $candidatos = $fotosPorCpf->get(self::digitos($freelancer->cpf));

            if ($candidatos === null) {
                $semFoto++;
                continue;
            }

            $escolhido = $candidatos->first();

            if (filled($freelancer->image) && (!$sobrescrever || $freelancer->image === $escolhido->image)) {
                $jaTinham++;
                continue;
            }

            if ($candidatos->pluck('image')->unique()->count() > 1) {
                $conflitos[] = [$freelancer->name, $candidatos->map(fn($w) => "#{$w->id} {$w->company}")->implode(', '), "#{$escolhido->id}"];
            }

            // Apontar para um arquivo que não existe só troca a inicial do nome
            // por uma imagem quebrada no monitor da portaria.
            if (!is_file(public_path('images/' . $escolhido->image))) {
                $arquivoAusente[] = [$freelancer->name, "#{$escolhido->id} {$escolhido->name}", $escolhido->image];
                continue;
            }

            if (!$seco) {
                DB::table('freelancers')->where('id', $freelancer->id)->update(['image' => $escolhido->image]);
            }

            $migrados[] = [
                $freelancer->id,
                $freelancer->name,
                "#{$escolhido->id} {$escolhido->name} ({$escolhido->company})",
                $escolhido->image,
            ];
        }

        if ($migrados !== []) {
            $this->table(['Freelancer', 'Nome', 'Terceirizado de origem', 'Arquivo'], $migrados);
        }

        if ($conflitos !== []) {
            $this->newLine();
            $this->warn('Mesmo CPF em mais de um terceirizado, com fotos diferentes — ficou a do cadastro ativo mais recente:');
            $this->table(['Freelancer', 'Terceirizados', 'Escolhido'], $conflitos);
        }

        if ($arquivoAusente !== []) {
            $this->newLine();
            $this->warn('Foto cadastrada no terceirizado, mas o arquivo não está em public/images — nada gravado:');
            $this->table(['Freelancer', 'Terceirizado', 'Arquivo'], $arquivoAusente);
        }

        $this->newLine();
        $this->info(($seco ? 'Simulação — nada foi gravado. ' : '')
            . count($freelancers) . ' freelancer(s) analisados: '
            . count($migrados) . ($seco ? ' receberiam foto' : ' receberam foto') . ', '
            . $jaTinham . ' já tinham foto, '
            . $semFoto . ' sem terceirizado com foto, '
            . count($arquivoAusente) . ' com arquivo ausente.');

        if ($seco && $migrados !== []) {
            $this->line('Confira a lista e rode de novo sem --dry-run para gravar.');
        }

        return self::SUCCESS;
    }

    /**
     * Terceirizados com foto, agrupados pelo CPF em dígitos. Dentro do grupo, o
     * primeiro é o que vale: cadastro ativo antes do excluído, e o mais
     * recente antes do mais antigo. Excluído entra na conta — a pessoa pode
     * ter saído da empresa parceira e seguido como freelancer, e a foto dela
     * continua sendo a dela.
     *
     * @return Collection<string, Collection<int, object>>
     */
    private function fotosDeTerceirizadosPorCpf(): Collection
    {
        return DB::table('company_workers')
            ->leftJoin('companies', 'companies.id', '=', 'company_workers.company_id')
            ->whereNotNull('company_workers.image')
            ->where('company_workers.image', '!=', '')
            ->whereNotNull('company_workers.document')
            ->orderByRaw('company_workers.deleted_at IS NULL DESC')
            ->orderByDesc('company_workers.updated_at')
            ->orderByDesc('company_workers.id')
            ->get([
                'company_workers.id',
                'company_workers.name',
                'company_workers.document',
                'company_workers.image',
                'companies.name as company',
            ])
            ->filter(fn($worker) => strlen(self::digitos($worker->document)) === 11)
            ->groupBy(fn($worker) => self::digitos($worker->document));
    }

    private static function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }
}
