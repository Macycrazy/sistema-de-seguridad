<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cierra la sesión y devuelve a la puerta.
 *
 * Va por POST, no por un enlace: un GET lo dispara cualquier cosa que cargue una URL —una imagen
 * en otra página, un enlace pulsado por error— y sacar a un vigilante de su turno a mitad de un
 * marcaje no es un chiste.
 *
 * No lleva lógica: cierra y delega, como pide el README.
 */
class SalirController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::logout();

        // Cerrar la sesión de Laravel no borra lo que hubiera guardado en la de PHP. Sin estas
        // dos líneas, el identificador de sesión con el que se estuvo dentro seguiría sirviendo
        // y el token del formulario también.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('ingresar');
    }
}
