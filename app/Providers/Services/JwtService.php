<?php

namespace App\Providers\Services;

use Firebase\JWT\JWT; // Biblioteca para gerar JWT
use Firebase\JWT\Key;

class JwtService
{
    private $secretKey;
    private $algorithm;

    public function __construct()
    {
        
        $this->secretKey = 'a1b2c3d4e5f6g7h8i9j0';
        $this->algorithm = 'HS256';

    }

    public function generateToken(array $payload): string
    {
        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    public function validateToken(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->secretKey, $this->algorithm));
    }
}