@extends('layouts.app')

@section('titulo', 'Marcar')
@section('seccion', 'Marcar')

@section('contenido')
    <livewire:marcar />
@endsection

{{-- El lector de QR (jsQR), servido desde el propio servidor —sin CDN—: lo usa el escáner de la
     cámara del carnet. Solo se carga en esta página. --}}
@push('scripts')
    <script src="{{ asset('vendor/jsQR.js') }}"></script>
@endpush
