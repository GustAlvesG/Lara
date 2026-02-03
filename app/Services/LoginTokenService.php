<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Providers\Services\JwtService;

class LoginTokenService
{

    protected $jwtService;
    public function __construct()
    {
        $this->jwtService = new JwtService();
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
