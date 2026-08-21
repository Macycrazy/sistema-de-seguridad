<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use App\Usuarios\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El comando que resuelve el huevo y la gallina: la pantalla de usuarios solo la abre un
 * administrador, y en un servidor recién montado no hay ninguno.
 */
class CrearUsuarioComandoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function crea_el_primer_administrador(): void
    {
        $this->artisan('usuario:crear', [
            'usuario' => 'jefa',
            '--nombre' => 'Carmen Díaz Silva',
            '--cedula' => '12345678',
            '--clave' => 'la-primera-de-todas',
            '--rol' => 'administrador',
        ])->assertSuccessful();

        $creada = User::where('usuario', 'jefa')->firstOrFail();

        $this->assertSame(Rol::administrador(), $creada->rol);
        $this->assertSame('12345678', $creada->cedula);
        $this->assertTrue($creada->activo);
        $this->assertTrue(Hash::check('la-primera-de-todas', $creada->password));
    }

    #[Test]
    public function un_rol_que_no_existe_no_crea_a_nadie(): void
    {
        $this->artisan('usuario:crear', [
            'usuario' => 'jefa',
            '--nombre' => 'Carmen Díaz Silva',
            '--rol' => 'jefazo',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['usuario' => 'jefa']);
    }

    #[Test]
    public function no_pisa_a_un_usuario_que_ya_existe(): void
    {
        User::factory()->create(['usuario' => 'jefa']);

        $this->artisan('usuario:crear', [
            'usuario' => 'jefa',
            '--nombre' => 'Otra Carmen',
            '--clave' => 'la-primera-de-todas',
        ])->assertFailed();

        $this->assertSame(1, User::where('usuario', 'jefa')->count());
    }
}
