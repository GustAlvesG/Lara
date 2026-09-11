<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Cadastro da diretoria que aprova os lotes de freelancer — nome, e-mail que
 * recebe os códigos e a imagem da assinatura aplicada aos contratos da
 * redação 2.
 *
 * Os registros NÃO são editados: cada alteração feita pela gerência cria uma
 * linha nova (`FreelancerDirectorService::register()`), e vale a mais recente
 * (`current()`). Contratos aprovados apontam para a linha que os assinou; se o
 * nome ou a imagem fossem trocados no lugar, todo documento já aprovado
 * passaria a exibir a assinatura nova.
 */
class FreelancerDirector extends Model
{
    /** Disco PRIVADO: a assinatura de uma pessoa não fica acessível por URL. */
    public const DISK = 'local';

    public const DIRECTORY = 'freelancer/director-signatures';

    protected $table = 'freelancer_directors';

    protected $fillable = ['name', 'email', 'signature_path', 'created_by'];

    /** O cadastro vigente — o mais recente. Null enquanto ninguém cadastrou. */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasSignature(): bool
    {
        return filled($this->signature_path)
            && Storage::disk(self::DISK)->exists($this->signature_path);
    }

    /**
     * A imagem como data URI, para entrar no documento sem depender de rota
     * nem de link público: o mesmo HTML é impresso pelo painel, exibido no
     * tablet e convertido pelo DomPDF, e só o data URI serve aos três.
     */
    public function signatureDataUri(): ?string
    {
        if (!$this->hasSignature()) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk(self::DISK)->get($this->signature_path));
    }
}
