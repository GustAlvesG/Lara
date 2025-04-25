<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //Get all members with name starting with 'A'
        $data = Member::where('Name', 'like', 'A%')->get();
        return response()->json($data);
    }

    public static function findMemberByTitle($title)
    {
        //Get all members with the given title
        $data = Member::where('Title', $title)->get();
        return response()->json($data);
    }

    public static function findMemberByCode($code)
    {
        $data = [];
        //Get all members with the given code
        $title = DB::connection('mc_sqlsrv')->table('dbo.Titles')->where('Code', $code)->get();
        dd($title);
        foreach ($title as $t) {
            $data[] = Member::where('Title', $t->Id)->where('Status', '!=', '2')->get();
            foreach ($data[count($data)-1] as $d) {
                $photo = DB::connection('mc_sqlsrv')->table('dbo.Storages')->where('Id', $d->Photo)->first();
                $d->Photo = $photo ? $photo->Content : null;
            }
        }

        dd($data);

        //Return the image
        return view('member.index', ['data' => $data]);
        
    }

    
}
