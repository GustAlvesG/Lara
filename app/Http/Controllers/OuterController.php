<?php

namespace App\Http\Controllers;

use App\Models\Outer;
use App\Models\Company;
use App\Http\Requests\StoreOuterRequest;
use App\Http\Requests\UpdateOuterRequest;

class OuterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($company_id)
    {
        $company = Company::find($company_id);
        return view('outer.create', [
            'company' => $company,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOuterRequest $request)
    {
        $data = $request->all();
        //convert image/base64 to image
        // Verificar se a imagem está presente e é uma string base64


        if($request->hasFile('image')) {
            $fileName = time().'.'.$request->image->extension();

            $request->image->move(public_path('images'), $fileName);

            $data['image'] = $fileName;
        }
        else if (isset($data['photo']) && preg_match('/^data:image\/(\w+);base64,/', $data['photo'], $type)) {
            $data['image'] = substr($data['photo'], strpos($data['photo'], ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            // Decodificar a string base64
            $data['image'] = base64_decode($data['image']);

            // Gerar um nome único para o arquivo de imagem
            $fileName = time() . '.' . $type;

            // Salvar a imagem no diretório desejado
            $filePath = public_path('images') . '/' . $fileName;
            file_put_contents($filePath, $data['image']);

            // Armazenar o caminho da imagem no banco de dados
            $data['image'] = $fileName;
            
        }

        Outer::create($data);

        return redirect()->route('company.show', $data['company_id']);
    }

  

    /**
     * Display the specified resource.
     */
    public function show($outer)
    {
        $outer = Outer::find($outer);
        //Load company
        $outer->company;
        //Load AccessRules
        $outer->accessRules;


        return view('outer.show', [
            'outer' => $outer,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Outer $outer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOuterRequest $request, Outer $outer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Outer $outer)
    {
        //
    }

}
