@extends('layouts.app')

@section('titulo', 'Roles y permisos')
@section('seccion', 'Roles')

@section('contenido')
    <livewire:roles.permisos-por-rol />
@endsection
