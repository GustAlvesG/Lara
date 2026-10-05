<?php

namespace Tests\Feature;

use App\Http\Controllers\FtpController;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Ftp\UnableToConnectToFtpHost;
use Mockery;
use Tests\TestCase;

/**
 * Foto do acesso no SIV (busca de placa): vem do FTP das câmeras e fica numa
 * cópia local, dentro de `public/`, que é de onde a tela lê.
 *
 * Discos falsos, sem banco e sem rede.
 */
class ParkingCarImageTest extends TestCase
{
    private const FOTO = 'RKT4F21/2026-10-01T10-00-12&Prata&A.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(FtpController::SOURCE_DISK);
        Storage::fake(FtpController::LOCAL_DISK);
        Storage::fake(FtpController::LEGACY_DISK);
        Cache::forget(FtpController::OFFLINE_CACHE_KEY);
    }

    /** Troca o disco do FTP por um que conta as chamadas. */
    private function ftpMock()
    {
        $ftp = Mockery::mock(FilesystemAdapter::class);
        Storage::set(FtpController::SOURCE_DISK, $ftp);

        return $ftp;
    }

    public function test_baixa_a_foto_do_ftp_para_o_disco_que_a_tela_le(): void
    {
        Storage::disk(FtpController::SOURCE_DISK)->put(self::FOTO, 'jpeg-da-camera');

        $this->assertSame(self::FOTO, FtpController::getImage(self::FOTO));

        $local = Storage::disk(FtpController::LOCAL_DISK);
        $this->assertSame('jpeg-da-camera', $local->get(self::FOTO));
        $local->assertMissing(self::FOTO . '.part');
        // O defeito antigo: a foto ia para o disco `public`, fora do alcance da URL.
        Storage::disk(FtpController::LEGACY_DISK)->assertMissing('img_car/' . self::FOTO);
    }

    public function test_o_disco_local_fica_dentro_de_public_onde_a_url_alcanca(): void
    {
        $this->assertSame(
            public_path('storage/img_car'),
            config('filesystems.disks.' . FtpController::LOCAL_DISK . '.root')
        );
    }

    public function test_nome_com_vehicle_body_acha_o_arquivo_que_no_ftp_e_so_jpg(): void
    {
        $noBanco = 'RKT4F21/2026-10-01T10-00-12&Prata&A.vehicleBody.jpg';
        Storage::disk(FtpController::SOURCE_DISK)->put(self::FOTO, 'jpeg-da-camera');

        $this->assertSame($noBanco, FtpController::getImage($noBanco));

        // A cópia local fica com o nome do banco: é por ele que a próxima busca procura.
        $this->assertSame('jpeg-da-camera', Storage::disk(FtpController::LOCAL_DISK)->get($noBanco));
    }

    public function test_nome_com_vehicle_body_prefere_o_arquivo_de_nome_igual(): void
    {
        $noBanco = 'RKT4F21/2026-10-01T10-00-12&Prata&A.vehicleBody.jpg';
        Storage::disk(FtpController::SOURCE_DISK)->put($noBanco, 'recorte-do-veiculo');
        Storage::disk(FtpController::SOURCE_DISK)->put(self::FOTO, 'jpeg-da-camera');

        FtpController::getImage($noBanco);

        $this->assertSame('recorte-do-veiculo', Storage::disk(FtpController::LOCAL_DISK)->get($noBanco));
    }

    public function test_foto_ja_baixada_nao_volta_ao_ftp(): void
    {
        Storage::disk(FtpController::LOCAL_DISK)->put(self::FOTO, 'copia-local');
        $this->ftpMock()->shouldNotReceive('readStream');

        $this->assertSame(self::FOTO, FtpController::getImage(self::FOTO));
        $this->assertSame('copia-local', Storage::disk(FtpController::LOCAL_DISK)->get(self::FOTO));
    }

    public function test_foto_baixada_antes_da_correcao_e_recuperada_sem_ftp(): void
    {
        Storage::disk(FtpController::LEGACY_DISK)->put('img_car/' . self::FOTO, 'copia-antiga');
        $this->ftpMock()->shouldNotReceive('readStream');

        $this->assertSame(self::FOTO, FtpController::getImage(self::FOTO));
        $this->assertSame('copia-antiga', Storage::disk(FtpController::LOCAL_DISK)->get(self::FOTO));
    }

    public function test_foto_que_nao_existe_no_ftp_devolve_falso_sem_deixar_resto(): void
    {
        $this->assertFalse(FtpController::getImage(self::FOTO));

        $this->assertSame([], Storage::disk(FtpController::LOCAL_DISK)->allFiles());
        // Arquivo faltando não é FTP fora do ar: as outras fotos continuam vindo.
        $this->assertFalse(Cache::has(FtpController::OFFLINE_CACHE_KEY));
    }

    public function test_ftp_fora_do_ar_e_tentado_uma_vez_so(): void
    {
        $this->ftpMock()->shouldReceive('readStream')->once()
            ->andThrow(UnableToConnectToFtpHost::forHost('ftp.invalido', 21, false));

        $this->assertFalse(FtpController::getImage(self::FOTO));
        $this->assertFalse(FtpController::getImage('RKT4F21/outra.jpg'));

        $this->assertTrue(Cache::has(FtpController::OFFLINE_CACHE_KEY));
        $this->assertSame([], Storage::disk(FtpController::LOCAL_DISK)->allFiles());
    }

    public function test_ftp_fora_do_ar_nao_esconde_a_foto_ja_baixada(): void
    {
        Cache::put(FtpController::OFFLINE_CACHE_KEY, true, 60);
        Storage::disk(FtpController::LOCAL_DISK)->put(self::FOTO, 'copia-local');

        $this->assertSame(self::FOTO, FtpController::getImage(self::FOTO));
    }

    public function test_acesso_sem_arquivo_nao_consulta_o_ftp(): void
    {
        $this->ftpMock()->shouldNotReceive('readStream');

        $this->assertFalse(FtpController::getImage(null));
        $this->assertFalse(FtpController::getImage('  '));
    }

    public function test_caminho_fora_da_pasta_das_fotos_e_recusado(): void
    {
        Storage::disk(FtpController::SOURCE_DISK)->put('segredo.txt', 'x');

        $this->assertFalse(FtpController::getImage('RKT4F21/../../segredo.txt'));
        $this->assertSame([], Storage::disk(FtpController::LOCAL_DISK)->allFiles());
    }

    public function test_url_da_foto_codifica_espaco_e_e_comercial(): void
    {
        $this->assertSame(
            asset('storage/img_car/Sem%20placa/2026-10-01T10-00-12%26Prata%26A.jpg'),
            FtpController::imageUrl('Sem placa/2026-10-01T10-00-12&Prata&A.jpg')
        );
        $this->assertSame(
            asset('storage/img_car/RKT4F21/20261001100012.jpg'),
            FtpController::imageUrl('RKT4F21/20261001100012.jpg')
        );
    }
}
