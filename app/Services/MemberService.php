<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Schedule;
use App\Models\Place;
use App\Models\Member;
use App\Models\ScheduleRules;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use PDF; // Facade do pacote barryvdh/laravel-dompdf
use App\Services\LoginTokenService;

class MemberService
{
    protected $loginTokenService;

    public function __construct()
    {
        $this->loginTokenService = new LoginTokenService();
    }

    public function memberByCpf(Request $request){
        $member_id = Member::where('cpf', preg_replace('/\D/', '', $request['cpf']))->value('id');
        if (!$member_id) {
            $response = $this->store($request['cpf'], $request['title'], $request['birthDate']);       
            // dd($response, 'here');
            //Type response
            if ($response->getStatusCode() == 201) {
                $member_id = $response->getData()->user->id;
            } else {
                return $response;
            }
        }
        return $member_id;
    }

    public function store($cpf, $title, $birthDate){
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        $member = Member::where('cpf', $cpf )->first();

        if ($member) {
            return response()->json(['error' => 'Member already exists'], 409);
        }
        
         $associated = self::queryMember($title, $cpf, $birthDate);

         if (!$associated) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        if ($associated) {
            $member = $associated[0];
            $member->title = $title;
            
            $member->cpf = $cpf;
            $member->birth_date = $birthDate;
            $member->image = self::getPhotoBlob($member->Photo)[0]->Content ?? null;

            //Convert the binary image to a image base324 string
            if ($member->image) {
                $member->image = base64_encode($member->image);
            }
            // SHA256 in cpf if not $password
            $member->Password = $password ?? hash('SHA256', $cpf);

            //Convert the object to an array
            $member = json_decode(json_encode($member), true);
            try {
                $member = Member::create($member);

                $token = $this->loginTokenService->generate($member);
    
            } catch (\Exception $e) {
                return response()->json([
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'user' => $member, 
                'token' => $token
            ], 201);
        }

        return response()->json(['error' => 'Member not found'], 404);
    }


    public static function queryMember($title, $document, $birthdate)
    {
        return DB::connection('mc_sqlsrv')->select("SELECT
            Name, Email, MobilePhone As telephone, Barcode, Photo
        FROM
            dbo.Members
        LEFT JOIN
            dbo.Titles ON dbo.Members.Title = dbo.Titles.Id
            AND dbo.Titles.TitleType NOT IN (374, 375, 693320, 1297904, 3804861, 4062070, 6736996, 6736997, 6736998, 6737000)
        WHERE
		dbo.Titles.Code = '". $title . "' And 
        dbo.Titles.Status = 0 And
		dbo.Members.DocumentUnmasked = '" . $document . "' And
        dbo.Members.BirthDate = '" . $birthdate . "'");
    }

    private static function getPhotoBlob($photoID)
    {
        if ($photoID) {
            return DB::connection('mc_sqlsrv_image')->select("SELECT Content FROM dbo.Files WHERE Id = " . $photoID);
        }
        return null;
    }
}