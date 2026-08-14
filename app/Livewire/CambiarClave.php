<?php

namespace App\Livewire;

use App\Services\GestionDeUsuarios;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Cambiar la propia clave, cuando a uno le parezca. Se entra por el nombre del encabezado.
 *
 * Aquí es donde una clave puesta por el administrador pasa a ser de su dueño: hasta entonces la
 * saben dos personas, y un registro que dice «esto lo hizo Ana» con una clave que también conocía
 * el administrador vale lo que valga esa clave compartida. El sistema no obliga a pasar por aquí
 * —así lo decidió el CIIP—, pero es lo que conviene hacer.
 */
class CambiarClave extends Component
{
    public string $actual = '';

    public string $nueva = '';

    public string $repetida = '';

    public string $confirmacion = '';

    /**
     * Cuántas veces se puede fallar la clave actual antes de que haya que esperar.
     *
     * La puerta de entrada ya tenía su límite; esta no lo tenía, y es una puerta igual: quien se
     * encuentre una sesión abierta y sin dueño podría probar claves hasta dar con la buena y
     * quedarse con el usuario para siempre.
     */
    public const INTENTOS_MAXIMOS = 5;

    public const SEGUNDOS_DE_ESPERA = 60;

    /** No es propiedad de Livewire: al ser protegida no viaja al navegador. */
    protected GestionDeUsuarios $gestion;

    public function boot(): void
    {
        $this->gestion = app(GestionDeUsuarios::class);
    }

    public function guardar(): void
    {
        $this->confirmacion = '';
        $this->resetErrorBag();

        $usuario = auth()->user();
        $llave = 'cambiar-clave|'.$usuario->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, self::INTENTOS_MAXIMOS)) {
            $segundos = RateLimiter::availableIn($llave);

            $this->addError('actual', "Demasiados intentos. Vuelve a probar en {$segundos} segundos.");

            return;
        }

        /*
         * Se pide la clave actual aunque ya haya sesión abierta. En el puesto de vigilancia la
         * máquina se queda sola cada dos por tres: sin esto, cualquiera que la encuentre
         * desatendida se queda con el usuario del turno para siempre.
         */
        if (! Hash::check($this->actual, (string) $usuario->password)) {
            RateLimiter::hit($llave, self::SEGUNDOS_DE_ESPERA);

            $this->addError('actual', 'Esa no es tu clave actual.');

            return;
        }

        RateLimiter::clear($llave);

        try {
            $this->gestion->cambiarClave($usuario, $this->nueva, $this->repetida);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->actual = '';
        $this->nueva = '';
        $this->repetida = '';

        $this->confirmacion = 'Clave cambiada. La nueva vale desde ahora.';
    }

    public function render()
    {
        return view('livewire.cambiar-clave');
    }
}
