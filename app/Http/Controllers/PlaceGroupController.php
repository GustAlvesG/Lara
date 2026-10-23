<?php

namespace App\Http\Controllers;

use App\Models\PlaceGroup;
use App\Http\Requests\StorePlaceGroupRequest;
use App\Http\Requests\UpdatePlaceGroupRequest;
use Illuminate\Http\Request;
use App\Models\Place;
use App\Models\ScheduleRules;
use Illuminate\Support\Facades\DB;
use App\Services\PlaceGroupService;
use App\Services\ScheduleRulesService;
use App\Models\Contactor;
use App\Support\ImageSource;

class PlaceGroupController extends Controller
{

    public function __construct(PlaceGroupService $placeGroupService, ScheduleRulesService $scheduleRulesService)
    {
        $this->placeGroupService = $placeGroupService;
        $this->scheduleRulesService = $scheduleRulesService;
    }
    /**
     * Display a listing of the resource.
     */
    public function indexByCategory($category)
    {
        $groups = PlaceGroup::where('category', $category)->get();

        //Load places relationship
        foreach ($groups as $group) {
            $group->places;
        }
        //For each place, keep only id and name
        foreach ($groups as $group) {
            foreach ($group->places as $place) {
                $placeData = [
                    'id' => $place->id,
                    'name' => $place->name,
                ];
                $place->only = $placeData;
                unset($place->pivot);
            }
        }
        //Order by name
        $groups = $groups->sortBy('name');

        //Get the images
        foreach ($groups as $group) {
            //If has not "imgur" in the image path, add it
            if (!str_contains($group->image_vertical, 'http')) {
                $group->image_vertical = asset('images/' . $group->image_vertical);
            }
            if (!str_contains($group->image_horizontal, 'http')) {
                $group->image_horizontal = asset('images/' . $group->image_horizontal);
            }
        }

        return response()->json($groups);
    }

    public function index_api($none = null)
    {
        // Só quadras: o app lista estes grupos na reserva por horário. Os
        // sociais têm rota própria (`/api/social/groups`).
        $groups = PlaceGroup::sport()->get();
        //Order by name
        $groups = $groups->sortBy('name');

        //Get the images
        foreach ($groups as $group) {
            //If has not "imgur" in the image path, add it
            if (!str_contains($group->image_vertical, 'http')) {
                $group->image_vertical = asset('images/' . $group->image_vertical);
            }
            if (!str_contains($group->image_horizontal, 'http')) {
                $group->image_horizontal = asset('images/' . $group->image_horizontal);
            }
        }

        return response()->json($groups);
    }

    public function index(){
        //Get all place groups and their places
        $groups = PlaceGroup::all();

        //Order by name
        $groups = $groups->sortBy('name');

        
        return view('location.placeGroup.index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('location.placeGroup.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Fora do try: link de imagem inválido volta ao formulário como erro
        // de validação, no campo dele.
        $images = $this->placeGroupService->resolveImages($request);

        try {
            $this->placeGroupService->store($request, $images);
        } catch (\Exception $e) {
            // Handle the exception or log it
            return redirect()->back()->withErrors(['error' => 'Failed to create Place Group: ' . $e->getMessage()]);
        }

        return redirect()->route('place-group.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(PlaceGroup $placeGroup)
    {
        try {
            $rules = $this->scheduleRulesService->getFilteredRulesByPlaceGroup($placeGroup);

        } catch (\Exception $e) {
            // Handle the exception or log it
            return redirect()->back()->withErrors(['error' => 'Failed to show Place Group: ' . $e->getMessage()]);
        }
        
        $ids = [];
        foreach ($placeGroup->weekdays as $weekday) {
            $ids[] = $weekday->id;
        }
        $placeGroup->weekdays = $ids;

        //Order places by name and status_id
        $placeGroup->places = $placeGroup->places->sortBy([['status_id', 'asc'], ['name', 'asc']]);


        return view('location.placeGroup.show', [
            'item' => $placeGroup,  
            'rules' => $rules,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PlaceGroup $placeGroup)
    {
        return view('location.placeGroup.edit', [
            'group' => $placeGroup,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlaceGroupRequest $request, PlaceGroup $placeGroup)
    {
        $images = $this->placeGroupService->resolveImages($request, $placeGroup);

        try {
            $this->placeGroupService->update($request, $placeGroup, $images);
        } catch (\Exception $e) {
            // Handle the exception or log it
            return redirect()->back()->withErrors(['error' => 'Failed to update Place Group: ' . $e->getMessage()]);
        }

        return redirect()->route('place-group.index');
    }


    public function createScheduleRule($group_id)
    {
        $group = PlaceGroup::find($group_id);
        $group->places;
        return view('location.rule.create', [
            'group' => $group,
        ]);
    }

    
    public function storeScheduleRule(Request $request)
    {
        try{
            $validated = $request->all();

            $response = $this->
            
            $rule = ScheduleRules::create($validated);

            //Check if has weekdays
            if (isset($validated['weekdays']) && !empty($validated['weekdays'])) {
                //Attach the weekdays to the rule
                $rule->weekdays()->attach($validated['weekdays']);
            }



            //Check if "all" in places, and remove
            $index = array_search('all', $validated['places']);
            if ($index !== false) {
                unset($validated['places'][$index]);
            }


            foreach ($validated['places'] as $place_id) {
                //Create the schedule rule with the relastionship to the place
                $place = Place::find($place_id);
                $place->scheduleRules()->attach($rule->id);
            }

        } catch (\Exception $e) {
            // Handle the exception or log it
            return redirect()->back()->withErrors(['error' => 'Failed to create Schedule Rule: ' . $e->getMessage()]);
        }

        return redirect()->route('place-group.show', ['place_group' => $validated['place_group_id']]);
    }

    public function editScheduleRule($id)
    {
        $rule = ScheduleRules::find($id);
        $rule->weekdays;

        foreach ($rule->weekdays as $weekday) {
            unset($weekday->pivot);
        }

        $places = [];
        $rule->places;

        //Remove pivot from places
        foreach ($rule->places as $place) {
            unset($place->pivot);
        }

        $group_id = $rule->places->first()->place_group_id;
        $group = PlaceGroup::find($group_id);
        $group->places;
        unset($group->pivot);


        return view('location.rule.edit', [
            'rule' => $rule,
            'group' => $group,
        ]);
    }

    public function scheduleRules($id)
    {
        $placeGroup = PlaceGroup::where('id', $id)->first();
        $placeGroup->load('places.scheduleRules');

        return response()->json($rules);
    }

    public function updateScheduleRule(Request $request, $id){
        $validated = $request->all();
        
        $rule = ScheduleRules::find($id);



        $rule->update($validated);
        
        DB::transaction(function () use ($rule, $validated) {
    
        // 1. Lógica dos Dias da Semana (Weekdays)
        // O método sync já lida com arrays vazios (fazendo o detach de tudo se necessário).
        // O operador '??' garante que se não existir, passa um array vazio.
        $rule->weekdays()->sync($validated['weekdays'] ?? []);

        // 2. Lógica dos Lugares (Places)
        // Remove o valor 'all' do array, se existir
        if (isset($validated['places'])) {
            $placesIds = array_filter($validated['places'], function ($value) {
                return $value !== 'all';
            });

            // O sync substitui o detach() e o loop foreach.
            // Ele sincroniza os IDs passados com a regra atual.
            $rule->places()->sync($placesIds);
        } else {
            // Se não houver places no validated, remove todos os vínculos
            $rule->places()->detach();
        }
    });
        return redirect()->route('place-group.show', ['place_group' => $rule->places->first()->place_group_id]);
    }

    public function destroyScheduleRule($id)
    {
        $rule = ScheduleRules::find($id);

        //Detach all weekdays
        $rule->weekdays()->detach();

        $group_id = $rule->places->first()->place_group_id;
        
        //Detach all places
        $rule->places()->detach();

        //Delete the rule
        $rule->delete();

        return redirect()->route('place-group.show', ['place_group' => $group_id]);
    }

    public function createPlace($group_id)
    {
        $group = PlaceGroup::find($group_id);

        $group->load('places.scheduleRules');

        $rules = [];

        $rules = $this->filterRules($group);


        return view('location.place.create', [
            'place_group' => $group,
            'rules'       => $rules,
            'contactors'  => Contactor::orderBy('name')->get(),
            'conflictOptions' => Place::with('group')->get(),
            'selectedConflicts' => [],
        ]);
    }

    public function storePlace(Request $request)
    {
        
        $validated = $request->all();
        // Arquivo enviado ou link web da imagem (ImageSource).
        $validated['image'] = ImageSource::resolve($request, 'image');

        $group = PlaceGroup::find($validated['place_group_id']);

        $place = Place::create(array_merge([
            'name'           => $validated['name'],
            'contactor_id'   => $this->contactorIdFor($request),
            // Autoatendimento de iluminação: liberado espaço a espaço, e nunca
            // por engano — o formulário manda um hidden 0 junto do checkbox.
            'self_service_lighting' => $request->boolean('self_service_lighting'),
            'place_group_id' => $validated['place_group_id'],
            'image'          => $validated['image'] ?? null,
            'price'          => $validated['price'],
            'status_id'      => $validated['status_id'],
        ], $this->socialAttributes($request, $group)));

        $this->syncConflicts($place, $request->input('conflicts', []));

        //Check if has rules
        if (!isset($validated['rules']) || empty($validated['rules'])) {
            return redirect()->route('place-group.show', ['place_group' => $validated['place_group_id']]);
        }
        foreach ($validated['rules'] as $rule_id) {
            //Create the schedule rule with the relastionship to the place
            $place->scheduleRules()->attach($rule_id);
        }

        return redirect()->route('place-group.show', ['place_group' => $validated['place_group_id']]);
    }

    /**
     * Campos do espaço social. Espaço social não tem contator nem
     * autoatendimento de luz: a reserva dele ocupa o dia inteiro, e um
     * contator ligado deixaria a luz acesa 24 horas pela automação.
     */
    protected function socialAttributes(Request $request, ?PlaceGroup $group): array
    {
        if (! $group || ! $group->isSocial()) {
            return [];
        }

        $data = $request->validate([
            'description' => 'nullable|string|max:2000',
            'amenities' => 'nullable|string|max:2000',
            'capacity' => 'nullable|integer|min:1|max:100000',
            'max_guests' => 'required|integer|min:0|max:100000',
            'extra_guests' => 'nullable|integer|min:0|max:100000',
            'extra_guest_price' => 'nullable|numeric|min:0|max:99999',
            'base_tables' => 'nullable|integer|min:0|max:10000',
            'base_chairs' => 'nullable|integer|min:0|max:100000',
            'event_duration_hours' => 'nullable|integer|min:1|max:24',
            'event_earliest_start' => 'nullable|date_format:H:i',
            'event_latest_start' => 'nullable|date_format:H:i',
        ]);

        return [
            'description' => $data['description'] ?? null,
            'amenities' => $data['amenities'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'max_guests' => (int) $data['max_guests'],
            'extra_guests' => (int) ($data['extra_guests'] ?? 0),
            'extra_guest_price' => (float) ($data['extra_guest_price'] ?? 0),
            'base_tables' => $data['base_tables'] ?? null,
            'base_chairs' => $data['base_chairs'] ?? null,
            'event_duration_hours' => $data['event_duration_hours'] ?? null,
            'event_earliest_start' => $data['event_earliest_start'] ?? null,
            'event_latest_start' => $data['event_latest_start'] ?? null,
            'contactor_id' => null,
            'self_service_lighting' => false,
        ];
    }

    /**
     * Grava os espaços que não podem ser usados no mesmo dia que este. O par
     * vale nos dois sentidos, então a lista do formulário substitui todos os
     * pares em que este espaço aparece — dos dois lados.
     */
    protected function syncConflicts(Place $place, array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0 && $id !== $place->id)->unique()->values();

        DB::transaction(function () use ($place, $ids) {
            DB::table('place_conflicts')
                ->where('place_id', $place->id)
                ->orWhere('conflicting_place_id', $place->id)
                ->delete();

            $now = now();
            DB::table('place_conflicts')->insert($ids->map(fn ($id) => [
                'place_id' => $place->id,
                'conflicting_place_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    /**
     * Contator do espaço depois do envio do formulário.
     *
     * Só quem gerencia o Home Assistant troca o contator. O select desabilitado no
     * formulário não impede que o campo chegue num POST montado à mão, então quem
     * não tem a permissão mantém o valor atual (nenhum, ao criar).
     */
    protected function contactorIdFor(Request $request, ?Place $place = null): ?int
    {
        if (! $request->user()?->can('home-assistant') || ! $request->has('contactor_id')) {
            return $place?->contactor_id;
        }

        return $request->input('contactor_id') ? (int) $request->input('contactor_id') : null;
    }

    public function editPlace($place_id)
    {

        // $group = PlaceGroup::find($group_id);
        $place = Place::find($place_id);

        $rules = [];

        $rules = $this->filterRules($place->group);

        unset($place->pivot);
        unset($place->group);



        return view('location.place.edit', [
            'place'      => $place,
            'rules'      => $this->filterRules($place->group),
            'contactors' => Contactor::orderBy('name')->get(),
            'conflictOptions' => Place::with('group')->where('id', '!=', $place->id)->get(),
            'selectedConflicts' => Place::conflictingPlaceIds($place->id),
        ]);
    }

    public function updatePlace(Request $request, $place_id)
    {
        $validated = $request->all();

        $place = Place::find($place_id);

        // Arquivo novo ou link novo trocam a imagem; sem nenhum, fica a atual.
        $validated['image'] = ImageSource::resolve($request, 'image', $place->image);

        $place->update(array_merge([
            'name'         => $validated['name'],
            'contactor_id' => $this->contactorIdFor($request, $place),
            'self_service_lighting' => $request->boolean('self_service_lighting'),
            'price'        => $validated['price'],
            'status_id'    => $validated['status_id'],
            'image'        => $validated['image'] ?? null,
        ], $this->socialAttributes($request, $place->group)));

        $this->syncConflicts($place, $request->input('conflicts', []));

        $place->scheduleRules()->detach();
        //Check if has rules
        if (!isset($validated['rules']) || empty($validated['rules'])) {
            return redirect()->route('place-group.show', ['place_group' => $place->place_group_id]);
        } else {
            //Detach all rules
            foreach ($validated['rules'] as $rule_id) {
                //Create the schedule rule with the relastionship to the place
                $place->scheduleRules()->attach($rule_id);
            }
        }

        return redirect()->route('place-group.show', ['place_group' => $place->place_group_id]);
    }

    public function destroyPlace($place_id)
    {
        
        $place = Place::find($place_id);

        //Detach all rules
        $place->scheduleRules()->detach();

        //Delete the place
        $place->delete();

        return redirect()->route('place-group.show', ['place_group' => $place->place_group_id]);
    }

    
    

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlaceGroup $placeGroup)
    {
        //
    }

    public function filterRules($group){
        $ruleToPlaces = [];

        // Itera sobre os lugares como objetos
        foreach ($group->places as $place) {
            $place->schedule_rules;
            $placeData = [
                'id' => $place->id,
                'name' => $place->name
            ];

            foreach ($place->scheduleRules as $rule) {
                $ruleId = $rule->id;
                if (!isset($ruleToPlaces[$ruleId])) {
                    // Clona o objeto para evitar modificar o original
                    $ruleToPlaces[$ruleId] = [
                        'rule' => clone $rule,
                        'places' => []
                    ];
                    // Remove campo pivot se existir
                    unset($ruleToPlaces[$ruleId]['rule']->pivot);
                }
                if (isset($rule->weekdays)) {
                    $rule->weekdays = $rule->weekdays->sortBy('id')->values();
                }   
                $ruleToPlaces[$ruleId]['places'][] = $placeData;
            }

        }


        // Agrupa regras por conjunto de lugares
        $rules = [];

        foreach ($ruleToPlaces as $ruleData) {
            $places = $ruleData['places'];

            // Adiciona o campo "places" no objeto da regra
            $rule = $ruleData['rule'];
            $rule->places = $places;

            $rules[] = $rule;
        }



        //Delete schedule_rules from places
        foreach ($group->places as $place) {
            unset($place->scheduleRules);
        }

        #Order rules by length of places
        usort($rules, function ($a, $b) {
            return count($b->places) <=> count($a->places);
        });

        //Remove weekdays pivot
        foreach ($rules as $rule) {
            if (isset($rule->weekdays)) {
                foreach ($rule->weekdays as $weekday) {
                    unset($weekday->pivot);
                }
            }
        }

        return $rules;
    }
}
