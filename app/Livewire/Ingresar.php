<?php

namespace App\Livewire;

use App\Auditoria\Accion;
use App\Models\User;
use App\Services\Rastro;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * La puerta del sistema: nombre de usuario y clave.
 *
 * Cada quien entra con el suyo. Es la regla 2 del README y no es un capricho: si varias personas
 * comparten una clave, el registro deja de probar quién hizo qué, y este sistema guarda dónde
 * está cada persona a cada hora.
 *
 * Aquí no se pide correo —no se registra el de nadie— ni hay «recordarme»: la máquina del puesto
 * de vigilancia la usan varios turnos, y una sesión que sobrevive al cambio de turno es
 * exactamente el usuario compartido que se quiere evitar.
 */
class Ingresar extends Component
{
    /** Cuántas veces se puede fallar antes de que haya que esperar. */
    public const INTENTOS_MAXIMOS = 5;

    /** Cuánto hay que esperar después de agotarlos. */
    public const SEGUNDOS_DE_ESPERA = 60;

    public string $usuario = '';

    public string $clave = '';

    public function entrar(): void
    {
        $this->validate();

        $this->exigirNoHaberInsistido();

        $usuario = User::where('usuario', $this->usuario)->first();

        // El mismo mensaje tanto si el usuario no existe como si la clave está mal. Decir
        // «ese usuario no existe» le regalaría media respuesta a quien esté probando nombres.
        if (! $usuario || ! Hash::check($this->clave, (string) $usuario->password)) {
            $this->anotarElFallo();

            // Queda anotado el nombre que se intentó, aunque no exista: una racha de intentos
            // fallidos contra nombres inventados es exactamente lo que hay que poder ver después.
            app(Rastro::class)->deja(Accion::INGRESO_FALLIDO, detalle: $this->usuario);

            throw ValidationException::withMessages([
                'usuario' => 'Usuario o clave incorrectos.',
            ]);
        }

        // Este mensaje sí es específico, y se puede: para llegar aquí hubo que dar con la clave
        // buena, así que no se le está contando nada a nadie que no lo supiera ya. Al vigilante
        // que llega a su turno y no puede entrar hay que decirle por qué.
        if (! $usuario->activo) {
            $this->anotarElFallo();

            // Aquí sí se sabe quién era: dio con la clave buena. Que alguien desactivado siga
            // intentando entrar es un dato, no ruido.
            app(Rastro::class)->deja(
                Accion::INGRESO_FALLIDO,
                detalle: 'usuario desactivado',
                usuarioId: $usuario->id,
            );

            throw ValidationException::withMessages([
                'usuario' => 'Ese usuario está desactivado. Habla con el administrador.',
            ]);
        }

        RateLimiter::clear($this->llaveDelLimite());

        // Auth::login() migra la sesión por dentro (SessionGuard::updateSession), así que el
        // identificador con el que se llegó a esta pantalla deja de servir. No hace falta
        // regenerarla a mano, pero sí saber que pasa: sin eso, un identificador conocido de
        // antes seguiría abriendo la sesión de quien acaba de entrar.
        Auth::login($usuario);

        app(Rastro::class)->deja(Accion::INGRESO_CORRECTO);

        $this->clave = '';

        $this->redirectIntended(default: $this->destino($usuario));
    }

    public function render()
    {
        return view('livewire.ingresar');
    }

    /** Dónde cae cada quien al entrar. Lo dice el rol; ver Rol::pantallaDeInicio(). */
    protected function destino(User $usuario): string
    {
        return route($usuario->rol->pantallaDeInicio(), absolute: false);
    }

    /**
     * @throws ValidationException
     */
    protected function exigirNoHaberInsistido(): void
    {
        if (! RateLimiter::tooManyAttempts($this->llaveDelLimite(), self::INTENTOS_MAXIMOS)) {
            return;
        }

        $segundos = RateLimiter::availableIn($this->llaveDelLimite());

        throw ValidationException::withMessages([
            'usuario' => "Demasiados intentos. Vuelve a probar en {$segundos} segundos.",
        ]);
    }

    protected function anotarElFallo(): void
    {
        RateLimiter::hit($this->llaveDelLimite(), self::SEGUNDOS_DE_ESPERA);
    }

    /**
     * Se cuenta por nombre de usuario Y por dirección, no por uno solo: por usuario solo, cualquiera
     * podría dejar fuera a un vigilante fallando cinco veces a propósito con su nombre; por
     * dirección sola, todo el puesto comparte una y se estorbarían entre turnos.
     */
    protected function llaveDelLimite(): string
    {
        return 'ingresar|'.Str::lower($this->usuario).'|'.request()->ip();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:40'],
            'clave' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'usuario.required' => 'Hace falta el usuario.',
            'usuario.max' => 'Ese usuario es más largo de lo que admite el sistema.',
            'clave.required' => 'Hace falta la clave.',
        ];
    }
}
