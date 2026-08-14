<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Echa del sistema al usuario que acaban de desactivar.
 *
 * Comprobar «activo» solo al entrar no alcanza: quien ya tuviera la sesión abierta seguiría
 * dentro hasta que se le ocurriera salir, y desactivar a alguien es justo lo que se hace cuando
 * hay prisa por que deje de tener acceso.
 */
class ExigirUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('ingresar')
                ->with('aviso', 'Tu usuario fue desactivado. Habla con el administrador.');
        }

        return $next($request);
    }
}
