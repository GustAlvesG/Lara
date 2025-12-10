<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Auth\MemberAuthController;

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

    public static function store($cpf, $title, $birthDate){
        $response = MemberAuthController::store($cpf, $title, $birthDate);

        return $response;
    
    }

    private static function queryMemberByCpf($document, $title, $birthDate)
    {
        return DB::connection('mc_sqlsrv')->select("SELECT
            Name, Email, MobilePhone As telephone, Barcode
        FROM
            dbo.Members
        LEFT JOIN
            dbo.Titles ON dbo.Members.Title = dbo.Titles.Id
            AND dbo.Titles.TitleType NOT IN (374, 375, 693320, 1297904, 3804861, 4062070, 6736996, 6736997, 6736998, 6737000)
        WHERE
            dbo.Titles.Code = '". $title . "' And 
            dbo.Titles.Status = 0 And
            dbo.Members.DocumentUnmasked = '" . $document . "' And
            dbo.Members.BirthDate = '" . $birthDate . "'");
    }

    
}
