<?php

namespace Tests;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cada prueba arranca con la caché de roles en blanco. RefreshDatabase revierte la base pero no
     * la memoria estática de Rol, así que un rol creado en una prueba se colaría en la siguiente.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Rol::olvidar();
    }

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
    protected function entrandoComo(?Rol $rol = null): User
    {
        $usuario = User::factory()->make(['rol' => $rol ?? Rol::administrador()]);

        $this->actingAs($usuario);

        return $usuario;
    }
}
