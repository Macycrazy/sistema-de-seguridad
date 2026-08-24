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

    // El estacionamiento visto desde el portón. Sin permiso propio: es para el guardia, como marcar.
    Route::view('/estacionamiento', 'estacionamiento')->name('estacionamiento');

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

    // El mirador del registro: las cuentas de un tramo de fechas. Mismo permiso que el registro:
    // quien ve el detalle ve su resumen.
    Route::view('/reportes', 'reportes')->middleware('can:ver-registro')->name('reportes');

    // Lo que ahora mismo merece atención: permanencias largas y aforo superado. Mismo permiso.
    Route::view('/alertas', 'alertas')->middleware('can:ver-registro')->name('alertas');

    // La agenda de visitas esperadas: recepción anticipa quién viene, la puerta lo confirma.
    Route::view('/visitas', 'visitas')->middleware('can:ver-visitas')->name('visitas');

    // El panel de administración: reúne en un solo sitio todo lo de admin (personal, organigrama,
    // usuarios, edificio, ajustes, auditoría, roles), para que el menú de arriba no se llene. Lo
    // abre quien tenga cualquiera de esos permisos; cada tarjeta, además, revisa el suyo.
    Route::view('/administracion', 'administracion')->middleware('can:ver-administracion')->name('administracion');

    // Meter la nómina: alta manual e importación por Excel, mientras la asociación con el sistema
    // de carnets no la traiga sola.
    Route::view('/trabajadores', 'trabajadores')->middleware('can:ver-personal')->name('trabajadores');

    // El organigrama como dato: la estructura de unidades a la que pertenece el personal.
    Route::view('/organigrama', 'organigrama')->middleware('can:ver-organigrama')->name('organigrama');

    // El catálogo de oficinas del edificio, que la puerta ofrece al marcar el piso de un invitado.
    Route::view('/edificio', 'edificio')->middleware('can:ver-edificio')->name('edificio');

    // El catálogo de puestos del estacionamiento: las plazas numeradas donde se para cada vehículo.
    Route::view('/puestos', 'puestos')->middleware('can:ver-puestos')->name('puestos');

    Route::view('/pases', 'pases')->middleware('can:ver-pases')->name('pases');

    // El rostro es un dato más de la ficha del personal, así que va con sus permisos y no con unos
    // propios: si esto se decide no usar, se quita entero sin dejar permisos huérfanos.
    Route::view('/rostros', 'rostros')->middleware('can:ver-personal')->name('rostros');

    // Las reglas de tiempo del marcaje, ajustables sin reprogramar.
    Route::view('/ajustes', 'ajustes')->middleware('can:ver-ajustes')->name('ajustes');

    // La bitácora de auditoría: quién consultó, exportó o cambió qué.
    Route::view('/auditoria', 'auditoria')->middleware('can:ver-auditoria')->name('auditoria');

    /*
     * Parte 3 · dar de alta, desactivar y cambiar claves y roles.
     *
     * Quién entra lo dice el permiso; qué puede hacer con cada fila —nadie toca a quien esté por
     * encima de su rol— lo decide GestionDeUsuarios, y eso no se configura.
     */
    Route::view('/usuarios', 'usuarios')->middleware('can:ver-usuarios')->name('usuarios');

    // Parte 3 · qué puede hacer cada rol. Solo el administrador, y no se puede quitar.
    Route::view('/roles', 'roles')->middleware('can:gestionar-permisos')->name('roles');

    // Respaldos de la base: crear, descargar y borrar. Un respaldo es toda la data, solo admin.
    Route::view('/respaldos', 'respaldos')->middleware('can:gestionar-respaldos')->name('respaldos');

    // Asociación con el sistema de carnets: probar la conexión y la lectura del QR.
    Route::view('/asociacion', 'asociacion')->middleware('can:gestionar-ajustes')->name('asociacion');
});
