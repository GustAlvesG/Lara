<?php

namespace App\Services\Signature\MinorTerms;

use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureFieldTypes;

/**
 * Os campos do modelo que o autoatendimento preenche sozinho.
 *
 * As chaves são as que o importador de `.docx` gera a partir dos rótulos do
 * termo em papel (`[[NOME RESPONSAVEL]]` → `nome_responsavel`, ver
 * DocxTemplateImporter::keyFor) — o modelo importado do Word casa sem ajuste
 * de chave. O e-mail aceita as duas grafias (`e_mail_…` e `email_…`).
 *
 * Os campos precisam ser do tipo Texto: o autoatendimento escreve o valor
 * pronto — inclusive "não informado" quando o dado falta e a pessoa pulou —, e
 * um tipo CPF ou e-mail apagaria esse texto na formatação.
 */
class MinorTermFields
{
    public const RESPONSIBLE_NAME = 'nome_responsavel';
    public const RESPONSIBLE_EMAIL = 'e_mail_responsavel';
    public const RESPONSIBLE_EMAIL_ALT = 'email_responsavel';
    public const RESPONSIBLE_CPF = 'cpf_responsavel';
    public const RESPONSIBLE_RG = 'rg_responsavel';
    public const RESPONSIBLE_ADDRESS = 'endereco_responsavel';
    public const MINOR_NAME = 'nome_menor';
    public const MINOR_AGE = 'idade_menor';
    public const MINOR_CPF = 'cpf_menor';
    public const MINOR_RG = 'rg_menor';

    /** O que sai escrito no termo quando o dado falta e a pessoa pulou. */
    public const NOT_INFORMED = 'não informado';

    /** Chave => o que é, para as mensagens e a documentação. */
    public const LABELS = [
        self::RESPONSIBLE_NAME => 'Nome do responsável',
        self::RESPONSIBLE_EMAIL => 'E-mail do responsável',
        self::RESPONSIBLE_EMAIL_ALT => 'E-mail do responsável',
        self::RESPONSIBLE_CPF => 'CPF do responsável',
        self::RESPONSIBLE_RG => 'RG do responsável',
        self::RESPONSIBLE_ADDRESS => 'Endereço do responsável (do título)',
        self::MINOR_NAME => 'Nome do menor',
        self::MINOR_AGE => 'Idade do menor',
        self::MINOR_CPF => 'CPF do menor',
        self::MINOR_RG => 'RG do menor',
    ];

    /** Sem estes dois, o termo não diz quem autoriza quem. */
    public const REQUIRED = [self::RESPONSIBLE_NAME, self::MINOR_NAME];

    /**
     * Por que este modelo não serve ao Termo de Menores. Lista vazia = serve.
     *
     * @return array<int, string>
     */
    public static function templateProblems(SignatureTemplate $template): array
    {
        $problemas = [];
        $campos = collect($template->declaredVariables())->keyBy('key');

        foreach (self::REQUIRED as $chave) {
            if (!$campos->has($chave)) {
                $problemas[] = 'O modelo não tem o campo [[' . $chave . ']] (' . self::LABELS[$chave] . ').';
            }
        }

        foreach ($campos as $chave => $campo) {
            if (SignatureFieldTypes::isAutomatic($campo['type'])) {
                continue;
            }

            if (isset(self::LABELS[$chave])) {
                if (!in_array($campo['type'], [SignatureFieldTypes::TEXT, SignatureFieldTypes::TEXTAREA], true)) {
                    $problemas[] = 'O campo "' . $campo['label'] . '" precisa ser do tipo Texto no modelo: o autoatendimento '
                        . 'escreve o valor pronto (inclusive "' . self::NOT_INFORMED . '").';
                }

                continue;
            }

            // Ninguém preenche: no autoatendimento não há atendente.
            if ($campo['required']) {
                $problemas[] = 'O campo obrigatório "' . $campo['label'] . '" não é preenchido pelo autoatendimento. '
                    . 'Escreva o valor no texto do modelo, torne o campo opcional ou, se for a data, use o tipo '
                    . '"Data da assinatura".';
            }
        }

        if (!$template->requires_photo) {
            $problemas[] = 'O modelo precisa exigir a foto de quem assina: é a foto do responsável que a entrada confere.';
        }

        if ($template->identity_check === SignatureTemplate::IDENTITY_EMAIL) {
            $problemas[] = 'O modelo confere a identidade por código no e-mail; no Termo de Menores a conferência é '
                . 'pelo CPF completo, digitado antes de escolher o menor. Use "CPF completo".';
        }

        if ($template->requires_initials) {
            $problemas[] = 'O modelo exige visto em todas as páginas; o Termo de Menores colhe só a assinatura.';
        }

        if ($template->declaredParties() !== []) {
            $problemas[] = 'O modelo declara partes; o Termo de Menores tem um signatário só (o responsável), no [[assinatura]].';
        }

        return $problemas;
    }

    /**
     * Os valores do documento, prontos para o texto.
     *
     * @param  array<string, ?string>  $values  chave => valor (null = não informado)
     * @return array<string, string>
     */
    public static function data(SignatureTemplate $template, array $values): array
    {
        $values[self::RESPONSIBLE_EMAIL_ALT] = $values[self::RESPONSIBLE_EMAIL] ?? null;

        $declarados = collect($template->declaredVariables())->pluck('key')->all();
        $saida = [];

        foreach ($values as $chave => $valor) {
            if (!in_array($chave, $declarados, true)) {
                continue;
            }

            $saida[$chave] = ($valor === null || trim($valor) === '') ? self::NOT_INFORMED : trim($valor);
        }

        return $saida;
    }
}
