<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Providers\Services\JwtService;

class LoginTokenController extends Controller
{
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }


    public static function generate($member)
    {
        $jwtService = new JwtService();
        $endOfDay = now()->endOfDay()->timestamp;

        $payload = [
            'username' =>  $member['cpf'],
            'exp' => $endOfDay,
        ];
        return $jwtService->generateToken($payload);
    }

    public static function validate(Request $request)
    {
        return response()->json([true], 200);
    }
}
