<?php

namespace App\Http\Controllers;

use App\Models\Information;
use App\Models\DataInfo;
use App\Models\Tag;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInformationRequest;
use App\Http\Requests\UpdateInformationRequest;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InformationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        // Só a versão vigente de cada informação, filtrada no banco. Antes isso
        // era feito em PHP (carregava todas as versões de todas as informações
        // para descartar quase tudo em seguida), o que impedia paginar.
        $query = DataInfo::with('user', 'information', 'tags')
            ->whereIn('id', function ($sub) {
                $sub->from('data_infos')
                    ->selectRaw('MAX(id)')
                    ->whereNull('deleted_at')
                    ->groupBy('information_id');
            })
            ->whereHas('information');

        // Busca no servidor: com paginação, filtrar só no navegador acharia
        // apenas o que está na página aberta.
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('responsible', 'like', $like)
                  ->orWhere('status', 'like', $like)
                  ->orWhere('location', 'like', $like)
                  ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $like));
            });
        }

        $infos = $query->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        foreach ($infos as $info) {
            $info->description = $this->previewDescription($info->description);

            $responsibleNames = array_values(array_filter(
                explode(';', $info->responsible ?? ''),
                fn ($value) => $value !== ''
            ));
            $info->responsible = implode(', ', $responsibleNames);

            $info->prices = $this->pricePackages($info);
        }

        return View('information.index', compact('infos', 'search'));
    }

    /**
     * Texto simples (sem HTML) truncado em ~250 caracteres para o card da listagem.
     */
    private function previewDescription(?string $description): string
    {
        $text = strip_tags($description ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        // String vazia (e não "<i></i>") para o card conseguir omitir o bloco.
        if ($text === '') {
            return '';
        }

        $truncated = mb_substr($text, 0, 250);
        $suffix = mb_strlen($text) > 250 ? '...' : '';

        return '<i>' . e($truncated) . $suffix . '</i>';
    }

    /**
     * Monta as linhas "Título R$ X (Sócio) | R$ Y (Não Sócio)" a partir dos
     * três campos ';'-separados. Usa o índice de name_price como referência
     * e ignora pacotes totalmente vazios (o delimitador final de
     * concatenateArrayValues() sempre deixa um item vazio sobrando).
     */
    private function pricePackages(DataInfo $info): array
    {
        $names = explode(';', $info->name_price ?? '');
        $associated = explode(';', $info->price_associated ?? '');
        $notAssociated = explode(';', $info->price_not_associated ?? '');

        $packages = [];
        foreach ($names as $index => $name) {
            $assoc = $associated[$index] ?? '';
            $notAssoc = $notAssociated[$index] ?? '';

            if ($name === '' && $assoc === '' && $notAssoc === '') {
                continue;
            }

            $packages[] = $name
                . ' R$ ' . ($assoc !== '' ? $assoc : '0') . ' (Sócio)'
                . ' | R$ ' . ($notAssoc !== '' ? $notAssoc : '0') . ' (Não Sócio)';
        }

        return $packages;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return View('information.create');
    }

    public function concatenateArrayValues($array, $delimiter = ';')
    {
        return implode($delimiter, $array) . $delimiter;
    }

    /**
     * Store a newly created resource in storage.
     *
     * Atende tanto a criação (POST sem information_id) quanto a edição
     * (POST com information_id, que gera uma nova versão em data_infos —
     * o histórico nunca sobrescreve uma versão existente).
     */
    public function store(StoreInformationRequest $request)
    {
        $data = $request->all();

        $isNewInformation = !isset($data['information_id']);
        abort_unless(
            auth()->user()->can($isNewInformation ? 'create information' : 'edit information'),
            403
        );

        try {
            $fieldsToConcatenate = ['name_price', 'price_associated', 'price_not_associated', 'responsible', 'responsible_contact'];

            foreach ($fieldsToConcatenate as $field) {
                if (isset($data[$field]) && is_array($data[$field])) {
                    // O ';' é o delimitador do campo: se o próprio valor tiver
                    // um, a linha inteira se parte em duas na leitura e todos
                    // os campos paralelos saem desalinhados daí em diante.
                    $values = array_map(
                        fn ($value) => str_replace(';', ',', (string) $value),
                        $data[$field]
                    );

                    $data[$field] = $this->concatenateArrayValues($values);
                }
            }

            $data['day_hour'] = ';';
            if (isset($data['day']) && is_array($data['day'])) {
                $day_hour = null;
                foreach ($data['day'] as $i => $day) {
                    if ($day === '#') {
                        continue;
                    }
                    $day_hour .= $day . ',' . ($data['start_hour'][$i] ?? '') . ',' . ($data['end_hour'][$i] ?? '') . ';';
                }
                $data['day_hour'] = $day_hour ?? ';';
            }

            $data['description'] = HtmlSanitizer::clean($data['description'] ?? '');
            $data['created_by'] = auth()->user()->id;

            if ($isNewInformation) {
                //TODO: Implementar a criação de privacy
                //0 - Public
                //1 - Setor
                //2 - Private
                $info = Information::create([
                    'privacy' => '0',
                    'created_by' => $data['created_by'],
                ]);

                $data['information_id'] = $info->id;
            }

            if ($request->hasFile('image')) {
                $imageName = $request->image->hashName();
                $request->image->move(public_path('images'), $imageName);
                $data['image'] = $imageName;
            } elseif ($request->boolean('remove_image')) {
                $data['image'] = null;
            } else {
                // Sem arquivo novo, a nova versão herda a imagem da anterior.
                $old_data = DataInfo::where('information_id', $data['information_id'])->orderBy('created_at', 'desc')->first();
                $data['image'] = $old_data->image ?? null;
            }

            $dataInfo = DataInfo::create($data);
            $this->syncTags($dataInfo, $request->input('tags', []));
        } catch (\Throwable $e) {
            Log::error('Falha ao salvar informação do InfoClube', [
                'user_id' => auth()->id(),
                'exception' => $e,
            ]);

            return back()->withInput()->withErrors([
                'error' => 'Não foi possível salvar a informação. Tente novamente ou avise a TI se o problema continuar.',
            ]);
        }

        return redirect()->route('information.index');
    }

    /**
     * Vincula as tags à versão recém-criada, criando as que ainda não existem.
     *
     * Os nomes são normalizados por Tag::normalize (minúsculas, sem espaços nas
     * pontas), então "Natação", "natação " e "NATAÇÃO" são a mesma tag — a
     * mesma tabela usada pelos avisos.
     *
     * @param  array<int, string>  $tags
     */
    private function syncTags(DataInfo $dataInfo, array $tags): void
    {
        $ids = collect($tags)
            ->map(fn ($name) => Tag::normalize((string) $name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(['name' => $name])->id)
            ->all();

        $dataInfo->tags()->sync($ids);
    }

    /**
     * Converte os campos ';'-separados em listas de linhas já alinhadas.
     *
     * O formato antigo (explodir cada coluna e passar array_filter em cada uma)
     * desalinhava os campos paralelos: array_filter preserva as chaves, então
     * bastava um título de preço vazio para que name_price[1] passasse a casar
     * com price_associated[0] na view — era daí que vinham os "dados no lugar
     * errado". Aqui a linha inteira é montada de uma vez e só é descartada
     * quando todos os seus campos estão vazios.
     */
    private function structureFields(DataInfo $info): void
    {
        $info->price_rows = $this->zipRows([
            'name' => $info->name_price,
            'associated' => $info->price_associated,
            'not_associated' => $info->price_not_associated,
        ]);

        $info->responsible_rows = $this->zipRows([
            'name' => $info->responsible,
            'contact' => $info->responsible_contact,
        ]);

        $info->schedule_rows = $this->scheduleRows($info->day_hour);
    }

    /**
     * @param  array<string, string|null>  $columns  chave do resultado => coluna crua
     * @return array<int, array<string, string>>
     */
    private function zipRows(array $columns): array
    {
        $parts = [];
        $length = 0;

        foreach ($columns as $key => $raw) {
            $parts[$key] = ($raw === null || $raw === '') ? [] : explode(';', $raw);
            $length = max($length, count($parts[$key]));
        }

        $rows = [];
        for ($i = 0; $i < $length; $i++) {
            $row = [];
            $isEmpty = true;

            foreach ($parts as $key => $values) {
                $value = trim($values[$i] ?? '');
                $row[$key] = $value;

                if ($value !== '') {
                    $isEmpty = false;
                }
            }

            if (!$isEmpty) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * day_hour guarda "dia,inicio,fim" por item. Índices ausentes viram string
     * vazia em vez de "Undefined array key" — há registros antigos gravados com
     * menos de três partes.
     *
     * @return array<int, array{day: string, start: string, end: string}>
     */
    private function scheduleRows(?string $dayHour): array
    {
        $rows = [];

        foreach (explode(';', $dayHour ?? '') as $item) {
            if (trim($item) === '') {
                continue;
            }

            $parts = explode(',', $item);

            $rows[] = [
                'day' => trim($parts[0] ?? ''),
                'start' => trim($parts[1] ?? ''),
                'end' => trim($parts[2] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Display the specified resource.
     */
    public function show(DataInfo $information)
    {
        $info = DataInfo::with('information', 'user', 'tags')
                            ->where('information_id', $information->information_id)
                            ->orderBy('created_at', 'desc')
                            ->first();

        // $info->information vem null se a Information pai estiver soft-deleted
        // (escopo global do SoftDeletes já filtra o relacionamento).
        abort_if(!$info || !$info->information, 404);

        $this->structureFields($info);

        return View('information.show', compact('info'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DataInfo $information)
    {
        abort_if(!$information->information, 404);

        $info = $information->load('tags');
        $this->structureFields($info);

        return View('information.edit', compact('info'));
    }

    /**
     * Update the specified resource in storage.
     *
     * Usado pelo botão "Tornar versão atual" do histórico: copia os dados da
     * versão escolhida para uma nova linha em data_infos (mesma regra de
     * versionamento do store()) e marca o usuário atual como autor da
     * restauração.
     */
    public function update(UpdateInformationRequest $request, DataInfo $information)
    {
        abort_if(!$information->information, 404);

        $data = array_intersect_key($information->toArray(), array_flip($information->getFillable()));
        $data['created_by'] = auth()->user()->id;
        $data['created_at'] = now();

        $new = DataInfo::create($data);

        // Restaurar uma versão precisa restaurar também as tags daquela época,
        // senão a nova versão nasce sem nenhuma.
        $this->syncTags($new, $information->tags->pluck('name')->all());

        return redirect()->route('information.show', $new->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Information $information)
    {
        $information->delete();

        return redirect()->route('information.index');
    }

    public function history($information)
    {
        $info = DataInfo::with('information', 'user', 'tags')
                            ->where('information_id', $information)
                            ->orderBy('created_at', 'desc')
                            ->get();

        abort_if($info->isEmpty() || !$info->first()->information, 404);

        foreach ($info as $i) {
            $this->structureFields($i);
        }

        return View('information.history', compact('info'));
    }
}
