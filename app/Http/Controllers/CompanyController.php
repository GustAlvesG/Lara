<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Http\Request;
use App\Models\AccessRule;
use App\Models\Outer;
use App\Http\Controllers\AccessRuleController;

class CompanyController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('company.index', [
            'companies' => Company::all()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('company.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();
        if($request->hasFile('image')) {
            $imageName = time().'.'.$request->image->extension();

            $request->image->move(public_path('images'), $imageName);

            $data['image'] = $imageName;
        }
        else {
            $data['image'] = '';
        }

        $company_data = [
            'name' => $data['name'],
            'email' => $data['email'],
            'image' => $data['image'],
            'telephone' => $data['telephone'],
            'address' => $data['address'],
            'description' => $data['description'],
        ];

        $company = Company::create($company_data);


        //Check if status exists
        if(isset($data['status'])) {
            $data['weekdays'] = '';
            if(isset($data['week_day'])) {
                foreach($data['week_day'] as $weekday) {
                    $data['weekdays'] = $data['weekdays'] . $weekday . ';';
                }
            }
            //Check if start_date is lower than end_date and change it if not
            if($data['start_date'] > $data['end_date']) {
                $temp = $data['start_date'];
                $data['start_date'] = $data['end_date'];
                $data['end_date'] = $temp;
            }

            //Check if start_time is lower than end_time and change it if not
            if($data['start_time'] > $data['end_time']) {
                $temp = $data['start_time'];
                $data['start_time'] = $data['end_time'];
                $data['end_time'] = $temp;
            }



            $access_rule_data = [
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'weekdays' => $data['weekdays'],
                'status' => $data['status'],
                'company_id' => $company->id,
            ];
            AccessRule::create($access_rule_data);
        }



        return redirect()->route('company.index');
    }

    public function change($id)
    {
        $company = Company::find($id);
        $company->status = $company->status == 1 ? 0 : 1;
        $company->save();
        return redirect()->route('company.show', $company->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        //Load data for the company and all access rules and outer
        $rules = AccessRule::where('company_id', $company->id)->where('outer_id', null)->get();

        // $company->applicable = false;

        // foreach($rules as $rule) {
            
        //     $rule->applicable = AccessRuleController::validAccessCompany($rule);
            
        //     if ($rule->applicable) $company->applicable = true;

        //     //Format time
        //     if ($rule->start_time != $rule->end_time) {
        //         $rule->start_time = date('H:i', strtotime($rule->start_time));
        //         $rule->end_time = date('H:i', strtotime($rule->end_time));
        //     }

        //     //Format date
        //     if ($rule->start_date != $rule->end_date) {
        //         $rule->start_date = date('d/m/Y', strtotime($rule->start_date));
        //         $rule->end_date = date('d/m/Y', strtotime($rule->end_date));
        //     }

        //     $rule->status = $rule->status == 1 ? 'Ativa' : 'Inativa';
        // }
        // //Order rules by applicable with true first and status with Ativa first
        // $rules = $rules->sortByDesc('applicable')->sortBy('status');
        

        // $outers = Outer::where('company_id', $company->id)->get();

        // foreach ($outers as $outer) {
        //     $outer->rules = AccessRule::where('outer_id', $outer->id)->get();

        //     if ($outer->rules->isEmpty()) {
        //         $outer->applicable = $company->applicable;
        //     }
        //     else{
        //         foreach ($outer->rules as $rule) {
        //             $rule->applicable = AccessRuleController::validAccessOuter($rule);
        //             if ($rule->applicable) $outer->applicable = true;
        //             // dd($rule);
        //         }
        //     }   
        // }



        return view('company.show',[
            'company' => $company,
            'rules' => $rules,
            'outers' => $outers
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        //
    }
}
