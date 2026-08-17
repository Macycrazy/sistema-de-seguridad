<?php

use App\Http\Controllers\FotoPersonaController;
use App\Http\Controllers\SalirController;
use App\Services\Marcaje;
use Illuminate\Support\Facades\Route;

// La puerta del sistema: lo único que se ve sin haber entrado.
Route::view('/ingresar', 'ingresar')->middleware('guest')->name('ingresar');

/*
 * De aquí para abajo hay que haber entrado.
 *
 * Lo que abre cada rol está en la tabla del README y se define en AppServiceProvider. Aquí solo
 * se aplica: «rol:supervisor» se lee «de supervisor para arriba», porque los roles son
 * acumulativos.
 */
Route::middleware('auth')->group(function () {
    Route::post('/salir', SalirController::class)->name('salir');

    // El inicio reparte por rol: quien no ve el registro —el vigilante— no tiene un tablero que
    // ofrecer, su turno entero es marcar, así que entra directo ahí. El supervisor y el
    // administrador llegan al inicio con el pulso del edificio y los accesos a lo suyo.
    Route::get('/', function () {
        if (! auth()->user()->can('ver-registro')) {
            return redirect()->route('marcar');
        }

        return view('inicio', ['dentro' => app(Marcaje::class)->cuantosDentro()]);
    })->name('inicio');

    // Herramienta del equipo, no del puesto: no cuelga del inicio ni del menú, pero sigue viva.
    Route::view('/diseno', 'diseno')->name('diseno');

    // La propia clave. Es lo único que se abre con una clave sin cambiar todavía.
    Route::view('/clave', 'clave')->name('clave');

    // Parte 1 · la pantalla que el vigilante tiene abierta todo el turno.
    Route::view('/marcar', 'marcar')->name('marcar');

    // MAQUETA · escanear la cédula con la cámara del teléfono. Es para enseñar la idea y
    // discutirla; no registra movimientos. No forma parte de lo que hay que entregar.
    Route::view('/maqueta/escaneo', 'maqueta-escaneo')->name('maqueta.escaneo');

    // Las fotos no están en una carpeta pública: salen solo por aquí. El permiso lo revisa el
    // propio controlador —con el gate «ver-foto»—, porque también hay que mirar de QUIÉN es la
    // foto, y eso una ruta no lo sabe.
    Route::get('/personas/{persona}/foto', FotoPersonaController::class)->name('persona.foto');

    /*
     * De aquí para abajo se pide un PERMISO, no un rol. Quién tiene cada permiso sale de la tabla
     * «permisos_de_rol» y se cambia desde /roles, así que mover un permiso en esa pantalla mueve
     * también estas puertas, sin tocar código.
     */

    // Parte 2 · el registro es la lista completa del personal, con el histórico de cada quien.
    Route::view('/registro', 'registro')->middleware('can:ver-registro')->name('registro');

    // Meter la nómina: alta manual e importación por Excel, mientras la asociación con el sistema
    // de carnets no la traiga sola.
    Route::view('/trabajadores', 'trabajadores')->middleware('can:gestionar-personal')->name('trabajadores');

    /*
     * Parte 3 · dar de alta, desactivar y cambiar claves y roles.
     *
     * Quién entra lo dice el permiso; qué puede hacer con cada fila —nadie toca a quien esté por
     * encima de su rol— lo decide GestionDeUsuarios, y eso no se configura.
     */
    Route::view('/usuarios', 'usuarios')->middleware('can:gestionar-usuarios')->name('usuarios');

    // Parte 3 · qué puede hacer cada rol. Solo el administrador, y no se puede quitar.
    Route::view('/roles', 'roles')->middleware('can:gestionar-permisos')->name('roles');
});
