<?php

namespace App\Http\Controllers;

use App\Models\Information;
use App\Models\DataInfo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInformationRequest;
use App\Http\Requests\UpdateInformationRequest;

class InformationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $infos = DataInfo::with('user', 'information')
                    ->whereHas('information', function($query) {
                        $query->where('deleted_at', null);
                    })
                    ->get()
                    ->groupBy('information_id')
                    ->map->sortByDesc('created_at')
                    ->map->first();


        $regHtmlTagOpen = '/<[^>]*>/';
        $regHtmlTagClose = '/<\/[^>]*>/';
        foreach ($infos as $info) {
            $info->description = preg_replace($regHtmlTagOpen, '', $info->description);
            $info->description = preg_replace($regHtmlTagClose, '', $info->description);
            // Limit the description to 100 characters
            $info->description = substr($info->description, 0, 250);
            // Add ... to the end of the description
            $info->description = '<i>' . $info->description . '... </i>';

            $aux = explode(';', $info->responsible);
            $responsibles = null;
            foreach ($aux as $key => $value) {
                $responsibles .= $value . ($key < count($aux) - 1 ? ', ' : null);
            }
            $info->responsible = rtrim($responsibles, ', ');

            $pricesCount = count(explode(';', $info->name_price));

            $aux = [];

            for ($i = 0; $i < $pricesCount -1; $i++) {
                if ($info->name_price[$i] != ';' || $info->price_associated[$i] != ';' || $info->price_not_associated[$i] != ';'){
                    
                    $aux[] =  explode(';', $info->name_price)[$i] . ' R$ ' . explode(';', $info->price_associated)[$i] . ' (Sócio) | R$ ' . explode(';', $info->price_not_associated)[$i] . ' (Não Sócio)';
                }
            }


            $info->prices = $aux;

            
        }  
        $infos = $infos->sortBy('name');


        return View('information.index', compact('infos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return View('information.create');
    }

    public function concatenateArrayValues($array, $delimiter = ';') {
        return implode($delimiter, $array) . $delimiter;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInformationRequest $request)
    {
        try{  
        $data = $request->all();
        
        $fieldsToConcatenate = ['name_price', 'price_associated', 'price_not_associated', 'responsible', 'responsible_contact', 'day_hour'];
        
        $regex = '/^[;]+$/';
        foreach ($fieldsToConcatenate as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->concatenateArrayValues($data[$field]);
            }
        }

        try {

            $count_day = count($data['day']);
            
            $day_hour = null;
            for ($i = 0; $i < $count_day; $i++) {
                if ($data['day'][$i] == '#') {
                    continue;
                }
                $day_hour .= $data['day'][$i] . ',' . $data['start_hour'][$i] . ',' . $data['end_hour'][$i] . ';';
            }
            if ($day_hour == null) {
                $day_hour = ';';
            }
            
            $data['day_hour'] = $day_hour;
        }
        catch (\Exception $e) {
            $data['day_hour'] = null;
        }
	$data['created_by'] = auth()->user()->id;
	$info_data = [
		'privacy' => '0',
		'created_by' => $data['created_by']
	];
        if (isset($data['information_id'])) $info_data['information_id'] = $data['information_id'];
        else {
            $info = Information::create($info_data);
    
            $data['information_id'] = $info->id;
        }


        //If has key image
        if($request->hasFile('image')) {
            $imageName = time().'.'.$request->image->extension();

            $request->image->move(public_path('images'), $imageName);

            $data['image'] = $imageName;
        }
        else{
            $old_data = DataInfo::where('information_id', $data['information_id'])->orderBy('created_at', 'desc')->first();
            $data['image'] = $old_data->image ?? null;
        }


        $data['created_by'] = auth()->user()->id;

        //TODO: Implementar a criação de privacy
        //0 - Public
        //1 - Setor
        //2 - Private

        //$info_data = [
         //   'privacy' => '0',
           // 'created_by' => $data['created_by']
//        ];



        DataInfo::create($data);
                    
        } catch (\Exception $e) {
            dd("Erro! Tire um print e envie para a TI!", $e);
        }
        
        return redirect()->route('information.index');
    }

    private function explode_fields($info){
        $info->name_price = explode(';', $info->name_price);
        $info->price_associated = explode(';', $info->price_associated);
        $info->price_not_associated = explode(';', $info->price_not_associated);
        $info->responsible = explode(';', $info->responsible);
        $info->responsible_contact = explode(';', $info->responsible_contact);
        $info->day_hour = explode(';', $info->day_hour);

        $info->name_price = array_filter($info->name_price);
        $info->price_associated = array_filter($info->price_associated);
        $info->price_not_associated = array_filter($info->price_not_associated);
        $info->responsible = array_filter($info->responsible);
        $info->responsible_contact = array_filter($info->responsible_contact);
        $info->day_hour = array_filter($info->day_hour);
    }
    /**
     * Display the specified resource.
     */
    public function show(DataInfo $information)
    {
        $info = DataInfo::with('information', 'user')
                            ->where('information_id', $information->information_id)
                            ->orderBy('created_at', 'desc')
                            ->first();
                            
                            $this->explode_fields($info);
                            // dd($info);

        
        return View('information.show', compact('info'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DataInfo $information)
    {
        // $info = Information::find($information)->first();
        $info = $information;
        $this->explode_fields($info);

        return View('information.edit', compact('info'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($information)
    {
        $info = DataInfo::where('id', $information)->first();
        
        $data = $info->toArray();

        // $fieldsToConcatenate = ['name_price', 'price_associated', 'price_not_associated', 'responsible', 'responsible_contact'];

        // foreach ($fieldsToConcatenate as $field) {
        //     if (isset($data[$field])) {
        //         $data[$field] = $this->concatenateArrayValues($data[$field]);
        //     }
        // }

        $data['created_by'] = auth()->user()->id;

        //Update created_at to now time
        $data['created_at'] = now();

        DataInfo::create($data);

        return redirect()->route('information.show', $info->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Information $information)
    {
        $info = Information::find($information)->first();
        $info->delete();
        return redirect()->route('information.index');
    }


    public function history($information){
        $info = DataInfo::with('information', 'user')
                            ->where('information_id', $information)
                            ->orderBy('created_at', 'desc')
                            ->get();

        foreach ($info as $i) {
            $this->explode_fields($i);
        }

        return View('information.history', compact('info'));
    }
}
