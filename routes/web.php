<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');
Route::view('/diseno', 'diseno')->name('diseno');
Route::view('/registro', 'registro')->name('registro');
