<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Um diretor ligado a um centro de custo.
 *
 * É a **sugestão** que a tela do gerente carrega, não a decisão. Quem define os
 * aprovadores de uma ordem é a Gerência, no nível 2 — ver a migration desta
 * tabela. Aqui só mora o padrão: "compras deste centro de custo costumam ser
 * decididas por estas pessoas".
 *
 * O centro de custo é o código no Questor. Não há relação com uma tabela local
 * porque não existe uma: o cadastro vive no FUNCSIDERURG e é lido sob demanda.
 */
class CostCenterApprover extends Model
{
    protected $table = 'purchase_order_cost_center_approvers';

    protected $fillable = [
        'cd_centro_custo',
        'user_id',
        'active',
    ];

    protected $casts = [
        'cd_centro_custo' => 'integer',
        'user_id' => 'integer',
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Os diretores sugeridos para um conjunto de centros de custo — o formato
     * em que a tela do gerente precisa deles.
     *
     * Devolve os usuários **sem repetir**: um diretor ligado a dois dos centros
     * de custo da mesma ordem é uma pessoa só na lista, não duas.
     *
     * @param  iterable<int>  $costCenters
     * @return Collection<int, User>
     */
    public static function suggestedFor(iterable $costCenters): Collection
    {
        $codigos = collect($costCenters)->filter()->map(fn($c) => (int) $c)->unique();

        if ($codigos->isEmpty()) {
            return collect();
        }

        return self::active()
            ->whereIn('cd_centro_custo', $codigos->all())
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * O mapa completo para a tela de cadastro: centro de custo → ids dos
     * diretores ativos nele.
     *
     * @return Collection<int, array<int, int>>
     */
    public static function mapByCostCenter(): Collection
    {
        return self::active()
            ->get()
            ->groupBy('cd_centro_custo')
            ->map(fn($linhas) => $linhas->pluck('user_id')->all());
    }

    /**
     * Regrava os diretores de um centro de custo a partir da tela.
     *
     * Desmarcar **desativa** em vez de apagar: a linha continua existindo para
     * quem for entender, meses depois, por que aquela ordem foi parar com
     * aquele diretor. Remarcar reativa a mesma linha — é o que o índice único
     * de (centro de custo, usuário) garante.
     *
     * @param  array<int, int>  $userIds
     */
    public static function sync(int $cdCentroCusto, array $userIds): void
    {
        $ids = collect($userIds)->map(fn($id) => (int) $id)->unique();

        foreach ($ids as $userId) {
            self::updateOrCreate(
                ['cd_centro_custo' => $cdCentroCusto, 'user_id' => $userId],
                ['active' => true],
            );
        }

        self::where('cd_centro_custo', $cdCentroCusto)
            ->when($ids->isNotEmpty(), fn($q) => $q->whereNotIn('user_id', $ids->all()))
            ->update(['active' => false]);
    }
}
