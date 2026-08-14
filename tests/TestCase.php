<?php

namespace Tests;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Abre una sesión para la prueba.
     *
     * Desde la parte 3, ninguna pantalla del sistema se ve sin haber entrado. Las pruebas que no
     * van de permisos —las de las partes 1 y 2— solo necesitan que haya alguien dentro: entran
     * con esto y siguen a lo suyo.
     *
     * Por omisión entra un administrador, que es el rol que alcanza a todo: así, cuando el
     * bloque B ponga el permiso por rol, estas pruebas no se caen por algo que no están mirando.
     *
     * El usuario NO se guarda en la base, a propósito: así esto sirve igual en las pruebas que no
     * montan tablas, como las del registro.
     */
    protected function entrandoComo(Rol $rol = Rol::ADMINISTRADOR): User
    {
        $usuario = User::factory()->make(['rol' => $rol]);

        $this->actingAs($usuario);

        return $usuario;
    }
}
