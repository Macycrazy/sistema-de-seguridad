@extends('layouts.app')

@section('titulo', 'Usuarios')
@section('seccion', 'Usuarios')

@section('contenido')
    <livewire:usuarios.lista-de-usuarios />
@endsection
