<?php

namespace App\Console\Commands;

use App\Models\Aviso;
use App\Models\Lembrete;
use App\Models\User;
use App\Notifications\AvisoReminder;
use App\Notifications\AvisoExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ProcessAvisoNotifications extends Command
{
    protected $signature = 'avisos:process-notifications';
    protected $description = 'Envia notificações de lembretes e expiração de avisos';

    public function handle(): void
    {
        $this->processLembretes();
        $this->processExpiring();
    }

    private function processLembretes(): void
    {
        Lembrete::with('aviso')
            ->where('remind_at', '<=', now())
            ->where('sent', false)
            ->each(function (Lembrete $lembrete) {
                $aviso = $lembrete->aviso;
                if (!$aviso || $aviso->deleted_at) return;

                $this->usersForAviso($aviso)
                     ->each(fn($user) => $user->notify(new AvisoReminder($aviso)));

                $lembrete->update(['sent' => true]);
                $this->info("Lembrete enviado: [{$aviso->id}] {$aviso->title} @ {$lembrete->remind_at}");
            });
    }

    private function processExpiring(): void
    {
        Aviso::with('creator')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', today())
            ->where('expiry_notified', false)
            ->whereNull('deleted_at')
            ->each(function (Aviso $aviso) {
                $this->usersForAviso($aviso)
                     ->each(fn($user) => $user->notify(new AvisoExpiring($aviso)));

                $aviso->update(['expiry_notified' => true]);
                $this->info("Expiração notificada: [{$aviso->id}] {$aviso->title}");
            });
    }

    private function usersForAviso(Aviso $aviso): Collection
    {
        return match ($aviso->privacy) {
            Aviso::PRIVACY_PUBLICO => User::all(),
            Aviso::PRIVACY_PESSOA  => User::where('id', $aviso->created_by)->get(),
            Aviso::PRIVACY_SETOR   => $this->usersInSameSetor($aviso),
            default                => collect(),
        };
    }

    private function usersInSameSetor(Aviso $aviso): Collection
    {
        $creator = User::with('roles')->find($aviso->created_by);
        if (!$creator || $creator->roles->isEmpty()) {
            return collect([$creator])->filter();
        }
        $roleNames = $creator->roles->pluck('name');
        return User::whereHas('roles', fn($q) => $q->whereIn('name', $roleNames))->get();
    }
}
