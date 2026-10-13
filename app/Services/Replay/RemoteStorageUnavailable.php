<?php

namespace App\Services\Replay;

use RuntimeException;

/**
 * O FTP dos clipes não respondeu (conexão, login, pasta raiz).
 *
 * Separado de "arquivo não existe" de propósito: na recepção, FTP fora é
 * 503 — o sistema de captura retenta —, e arquivo inexistente é 422, que
 * retentar não resolve.
 */
class RemoteStorageUnavailable extends RuntimeException
{
}
