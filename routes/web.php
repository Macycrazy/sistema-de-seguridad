<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');
Route::view('/diseno', 'diseno')->name('diseno');

// Parte 1 · la pantalla que el vigilante tiene abierta todo el turno.
// Cuando la parte 3 esté lista, esta ruta va detrás del ingreso con usuario.
Route::view('/marcar', 'marcar')->name('marcar');
