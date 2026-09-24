<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Um menu de opções que a Poli enviou para um contato.
 *
 * Gravado a partir do webhook de SAÍDA (`event: "sent"`), e consultado quando
 * a resposta volta citando-o em `value.context.message.uuid`.
 */
class PoliListMessage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'poli_message_uuid',
        'attendance_uuid',
        'contact_uuid',
        'template_name',
        'rows',
        'sent_at',
    ];

    protected $casts = [
        'rows' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * A linha do menu que produziu esta resposta, ou null se nenhuma bate.
     *
     * A resposta de uma lista chega como "título\ndescrição" — e o parser
     * troca a quebra por espaço antes de nos entregar. Então o que se compara
     * é a composição das duas partes, e não só o título: exigir a descrição é
     * o que impede que uma mensagem DIGITADA em resposta ao menu (citando-o,
     * o que preencheria o `context`) passe por um toque no botão.
     *
     * Linha sem descrição cai no título sozinho, que é tudo o que ela tem.
     */
    public function rowForAnswer(?string $answer): ?array
    {
        $needle = self::normalize($answer);

        if ($needle === '') {
            return null;
        }

        foreach ($this->rows ?? [] as $row) {
            $title = $row['title'] ?? null;

            if (!is_string($title) || trim($title) === '') {
                continue;
            }

            $description = (string) ($row['description'] ?? '');

            $esperado = trim($description) === ''
                ? $title
                : $title . ' ' . $description;

            if (self::normalize($esperado) === $needle) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Mesma dobra que o texto recebido sofre no PoliMessageParser (quebras e
     * controles viram espaço, espaços colapsam), mais caixa baixa.
     *
     * O menu e a resposta saem da mesma fonte, então na prática são idênticos
     * byte a byte; a normalização é para não quebrar a trava inteira se a Poli
     * mudar um espaçamento. Quem garante a origem é o contexto, não o texto —
     * aqui só se descobre QUAL opção foi tocada.
     */
    public static function normalize(?string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $value);

        return Str::of($value)->replaceMatches('/\s+/', ' ')->trim()->lower()->value();
    }
}
