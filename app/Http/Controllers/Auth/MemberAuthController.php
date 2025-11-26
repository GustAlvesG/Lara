<?php

namespace App\Http\Controllers\Auth;
use App\Providers\Services\JwtService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginTokenController;



class MemberAuthController extends Controller
{

    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }


    public function register(StoreMemberRequest $request)
    {
        $validated = $request->validated([
            'title' => 'required|string',
            'cpf' => 'required|string',
            'birthDate' => 'required|date',
            'password' => 'required|string|min:6',
        ]);

        $member = Member::where('cpf', $request->input('cpf'))->first();

        if ($member) {
            return response()->json(['error' => 'Member already exists'], 409);
        }

        $title = $request->input('title');
        $document = $request->input('cpf');
        $birthdate = $request->input('birthDate');

        $associated = self::queryMember($title, $document, $birthdate);

        if (!$associated) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        if ($associated) {
            $member = $associated[0];
            $member->title = $title;
            
            $member->cpf = $document;
            $member->birth_date = $birthdate;
            
            $member->Password = $request->input('password');

            //Convert the object to an array
            $member = json_decode(json_encode($member), true);
            
            Member::create($member);

            $token = LoginTokenController::generate($member);

            $member = self::removeFields($member, ['Password', 'created_at', 'updated_at', 'deleted_at']);

            return response()->json([
                'user' => $member, 
                'token' => $token
            ], 201);
        }

        return response()->json(['error' => 'Member not found'], 404);
    }



    public function login(Request $request)
    {
        if ($request->has('login') && $request->has('password')) {
            // Validação das credenciais

            if (!$member = $this->validateCredentials($request->input('login'), $request->input('password'))) {
                return response()->json(['error' => 'Credenciais inválidas',
                'data' => $request->all()
            ], 401);
            }

            $token = LoginTokenController::generate($member);

            $member = self::removeFields($member, ['Password', 'created_at', 'updated_at', 'deleted_at']);

            return response()->json([
                'user' => $member,
                'token' => $token
            ], 200);
        }

        return response()->json([
            'error' => 'Invalid login credentials',
            'data' => $request->all()
        ], 401);
    }

    public function update(Request $request)
    {

        $member = Member::where('cpf', $request->input('cpf'))->first();

        if (!$member) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        //Update member data based on request input if not null
        $member->email = $request->input('email', $member->email) ?? $member->email;
        $member->telephone = $request->input('telephone', $member->telephone) ?? $member->telephone;
        $member->save();

        return response()->json(['user' => $member], 200);
    }

    public function changePassword(Request $request)
    {
        $member = Member::where('cpf', $request->input('cpf'))->first();

        $member->Password = $request->input('new_password');
        $member->save();

        return response()->json(['message' => 'Senha alterada com sucesso'], 200);
    }

    public function checkMember(Request $request)
    {
        $title = $request->input('title');
        $document = $request->input('cpf');
        $birthdate = $request->input('birth_date');


        $associated = Member::where('title', $title)
            ->where('cpf', $document)
            ->where('birth_date', $birthdate)
            ->first();



        if ($associated) {
            return response()->json(['exists' => true,
            'id' => $associated->cpf], 200);
        } else {
            return response()->json(['exists' => false], 404);
        }
    }

    private function generateToken($member)
    {
        $endOfDay = now()->endOfDay()->timestamp;
        $payload = [
            'user_id' => $member->id,
            'username' =>  $member->cpf,
            'exp' => $endOfDay
        ];
        return $this->jwtService->generateToken($payload);
    }

    private function validateCredentials($username, $password)
    {
        $member = Member::where('cpf', $username)->where('Password', $password)->first();
        return $member;
    }



    private function queryMember($title, $document, $birthdate)
    {
        return DB::connection('mc_sqlsrv')->select("SELECT Name, Email, MobilePhone As telephone, Barcode From
        dbo.Members LEFT JOIN dbo.Titles ON dbo.Members.Title = dbo.Titles.Id
        WHERE
		dbo.Titles.Code = '". $title . "' And 
		dbo.Members.DocumentUnmasked = '" . $document . "' And
        dbo.Members.BirthDate = '" . $birthdate . "'");
    }

    private static function removeFields($data, $fields)
    {
        foreach ($fields as $field) {
            unset($data[$field]);
        }
        return $data;
    }
}
