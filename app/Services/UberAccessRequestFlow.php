<?php

namespace App\Services;

use App\Exceptions\PoliListMessageNotIndexedException;
use App\Models\PoliListMessage;
use App\Models\UberAccessRequest;
use App\Services\MemberValidation\MemberValidator;
use App\Services\Poli\ParsedPoliMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * State machine for the "Pedi um Uber" WhatsApp capture flow, keyed by
 * contact_uuid: idle -> aguardando_matricula -> aguardando_nome ->
 * aguardando_local -> aguardando_placa -> aguardando_print ->
 * aguardando_acesso.
 */
class UberAccessRequestFlow
{
    private const TRIGGER_TEXT = 'Carro de Aplicativo';

    /**
     * Tempo máximo sem resposta do associado durante a coleta. Estourado o
     * prazo o pedido vira "expirado" e as respostas atrasadas são ignoradas —
     * só um novo gatilho recomeça o fluxo, do zero.
     */
    public const SESSION_TIMEOUT_SECONDS = 200;

    private const ACCESS_VALIDITY_MINUTES = 30;

    /**
     * Carência entre a Poli anunciar o fim do atendimento e a coleta ser
     * encerrada de fato.
     *
     * Existe por causa de uma corrida estreita: no fluxo que dá certo, a
     * despedida do bot sai no MESMO segundo em que o print chega. Os dois
     * viram webhooks independentes, e com mais de um worker na fila o fecho
     * pode ser processado antes do print — matando, na última etapa, um pedido
     * que estava completo. A carência deixa o que está em voo aterrissar; se
     * nesse meio-tempo a coleta terminar, o pedido sai de CAPTURE_STATUSES e o
     * fecho não encosta nele.
     */
    public const CLOSURE_GRACE_SECONDS = 30;
    private const PLATE_PATTERN = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    public function __construct(private readonly MemberValidator $memberValidator) {}

    public function handle(ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->contactUuid === null) {
            return null;
        }

        $request = UberAccessRequest::open()
            ->where('contact_uuid', $message->contactUuid)
            ->latest('id')
            ->first();

        if ($request && $this->isExpired($request)) {
            $request->update(['status' => UberAccessRequest::STATUS_EXPIRADO]);
            $request = null;
        }

        return $request
            ? $this->advance($request, $message)
            : $this->maybeStartSession($message);
    }

    /**
     * Encerra a coleta de um atendimento que a Poli fechou, completa ou não.
     *
     * A conversa acabou: o que ficou pela metade não vai receber mais resposta
     * nenhuma, e segurá-lo aberto só faz a próxima mensagem do associado cair
     * como se fosse continuação. Encerrado aqui, a mensagem seguinte não acha
     * pedido aberto e passa pela validação do gatilho de novo — que é como
     * deve ser, porque é um pedido novo.
     *
     * Só a COLETA é encerrada. Um pedido já em "aguardando_acesso" está
     * completo e o atendimento fecha logo depois dele no fluxo normal: expirar
     * aí mataria todo pedido legítimo no instante em que ficou pronto. Ele
     * segue vivo até o motorista chegar ou até `expires_at` vencer.
     *
     * @return int quantas coletas foram encerradas
     */
    public function closeCaptureForAttendance(string $attendanceUuid): int
    {
        $encerradas = UberAccessRequest::whereIn('status', UberAccessRequest::CAPTURE_STATUSES)
            ->where('poli_attendance_uuid', $attendanceUuid)
            ->update(['status' => UberAccessRequest::STATUS_EXPIRADO]);

        if ($encerradas > 0) {
            Log::info('UberAccessRequestFlow: coleta encerrada com o atendimento', [
                'poli_attendance_uuid' => $attendanceUuid,
                'pedidos' => $encerradas,
            ]);
        }

        return $encerradas;
    }

    /**
     * Só a fase de coleta expira por inatividade. Um pedido já em
     * "aguardando_acesso" está completo e vive até `expires_at` — aplicar o
     * timeout de resposta ali mataria o pedido antes de o motorista chegar.
     */
    private function isExpired(UberAccessRequest $request): bool
    {
        if (!in_array($request->status, UberAccessRequest::CAPTURE_STATUSES, true)) {
            return false;
        }

        if (!$request->last_message_at) {
            return false;
        }

        return $request->last_message_at
            ->copy()
            ->addSeconds(self::SESSION_TIMEOUT_SECONDS)
            ->isPast();
    }

    private function maybeStartSession(ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->type !== ParsedPoliMessage::TYPE_TEXT || !$this->isTrigger($message->text)) {
            return null;
        }

        // O texto sozinho não abre pedido: ele é idêntico quando o associado
        // toca no botão e quando digita na mão, e o menu de dias atrás repete
        // o mesmo texto. Quem decide é a procedência do toque.
        $recusa = $this->motivoDeRecusaDoGatilho($message);

        if ($recusa !== null) {
            Log::info('UberAccessRequestFlow: gatilho recusado', [
                'motivo' => $recusa,
                'contact_uuid' => $message->contactUuid,
                'attendance_uuid' => $message->attendanceUuid,
                'context_message_uuid' => $message->contextMessageUuid,
                'texto' => $message->text,
            ]);

            return null;
        }

        return UberAccessRequest::create([
            'contact_uuid' => $message->contactUuid,
            'contact_phone' => $message->contactPhone,
            'contact_name_whatsapp' => $message->contactName,
            'poli_attendance_uuid' => $message->attendanceUuid,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
            'last_message_at' => now(),
        ]);
    }

    /**
     * Filtro barato, e só isso: separa "parece o gatilho" de todo o resto para
     * não consultar o banco a cada mensagem que entra. Quem autoriza de fato é
     * `motivoDeRecusaDoGatilho`.
     */
    private function isTrigger(?string $text): bool
    {
        return $text !== null
            && Str::of($text)->trim()->lower()->startsWith(Str::lower(self::TRIGGER_TEXT));
    }

    /**
     * Por que este gatilho não vale — ou null, se valer.
     *
     * A procedência é verificada por identidade e por parentesco, nunca por
     * relógio nem por id de menu:
     *
     *  - sem `context`, o associado DIGITOU o texto; só o toque no botão
     *    preenche esse campo;
     *  - o menu citado tem que ser um menu nosso, que vimos sair;
     *  - e tem que ser o menu DESTA conversa. O menu de outro dia pertence a
     *    um atendimento já encerrado pela Poli, enquanto o toque nele abre um
     *    atendimento novo — os uuids não batem, e isso basta. Nada de janela
     *    de minutos: o associado pode demorar o quanto quiser para responder,
     *    desde que seja o menu da conversa dele;
     *  - e a linha tocada tem que ser a do carro de aplicativo, e não outra
     *    opção do mesmo menu.
     *
     * @throws PoliListMessageNotIndexedException quando o menu citado ainda
     *         não chegou — indecisão, não recusa.
     */
    private function motivoDeRecusaDoGatilho(ParsedPoliMessage $message): ?string
    {
        if ($message->contextMessageUuid === null) {
            return 'digitado_sem_menu';
        }

        $menu = PoliListMessage::where('poli_message_uuid', $message->contextMessageUuid)->first();

        if (!$menu) {
            throw new PoliListMessageNotIndexedException($message->contextMessageUuid);
        }

        if ($message->attendanceUuid === null || $menu->attendance_uuid !== $message->attendanceUuid) {
            return 'menu_de_outro_atendimento';
        }

        $linha = $menu->rowForAnswer($message->text);

        if ($linha === null) {
            return 'opcao_nao_confere';
        }

        if (PoliListMessage::normalize($linha['title']) !== PoliListMessage::normalize(self::TRIGGER_TEXT)) {
            return 'outra_opcao_do_menu';
        }

        return null;
    }

    /**
     * O título da opção tocada, quando a resposta veio de um menu.
     *
     * A resposta de lista chega como "título descrição" ("Campo Bar do Campo"),
     * e é isso que ia parar no banco — e depois no WhatsApp de chegada, num
     * "a caminho de Campo Bar do Campo". O que interessa é o título.
     *
     * Sem exigir o mesmo atendimento de propósito: aqui não se autoriza nada,
     * só se escolhe um rótulo melhor. Não achando, fica o texto como veio.
     */
    private function tituloDaOpcao(ParsedPoliMessage $message): ?string
    {
        if ($message->contextMessageUuid === null) {
            return null;
        }

        $menu = PoliListMessage::where('poli_message_uuid', $message->contextMessageUuid)->first();

        return $menu?->rowForAnswer($message->text)['title'] ?? null;
    }

    private function advance(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        /*
         * "Carro de Aplicativo" no meio de uma sessão aberta nunca é resposta:
         * não é matrícula, não é nome, não é local nem placa. É o associado
         * tocando no menu de novo — e era exatamente isso que entrava no campo
         * seguinte e deslocava o pedido inteiro uma casa, gravando a matrícula
         * no nome e o nome no local.
         *
         * Aqui, ao contrário do gatilho, não se confere a procedência: venha
         * do botão, do teclado ou do menu de ontem, esse texto não é resposta
         * para pergunta nenhuma.
         */
        if ($message->type === ParsedPoliMessage::TYPE_TEXT && $this->isTrigger($message->text)) {
            Log::info('UberAccessRequestFlow: gatilho repetido durante sessão aberta, ignorado', [
                'uber_access_request_id' => $request->id,
                'status' => $request->status,
            ]);

            return $request;
        }

        return match ($request->status) {
            UberAccessRequest::STATUS_AGUARDANDO_MATRICULA => $this->captureText(
                $request,
                $message,
                'matricula',
                UberAccessRequest::STATUS_AGUARDANDO_NOME
            ),
            UberAccessRequest::STATUS_AGUARDANDO_NOME => $this->captureText(
                $request,
                $message,
                'requester_name',
                UberAccessRequest::STATUS_AGUARDANDO_LOCAL
            ),
            UberAccessRequest::STATUS_AGUARDANDO_LOCAL => $this->captureText(
                $request,
                $message,
                'club_location',
                UberAccessRequest::STATUS_AGUARDANDO_PLACA
            ),
            UberAccessRequest::STATUS_AGUARDANDO_PLACA => $this->capturePlate($request, $message),
            UberAccessRequest::STATUS_AGUARDANDO_PRINT => $this->captureScreenshot($request, $message),
            default => $this->ignore($request, $message),
        };
    }

    private function captureText(
        UberAccessRequest $request,
        ParsedPoliMessage $message,
        string $field,
        string $nextStatus
    ): ?UberAccessRequest {
        if ($message->type !== ParsedPoliMessage::TYPE_TEXT) {
            return $this->ignore($request, $message);
        }

        $request->update([
            // Resposta de menu entra pelo título; resposta digitada, como veio.
            $field => $this->tituloDaOpcao($message) ?? $message->text,
            'status' => $nextStatus,
            'last_message_at' => now(),
        ]);

        return $request;
    }

    private function capturePlate(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->type !== ParsedPoliMessage::TYPE_TEXT) {
            return $this->ignore($request, $message);
        }

        $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $message->text));

        if (!preg_match(self::PLATE_PATTERN, $plate)) {
            Log::warning('UberAccessRequestFlow: placa fora do formato esperado', [
                'uber_access_request_id' => $request->id,
                'value' => $message->text,
            ]);
        }

        $request->update([
            'vehicle_plate' => $plate,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_PRINT,
            'last_message_at' => now(),
        ]);

        return $request;
    }

    private function captureScreenshot(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        if ($message->type !== ParsedPoliMessage::TYPE_IMAGE) {
            return $this->ignore($request, $message);
        }

        return $this->concluir($request, $message->mediaUrl);
    }

    /**
     * Pedido cuja coleta foi feita pelo bot da Lara, e não pela escuta do bot
     * da Poli. Nasce já completo — o bot só chama aqui depois de validar cada
     * resposta — e passa pela MESMA conclusão do fluxo escutado: conferência
     * de sócio/funcionário e validade.
     *
     * @param array{matricula: string, nome: string, local: ?string, placa: string, print: string} $dados
     */
    public function registrarPedidoDoBot(array $dados, ParsedPoliMessage $message): UberAccessRequest
    {
        $request = UberAccessRequest::create([
            'contact_uuid' => $message->contactUuid,
            'contact_phone' => $message->contactPhone,
            'contact_name_whatsapp' => $message->contactName,
            'poli_attendance_uuid' => $message->attendanceUuid,
            'matricula' => $dados['matricula'],
            'requester_name' => $dados['nome'],
            'club_location' => $dados['local'] ?? null,
            'vehicle_plate' => $dados['placa'],
            'status' => UberAccessRequest::STATUS_AGUARDANDO_PRINT,
            'last_message_at' => now(),
        ]);

        return $this->concluir($request, $dados['print']);
    }

    private function concluir(UberAccessRequest $request, ?string $screenshotUrl): UberAccessRequest
    {
        $completedAt = now();

        // Com todos os dados em mãos, confere nome + matrícula/CPF contra
        // sócios e funcionários. O resultado é registrado para a portaria ver;
        // não bloqueia o pedido.
        $validation = $this->memberValidator->validate($request->matricula, $request->requester_name);

        // O pedido está completo, mas o acesso ainda não aconteceu: fica
        // "aguardando acesso do motorista" até ele chegar na portaria (quando
        // vira "concluido") ou a validade vencer (quando vira "expirado").
        $request->update([
            'screenshot_url' => $screenshotUrl,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_ACESSO,
            'member_validation' => $validation->status,
            'member_validation_name' => $validation->matchedName,
            'member_validation_type' => $validation->type,
            'member_validated_at' => $completedAt,
            'completed_at' => $completedAt,
            'expires_at' => $completedAt->copy()->addMinutes(self::ACCESS_VALIDITY_MINUTES),
            'last_message_at' => $completedAt,
        ]);

        if ($validation->status !== UberAccessRequest::MEMBER_VALIDATION_VALIDADO) {
            Log::info('UberAccessRequestFlow: pedido concluído sem confirmar sócio/funcionário', [
                'uber_access_request_id' => $request->id,
                'member_validation' => $validation->status,
                'matricula' => $request->matricula,
            ]);
        }

        return $request;
    }

    private function ignore(UberAccessRequest $request, ParsedPoliMessage $message): ?UberAccessRequest
    {
        Log::info('UberAccessRequestFlow: mensagem fora de ordem ignorada', [
            'uber_access_request_id' => $request->id,
            'status' => $request->status,
            'message_type' => $message->type,
        ]);

        return null;
    }
}
