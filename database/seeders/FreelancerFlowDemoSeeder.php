<?php

namespace Database\Seeders;

use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use App\Models\FunctionFreelancer;
use App\Models\User;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Massa de demonstração do fluxo de freelancers: um contrato em cada estágio,
 * do "não assinado" ao "aprovado pela gerência", para dar o que testar em cada
 * tela sem precisar percorrer a cadeia inteira à mão.
 *
 *   php artisan db:seed --class=FreelancerFlowDemoSeeder
 *
 * Não é idempotente: cada execução acrescenta um novo conjunto.
 */
class FreelancerFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $services = app(FreelancerServiceManager::class);

        $coordinator = User::all()->first(fn(User $u) => $u->isCoordinatorOfSectorNamed('Comercial'))
            ?? User::all()->first(fn(User $u) => $u->isCoordinator());
        $manager = User::role('admin')->first();
        $freelancers = Freelancer::orderBy('id')->get();
        $functions = FunctionFreelancer::where('price', '<', 10)->orderBy('name')->get();

        if ($freelancers->isEmpty() || $functions->isEmpty() || !$coordinator || !$manager) {
            $this->command->error('Faltam freelancers, funções, coordenador ou gerente (role admin). Nada foi criado.');

            return;
        }

        $this->command->info("Coordenador: {$coordinator->name} · Gerente: {$manager->name}");

        // Cada linha é um turno; os locais são só para dar contexto na tela.
        $plan = [
            // [dias atrás, início, fim, local, estágio]
            [1, '18:00', '23:00', 'Confraternização — Salão Nobre', 'nova'],
            [1, '19:00', '23:30', 'Aniversário infantil — Espaço Kids', 'nova'],
            [2, '20:00', '02:00', 'Baile de aniversário — Salão Nobre', 'freelancer'],
            [2, '17:00', '22:00', 'Formatura — Praça de Esportes', 'freelancer'],
            [4, '19:00', '23:00', 'Casamento — Salão Nobre', 'assinado'],
            [5, '18:30', '00:30', 'Show de música — Área externa', 'assinado'],
            [6, '12:00', '17:00', 'Almoço de confraternização — Restaurante', 'assinado'],
            [9, '19:00', '23:00', 'Evento corporativo CSN — Auditório', 'enviado'],
            [10, '20:00', '01:00', 'Réveillon antecipado — Salão Nobre', 'enviado'],
            [12, '18:00', '23:00', 'Jantar de premiação — Salão Nobre', 'diretoria'],
            [13, '19:30', '01:30', 'Baile de máscaras — Salão Nobre', 'diretoria'],
            [15, '18:00', '22:00', 'Jantar dançante — Salão Nobre', 'aprovado'],
            [16, '21:00', '03:00', 'Festa junina — Quadra coberta', 'recusado'],
        ];

        $created = [];

        foreach ($plan as $i => [$daysAgo, $start, $end, $location, $stage]) {
            $service = $services->createService([
                'freelancer_id' => $freelancers[$i % $freelancers->count()]->id,
                'function_freelancer_id' => $functions[$i % $functions->count()]->id,
                'location' => $location,
                'start_date' => now()->subDays($daysAgo)->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'created_by' => $coordinator->id,
            ]);

            $service->forceFill(['created_by' => $coordinator->id, 'updated_by' => $coordinator->id])->save();

            $this->applyStage($service, $stage, $coordinator);
            $created[$stage][] = $service;
        }

        // Lote aguardando a gerência — é o que o gerente vai analisar na web.
        $sent = FreelancerServiceBatch::create([
            'status' => FreelancerServiceBatch::STATUS_SENT,
            'created_by' => $coordinator->id,
            'sent_at' => now()->subHours(3),
        ]);
        foreach ($created['enviado'] ?? [] as $service) {
            $service->forceFill(['batch_id' => $sent->id])->save();
        }

        // Lote aprovado pela gerência, aguardando o código da diretoria. Os PINs
        // já vêm gerados e o lote consta como avisado, para dar o que testar
        // mesmo sem SMTP — os números saem no relatório abaixo.
        $awaiting = FreelancerServiceBatch::create([
            'status' => FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR,
            'created_by' => $coordinator->id,
            'sent_at' => now()->subDays(2),
            'reviewed_by' => $manager->id,
            'reviewed_at' => now()->subDay(),
            'director_email' => config('freelancers.director.email') ?: 'diretoria@exemplo.com.br',
            'director_notified_at' => now()->subDay(),
        ]);
        $awaiting->ensureDirectorPins();

        foreach ($created['diretoria'] ?? [] as $service) {
            $service->forceFill([
                'batch_id' => $awaiting->id,
                'manager_approved_at' => now()->subDay(),
                'manager_approved_by' => $manager->id,
            ])->save();
        }

        // Lote já decidido pela diretoria: um contrato aprovado (segue ao
        // financeiro) e um recusado pela gerência (volta ao coordenador).
        $decided = FreelancerServiceBatch::create([
            'status' => FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED,
            'created_by' => $coordinator->id,
            'sent_at' => now()->subDays(4),
            'reviewed_by' => $manager->id,
            'reviewed_at' => now()->subDays(3),
            'director_email' => config('freelancers.director.email') ?: 'diretoria@exemplo.com.br',
            'director_notified_at' => now()->subDays(3),
            'director_decision' => FreelancerServiceBatch::DECISION_APPROVED,
            'director_decided_at' => now()->subDays(2),
            'director_decided_by' => $manager->id,
            'director_note' => 'Código informado por telefone.',
        ]);
        $decided->ensureDirectorPins();

        foreach ($created['aprovado'] ?? [] as $service) {
            $service->forceFill([
                'batch_id' => $decided->id,
                'manager_approved_at' => now()->subDays(3),
                'manager_approved_by' => $manager->id,
                'director_approved_at' => now()->subDays(2),
            ])->save();
        }

        foreach ($created['recusado'] ?? [] as $service) {
            $service->forceFill([
                'batch_id' => $decided->id,
                'manager_rejected_at' => now()->subDays(3),
                'manager_rejected_by' => $manager->id,
                'manager_rejection_reason' => 'Horário de término divergente do informado pelo evento.',
            ])->save();
        }

        $this->report($created, $sent, $awaiting, $decided);
    }

    /** Leva o contrato até o estágio pedido, assinando o que for necessário. */
    private function applyStage(FreelancerService $service, string $stage, User $coordinator): void
    {
        if ($stage === 'nova') {
            return; // sem assinatura nenhuma
        }

        $service->forceFill([
            'freelancer_signed_at' => $service->start_date->copy()->addDay()->setTime(9, 30),
            'freelancer_signed_by' => $coordinator->id,
            'freelancer_signature_path' => $this->drawSignature($service, 'freelancer'),
        ])->save();

        if ($stage === 'freelancer') {
            return; // aguardando o coordenador
        }

        $service->forceFill([
            'coordinator_signed_at' => $service->start_date->copy()->addDay()->setTime(14, 0),
            'coordinator_signed_by' => $coordinator->id,
            'coordinator_signature_path' => $this->drawSignature($service, 'coordinator'),
        ])->save();
    }

    /**
     * Desenha um rabisco de assinatura em PNG transparente, no mesmo formato
     * que o canvas do kiosk gera — para as telas mostrarem algo de verdade.
     */
    private function drawSignature(FreelancerService $service, string $party): string
    {
        $width = 340;
        $height = 92;

        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagesetthickness($img, 3);

        $ink = imagecolorallocate($img, 26, 21, 18);

        // Semente derivada do contrato: o mesmo serviço sempre gera o mesmo traço.
        $seed = crc32($service->id . $party);
        mt_srand($seed);

        // Somar ondas de frequências incomensuráveis, com fases sorteadas, evita
        // o desenho virar uma senoide limpa — que não se parece com assinatura.
        $waves = [];
        for ($w = 0; $w < 4; $w++) {
            $waves[] = [
                'freq' => mt_rand(150, 900) / 100,
                'amp' => mt_rand(4, 20),
                'phase' => mt_rand(0, 628) / 100,
            ];
        }

        $baseline = $height * 0.58;
        $previous = null;

        for ($x = 18; $x <= $width - 34; $x++) {
            $progress = ($x - 18) / ($width - 52);

            $y = $baseline;
            foreach ($waves as $wave) {
                // Envelope: o traço abre no meio e fecha nas pontas, como a mão faz.
                $envelope = sin($progress * M_PI) ** 0.6;
                $y += sin($progress * M_PI * $wave['freq'] + $wave['phase']) * $wave['amp'] * $envelope;
            }
            $y -= $progress * 10; // leve subida para a direita

            if ($previous !== null) {
                imagesetthickness($img, $x % 37 < 18 ? 3 : 2);
                imageline($img, $previous[0], (int) $previous[1], $x, (int) $y, $ink);
            }

            $previous = [$x, $y];
        }

        // Rubrica final: laço de volta por baixo, cruzando o traço.
        imagesetthickness($img, 2);
        $loopStart = [(int) $previous[0], (int) $previous[1]];
        imageline($img, $loopStart[0], $loopStart[1], $width - 16, (int) ($baseline - 22), $ink);
        imageline($img, $width - 16, (int) ($baseline - 22), $width - 96, $height - 12, $ink);
        imageline($img, $width - 96, $height - 12, $width - 30, (int) ($baseline + 6), $ink);

        mt_srand();

        ob_start();
        imagepng($img);
        $binary = ob_get_clean();
        imagedestroy($img);

        $path = 'signatures/service_' . $service->id . '_' . $party . '_' . now()->format('YmdHis') . '.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function report(
        array $created,
        FreelancerServiceBatch $sent,
        FreelancerServiceBatch $awaiting,
        FreelancerServiceBatch $decided,
    ): void {
        $labels = [
            'nova' => 'sem assinatura — kiosk (operador) colhe a assinatura do freelancer',
            'freelancer' => 'só o freelancer assinou — fila de assinatura do coordenador no kiosk',
            'assinado' => 'as duas assinaturas, fora de lote — disponíveis para montar lote',
            'enviado' => "no lote #{$sent->id} (aguardando gerência) — analisar na web",
            'diretoria' => "no lote #{$awaiting->id} — aguardando o código da diretoria",
            'aprovado' => "aprovado pela diretoria no lote #{$decided->id} — aparece no Financeiro",
            'recusado' => "recusado pela gerência no lote #{$decided->id} — voltou para o coordenador",
        ];

        $this->command->newLine();
        $this->command->info('Contratos de demonstração criados:');

        foreach ($labels as $stage => $label) {
            $ids = collect($created[$stage] ?? [])->pluck('id')->implode(', ');
            $this->command->line('  ' . str_pad('#' . $ids, 10) . $label);
        }

        // Sem SMTP configurado o e-mail não sai, então os códigos do lote que
        // aguarda a diretoria vão aqui — é o que permite testar a tela do PIN.
        $this->command->newLine();
        $this->command->info("Códigos da diretoria para o lote #{$awaiting->id} (normalmente só existem no e-mail):");
        $this->command->line('  APROVAR: ' . $awaiting->director_approve_pin);
        $this->command->line('  RECUSAR: ' . $awaiting->director_reject_pin);
    }
}
