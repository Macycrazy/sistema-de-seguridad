<?php

namespace App\Livewire\Asociacion;

use App\Services\Carnets\Verificador;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * La herramienta para asociar este sistema con el de carnets y probar la conexión.
 *
 * No marca nada ni guarda nada: es un banco de pruebas. Se teclea la dirección del carnets (su IP
 * de la red interna) y un botón dice si responde; con el contenido de un QR, otro botón enseña el
 * veredicto que devolvería la puerta. Cuando la dirección buena esté confirmada, se fija en el
 * .env (CARNETS_URL) y ya el marcaje la usa sola.
 *
 * La llamada la hace el SERVIDOR, no el navegador: por eso lo que tiene que alcanzar al carnets es
 * la máquina donde corre este sistema. Desde un equipo detrás de una VPN, la prueba dará «no
 * respondió» aunque la app esté bien —eso es la red, no el asociador—.
 */
class Carnets extends Component
{
    public string $url = '';

    public string $qr = '';

    /** @var array<string, mixed> */
    public array $conexion = [];

    /** @var array<string, mixed> */
    public array $verificacion = [];

    public function boot(): void
    {
        Gate::authorize('gestionar-ajustes');
    }

    public function mount(): void
    {
        $this->url = app(Verificador::class)->urlPorOmision();
    }

    public function probar(): void
    {
        $this->verificacion = [];
        $this->conexion = app(Verificador::class)->probar($this->url);
    }

    public function verificar(): void
    {
        $this->conexion = [];

        if (trim($this->qr) === '') {
            $this->verificacion = ['ok' => false, 'mensaje' => 'Pega primero el contenido de un QR.'];

            return;
        }

        $this->verificacion = app(Verificador::class)->verificar($this->url, trim($this->qr));
    }

    public function render()
    {
        return view('livewire.asociacion.carnets');
    }
}
