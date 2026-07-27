<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardTemplateRequest;
use App\Http\Requests\UpdateCardTemplateRequest;
use App\Models\CardTemplate;
use Illuminate\Http\UploadedFile;

class CardTemplateController extends Controller
{
    public function index()
    {
        $templates = CardTemplate::orderBy('name')->get();

        return view('card-templates.index', compact('templates'));
    }

    public function create()
    {
        return view('card-templates.create');
    }

    public function store(StoreCardTemplateRequest $request)
    {
        $data = $request->validated();
        $data['front_image'] = $this->storeUploadedImage($request->file('front_image'), 'card_front_');
        $data['back_image'] = $this->storeUploadedImage($request->file('back_image'), 'card_back_');
        $data['layout'] = json_decode($request->input('layout'), true);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = $data['created_by'];

        $template = CardTemplate::create($data);

        return redirect()->route('card-templates.index')
            ->with('success', 'Modelo "' . $template->name . '" cadastrado com sucesso.');
    }

    public function edit(CardTemplate $cardTemplate)
    {
        return view('card-templates.edit', ['template' => $cardTemplate]);
    }

    public function update(UpdateCardTemplateRequest $request, CardTemplate $cardTemplate)
    {
        $data = $request->validated();

        if ($request->hasFile('front_image')) {
            $data['front_image'] = $this->storeUploadedImage($request->file('front_image'), 'card_front_');
        }

        if ($request->hasFile('back_image')) {
            $data['back_image'] = $this->storeUploadedImage($request->file('back_image'), 'card_back_');
        }

        $data['layout'] = json_decode($request->input('layout'), true);
        $data['updated_by'] = auth()->id();

        $cardTemplate->update($data);

        return redirect()->route('card-templates.index')
            ->with('success', 'Modelo atualizado com sucesso.');
    }

    public function destroy(CardTemplate $cardTemplate)
    {
        $name = $cardTemplate->name;
        $cardTemplate->delete();

        return redirect()->route('card-templates.index')
            ->with('success', 'Modelo "' . $name . '" excluído com sucesso.');
    }

    private function storeUploadedImage(UploadedFile $file, string $prefix): string
    {
        $imageName = $prefix . time() . '_' . uniqid() . '.' . $file->extension();
        $file->move(public_path('images'), $imageName);

        return $imageName;
    }
}
