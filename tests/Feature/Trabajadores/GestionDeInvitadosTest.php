<?php

namespace Tests\Feature\Trabajadores;

use App\Models\Persona;
use App\Services\GestionDeInvitados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GestionDeInvitadosTest extends TestCase
{
    use RefreshDatabase;

    private function invitado(): Persona
    {
        return Persona::create([
            'cedula' => '99887766', 'tipo' => Persona::INVITADO,
            'nombre' => 'PEDRO', 'motivo' => 'reunion', 'piso' => '2-1', 'activo' => true,
        ]);
    }

    #[Test]
    public function corrige_los_datos_del_invitado(): void
    {
        $inv = $this->invitado();

        app(GestionDeInvitados::class)->editar(
            invitado: $inv,
            nombre: 'Pedro Pérez',
            nacionalidad: 'E',
            motivo: 'entrega de equipos',
            piso: '3-2',
        );

        $this->assertDatabaseHas('personas', [
            'id' => $inv->id, 'tipo' => 'invitado',
            'nombre' => 'PEDRO PÉREZ', 'nacionalidad' => 'E',
            'motivo' => 'entrega de equipos', 'piso' => '3-2',
        ]);
    }

    #[Test]
    public function sin_motivo_no_guarda(): void
    {
        $this->expectException(ValidationException::class);

        app(GestionDeInvitados::class)->editar(
            invitado: $this->invitado(),
            nombre: 'Pedro',
            nacionalidad: 'V',
            motivo: '  ',
            piso: null,
        );
    }

    #[Test]
    public function no_edita_a_un_trabajador_como_si_fuera_invitado(): void
    {
        $trabajador = Persona::create([
            'cedula' => '12345678', 'tipo' => Persona::TRABAJADOR, 'nombre' => 'ANA', 'activo' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(GestionDeInvitados::class)->editar(
            invitado: $trabajador,
            nombre: 'Ana',
            nacionalidad: 'V',
            motivo: 'algo',
            piso: null,
        );
    }
}
