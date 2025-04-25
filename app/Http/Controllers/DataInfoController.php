<?php

namespace App\Http\Controllers;

use App\Models\DataInfo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDataInfoRequest;
use App\Http\Requests\UpdateDataInfoRequest;

class DataInfoController extends Controller
{

    public function test(){
        //Get all data from the table with the relationships group by information_id
        $infos = DataInfo::with('information', 'user')
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
        }

        return View('information.index', compact('infos'));
    }

    public function test_2($info){
        //Get all data from the table with the relationships group by information_id
        $infos = DataInfo::with('information', 'user')
                            ->where('information_id', $info)
                            ->get()
                            ->sortByDesc('created_at');
                            
        dd($infos);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDataInfoRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(DataInfo $dataInfo)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DataInfo $dataInfo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDataInfoRequest $request, DataInfo $dataInfo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DataInfo $dataInfo)
    {
        //
    }
}
