<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Registro\RegistroDelDia;
use App\Models\Bitacora;
use App\Models\Persona;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Services\GestionDeUsuarios;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anota_con_el_usuario_de_la_sesion(): void
    {
        $admin = User::factory()->create(['rol' => Rol::ADMINISTRADOR]);
        $this->actingAs($admin);

        app(Auditoria::class)->anota(Auditoria::CAMBIO_PERMISOS);

        $this->assertDatabaseHas('bitacora', [
            'usuario_id' => $admin->id,
            'accion' => Auditoria::CAMBIO_PERMISOS,
        ]);
    }

    #[Test]
    public function sin_sesion_el_usuario_queda_nulo(): void
    {
        app(Auditoria::class)->anota(Auditoria::CARGO_PERSONAL, 'importación');

        $this->assertSame(1, Bitacora::whereNull('usuario_id')->where('accion', Auditoria::CARGO_PERSONAL)->count());
    }

    #[Test]
    public function crear_un_usuario_deja_rastro(): void
    {
        $admin = User::factory()->create(['rol' => Rol::ADMINISTRADOR]);
        $this->actingAs($admin);

        app(GestionDeUsuarios::class)->crear('nuevo', 'Persona Nueva', null, Rol::VIGILANTE, 'clave1234', $admin);

        $this->assertDatabaseHas('bitacora', [
            'usuario_id' => $admin->id,
            'accion' => Auditoria::CREO_USUARIO,
            'sobre' => 'nuevo',
        ]);
    }

    #[Test]
    public function exportar_el_registro_deja_rastro(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));

        Livewire::test(RegistroDelDia::class)->call('exportar');

        $this->assertSame(1, Bitacora::where('accion', Auditoria::EXPORTO_REGISTRO)->count());
    }

    #[Test]
    public function consultar_el_historico_de_una_persona_deja_rastro(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::ADMINISTRADOR]));
        $persona = Persona::create(['cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA PÉREZ', 'activo' => true]);

        Livewire::test(RegistroDelDia::class)->call('abrirPanel', (string) $persona->id);

        $this->assertDatabaseHas('bitacora', [
            'accion' => Auditoria::CONSULTO_HISTORICO,
            'sobre' => '12345678',
        ]);
    }

    #[Test]
    public function la_bitacora_es_inmutable(): void
    {
        // El modelo no lleva timestamps de Eloquent: la hora es «ocurrio_en» y no se pisa.
        $this->assertFalse((new Bitacora)->usesTimestamps());
    }
}
