<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\SocialBeverage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de bebidas das reservas sociais (permissão `reservas.configurar`).
 *
 * Mudar preço ou desativar não mexe nas reservas já feitas: cada reserva
 * guarda a cópia do item (SocialBookingItem).
 */
class SocialBeverageController extends Controller
{
    public function index(Request $request): View
    {
        return view('location.social.beverages', [
            'beverages' => SocialBeverage::orderBy('category')->orderByDesc('active')->orderBy('name')->get(),
            'editing' => $request->filled('edit') ? SocialBeverage::find($request->input('edit')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SocialBeverage::create($this->validated($request));

        return redirect()->route('social-booking.beverages.index')->with('success', 'Bebida cadastrada.');
    }

    public function update(Request $request, SocialBeverage $beverage): RedirectResponse
    {
        $beverage->update($this->validated($request));

        return redirect()->route('social-booking.beverages.index')->with('success', 'Bebida atualizada.');
    }

    public function destroy(SocialBeverage $beverage): RedirectResponse
    {
        $beverage->delete();

        return redirect()->route('social-booking.beverages.index')->with('success', 'Bebida removida do catálogo.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', array_keys(SocialBeverage::CATEGORIES)),
            'unit' => 'nullable|string|max:60',
            'mv_code' => 'nullable|string|max:60',
            'price' => 'required|numeric|min:0|max:99999',
            'tables_per_unit' => 'nullable|integer|min:0|max:100',
            'chairs_per_unit' => 'nullable|integer|min:0|max:100',
            'max_quantity' => 'nullable|integer|min:1|max:9999',
        ], [], [
            'mv_code' => 'Código MV',
            'tables_per_unit' => 'mesas por unidade',
            'chairs_per_unit' => 'cadeiras por unidade',
            'max_quantity' => 'máximo por reserva',
        ]);

        // Bebida não traz mesa nem cadeira, mesmo que o campo venha preenchido.
        $furniture = $data['category'] === SocialBeverage::CATEGORY_FURNITURE;
        $data['tables_per_unit'] = $furniture ? (int) ($data['tables_per_unit'] ?? 0) : 0;
        $data['chairs_per_unit'] = $furniture ? (int) ($data['chairs_per_unit'] ?? 0) : 0;
        $data['max_quantity'] = $data['max_quantity'] ?? null;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
