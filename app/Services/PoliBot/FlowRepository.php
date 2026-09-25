<?php

namespace App\Services\PoliBot;

use App\Models\BotFlow;
use App\Models\BotFlowVersion;
use Illuminate\Support\Facades\DB;

/**
 * Gravação de fluxos pela tela: as regras que valem para o conjunto, e não
 * só para um fluxo isolado (o FlowDefinition cuida dessas).
 *
 *   - só um fluxo ATIVO pode abrir em "qualquer primeira mensagem" —
 *     com dois, o segundo nunca seria escolhido e ninguém saberia por quê;
 *   - todo goto_flow aponta para um fluxo que existe;
 *   - fluxo que é destino de goto de outro não pode ser apagado.
 */
class FlowRepository
{
    /**
     * Problemas que impedem gravar (ou ativar) este fluxo. Vazio = pode.
     *
     * @param array<string, mixed> $definicao
     * @return string[]
     */
    public function errors(string $slug, array $definicao, bool $ativo, ?int $ignorarId = null): array
    {
        $fluxo = new FlowDefinition($slug, $definicao);
        $erros = $fluxo->errors();

        foreach ($fluxo->gotoTargets() as $alvo) {
            if ($alvo === $slug) {
                $erros[] = 'O fluxo manda para ele mesmo (ir para outro fluxo). Use "próximo passo" dentro do fluxo.';
            } elseif (!BotFlow::where('slug', $alvo)->exists()) {
                $erros[] = "Um passo manda para o fluxo \"{$alvo}\", que não existe.";
            }
        }

        if ($ativo && $fluxo->opensOnAnyMessage()) {
            $outro = BotFlow::where('active', true)
                ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
                ->get()
                ->first(fn (BotFlow $f) => $f->flow()->opensOnAnyMessage());

            if ($outro) {
                $erros[] = "O fluxo \"{$outro->name}\" já abre em qualquer primeira mensagem. Só um fluxo ativo pode fazer isso.";
            }
        }

        return $erros;
    }

    /**
     * @param array<string, mixed> $definicao já limpa (FlowDefinition::clean)
     */
    public function save(?BotFlow $fluxo, string $slug, string $nome, bool $ativo, array $definicao, ?object $usuario): BotFlow
    {
        return DB::transaction(function () use ($fluxo, $slug, $nome, $ativo, $definicao, $usuario) {
            $fluxo ??= new BotFlow(['slug' => $slug]);
            $fluxo->fill(['name' => $nome, 'active' => $ativo, 'definition' => $definicao]);
            $fluxo->save();

            $this->registrarVersao($fluxo, $usuario);

            return $fluxo;
        });
    }

    public function registrarVersao(BotFlow $fluxo, ?object $usuario): void
    {
        BotFlowVersion::create([
            'bot_flow_id' => $fluxo->id,
            'name' => $fluxo->name,
            'active' => $fluxo->active,
            'definition' => $fluxo->definition,
            'user_id' => $usuario->id ?? null,
            'user_name' => $usuario->name ?? null,
        ]);
    }

    /** @return string[] nomes dos fluxos que mandam para este (goto_flow) */
    public function referencedBy(BotFlow $fluxo): array
    {
        return BotFlow::where('id', '!=', $fluxo->id)
            ->get()
            ->filter(fn (BotFlow $f) => in_array($fluxo->slug, $f->flow()->gotoTargets(), true))
            ->pluck('name')
            ->values()
            ->all();
    }
}
