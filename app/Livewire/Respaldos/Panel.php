<?php

namespace App\Livewire\Respaldos;

use App\Services\Auditoria\Auditoria;
use App\Services\Respaldos\Respaldos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * La pantalla de respaldos: crear una copia, verlas, descargarlas y borrarlas.
 *
 * Solo el administrador («gestionar-respaldos»): un respaldo es toda la base. No decide nada; se
 * lo pide todo al servicio Respaldos y anota en la bitácora lo que se hace.
 */
class Panel extends Component
{
    public string $aviso = '';

    public string $error = '';

    public function boot(): void
    {
        Gate::authorize('gestionar-respaldos');
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function respaldos(): Collection
    {
        return app(Respaldos::class)->listar();
    }

    public function crear(): void
    {
        $this->reset('aviso', 'error');

        try {
            $resultado = app(Respaldos::class)->crear();
        } catch (Throwable $e) {
            $this->error = 'No se pudo crear el respaldo. '.$e->getMessage();

            return;
        }

        unset($this->respaldos);
        app(Auditoria::class)->respaldo('creó '.$resultado['archivo']);
        $this->aviso = 'Respaldo creado: '.$resultado['archivo'].'.';
    }

    public function descargar(string $nombre): ?StreamedResponse
    {
        if (! app(Respaldos::class)->existe($nombre)) {
            $this->error = 'Ese respaldo ya no está.';

            return null;
        }

        app(Auditoria::class)->respaldo('descargó '.basename($nombre));

        return app(Respaldos::class)->descargar($nombre);
    }

    public function eliminar(string $nombre): void
    {
        app(Respaldos::class)->eliminar($nombre);
        unset($this->respaldos);
        app(Auditoria::class)->respaldo('borró '.basename($nombre));
        $this->aviso = 'Respaldo borrado.';
    }

    public function render()
    {
        return view('livewire.respaldos.panel');
    }
}
