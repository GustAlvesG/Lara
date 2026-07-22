<?php

namespace App\Imports;

use App\Exceptions\ImportRowException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Normalização dos valores digitados na planilha. O Excel é permissivo — a
 * mesma data pode chegar como "22/07/2026", "2026-07-22" ou como número
 * serial já convertido pelo leitor — então cada campo é reduzido aqui a um
 * único formato antes da validação.
 */
class ImportValues
{
    /**
     * Mantém só os dígitos e recompõe os zeros à esquerda que o Excel corta
     * quando a célula é tratada como número.
     */
    public static function cpf(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        if ($digits === '' || strlen($digits) > 11) {
            return $digits;
        }

        return str_pad($digits, 11, '0', STR_PAD_LEFT);
    }

    /**
     * @throws ImportRowException
     */
    public static function date(string $value, string $label): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
            } catch (Throwable $e) {
                continue;
            }

            // Reescrever no mesmo formato descarta datas que o Carbon aceita
            // por transbordo, como 32/01/2026 virando 01/02/2026.
            if ($date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        throw new ImportRowException(
            $label . ' inválida ("' . $value . '"). Use o formato dd/mm/aaaa.'
        );
    }

    /**
     * @throws ImportRowException
     */
    public static function time(string $value, string $label): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('!H:i:s', strlen($value) === 5 ? $value . ':00' : $value)
                ->format('H:i');
        } catch (Throwable $e) {
            throw new ImportRowException(
                $label . ' inválido ("' . $value . '"). Use o formato HH:MM.'
            );
        }
    }
}
