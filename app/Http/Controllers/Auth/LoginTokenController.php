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
        $now_plus_1minute = now()->addMinute()->timestamp;

        $payload = [
            'username' =>  $member['cpf'],
            'exp' => $now_plus_1minute,
        ];
        return $jwtService->generateToken($payload);
    }

    public static function validate(Request $request)
    {
        return response()->json([true], 200);
    }
}
