<?php

namespace App\Http\Requests;

use App\Services\Lara\ScreenError;
use App\View\Navigation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

class AskLaraRequest extends FormRequest
{
    private ?ScreenError $erroDaTela = null;

    /**
     * O controle de acesso é da rota (`can:` permissão `lara` do catálogo).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mensagem = $this->input('mensagem');

        if (is_string($mensagem)) {
            $this->merge(['mensagem' => trim($mensagem)]);
        }

        // "Verificar com o HAL": a pergunta é montada aqui, do erro da tela.
        $erro = $this->input('erro');

        if (is_array($erro) && blank($mensagem)) {
            $this->erroDaTela = ScreenError::from($erro['status'] ?? null, $erro['texto'] ?? null);
            $this->merge(['mensagem' => $this->erroDaTela->pergunta()]);
        }
    }

    /**
     * O erro que a pessoa pediu para verificar, só com o tipo e o status.
     *
     * @return array{tipo: string, status: ?int}|null
     */
    public function erro(): ?array
    {
        return $this->erroDaTela?->contrato();
    }

    /**
     * O limite de caracteres também é aplicado no cliente da IA (que trunca
     * antes de enviar). Aqui ele existe para o funcionário receber um aviso
     * claro em vez de ter a pergunta cortada em silêncio.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'mensagem' => ['required', 'string', 'min:2', 'max:' . (int) config('services.lara.max_input_chars', 1000)],
            // Tela em que a pessoa está (o balão manda; ver contexto()).
            'contexto' => ['nullable', 'array'],
            'contexto.rota' => ['nullable', 'string', 'max:150'],
            'contexto.aba' => ['nullable', 'string', 'max:150'],
            // Erro da tela ("Verificar com o HAL"; ver prepareForValidation()).
            'erro' => ['nullable', 'array'],
            'erro.status' => ['nullable', 'integer'],
            'erro.texto' => ['nullable', 'string'],
        ];
    }

    /**
     * Onde a pessoa está, para a IA entender "como faço isso aqui?".
     *
     * O balão manda só NOMES DE ROTA (a da página e a aba do menu em que ela
     * cai — criar/editar caem na aba do recurso). Módulo e página saem daqui,
     * do menu do Lara: nome que não é rota existente é descartado, então o
     * navegador não consegue fazer chegar à IA um texto qualquer como se fosse
     * o contexto.
     *
     * @return array{modulo: string, pagina: string, caminho: string, rota: ?string}|null
     */
    public function contexto(): ?array
    {
        $contexto = $this->validated()['contexto'] ?? [];
        $valida = fn ($nome) => is_string($nome) && preg_match('/^[A-Za-z0-9._-]+$/', $nome) && Route::has($nome) ? $nome : null;

        $rota = $valida($contexto['rota'] ?? null);
        $aba = $valida($contexto['aba'] ?? null);

        $destinations = Navigation::destinations();
        $destino = $destinations[$aba] ?? $destinations[$rota] ?? null;

        if ($destino === null) {
            return null;
        }

        return [
            'modulo' => $destino['modulo'],
            'pagina' => $destino['pagina'],
            'caminho' => $destino['caminho'],
            // A rota exata diz o que a pessoa faz na aba (create, edit, show).
            'rota' => $rota,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mensagem.required' => 'Escreva uma pergunta.',
            'mensagem.min' => 'A pergunta está curta demais.',
            'mensagem.max' => 'A pergunta é longa demais — tente resumir.',
        ];
    }
}
