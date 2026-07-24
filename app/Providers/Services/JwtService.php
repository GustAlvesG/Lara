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
        $secret = config('services.jwt.secret');

        if (!$secret) {
            throw new \RuntimeException('JWT_SECRET não configurado.');
        }

        $this->secretKey = $secret;
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