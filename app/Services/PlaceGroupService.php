<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PlaceGroup;
use App\Support\ImageSource;


class PlaceGroupService
{
    /** As duas imagens do grupo: cada uma por arquivo ou por link (ImageSource). */
    public const IMAGE_FIELDS = ['image_vertical', 'image_horizontal'];

    /**
     * Valores das imagens a gravar — arquivo enviado, senão link, senão o que
     * já estava. Chamado pelo controller antes da transação, para que link
     * inválido volte ao formulário como erro de validação.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resolveImages(Request $request, ?PlaceGroup $placeGroup = null): array
    {
        $images = [];

        foreach (self::IMAGE_FIELDS as $field) {
            $images[$field] = ImageSource::resolve($request, $field, $placeGroup?->{$field});
        }

        return $images;
    }

    public function store(Request $request, array $images = []){
        DB::beginTransaction();
        try {
            // Os campos de imagem vêm de $images: no request estão o arquivo
            // (UploadedFile) e o link, que não são o valor da coluna.
            $placeGroup = PlaceGroup::create(array_merge($this->withoutImageInputs($request), $images));

            foreach ($request->weekdays ?? [] as $weekdayId) {
                $placeGroup->weekdays()->attach($weekdayId);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show($placeGroup){
        // Logic to show a PlaceGroup
        return $placeGroup;
    }

    public function update(Request $request, $placeGroup, array $images = []){
        DB::beginTransaction();
        try {
            $validated = array_merge($this->withoutImageInputs($request), $images);

            PlaceGroup::find($placeGroup->id)->update($validated);

            //Sync weekdays
            $placeGroup->weekdays()->sync($validated['weekdays'] ?? []);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function withoutImageInputs(Request $request): array
    {
        $inputs = [];
        foreach (self::IMAGE_FIELDS as $field) {
            $inputs[] = $field;
            $inputs[] = $field . '_url';
        }

        return $request->except($inputs);
    }
}
