<?php

namespace App\Jobs;

use App\Services\PoliBot\BotEngine;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Confere, alguns segundos depois do distribute, se o atendimento saiu mesmo
 * do O Lara. A Poli aceita o distribute com 200 e só então procura um
 * atendente disponível no time — o 200 não garante que alguém pegou.
 */
class ConfirmPoliBotHandoff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $contactUuid) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(2);
    }

    public function handle(BotEngine $bot): void
    {
        try {
            $bot->exclusive($this->contactUuid, fn () => $bot->confirmHandoff($this->contactUuid), waitSeconds: 5);
        } catch (LockTimeoutException) {
            $this->job?->release(5);
        }
    }
}
