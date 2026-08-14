<?php

namespace Tests\Unit\Usuarios;

use App\Usuarios\Rol;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Los roles son acumulativos, como los describe el README: el supervisor hace lo del vigilante y
 * algo más, y el administrador hace todo. Sobre esto se va a apoyar el permiso del bloque B, así
 * que conviene que el orden esté escrito y no solo entendido.
 */
class RolTest extends TestCase
{
    #[Test]
    public function el_administrador_alcanza_a_todos(): void
    {
        $this->assertTrue(Rol::ADMINISTRADOR->alcanza(Rol::ADMINISTRADOR));
        $this->assertTrue(Rol::ADMINISTRADOR->alcanza(Rol::SUPERVISOR));
        $this->assertTrue(Rol::ADMINISTRADOR->alcanza(Rol::VIGILANTE));
    }

    #[Test]
    public function el_supervisor_alcanza_al_vigilante_pero_no_al_administrador(): void
    {
        $this->assertTrue(Rol::SUPERVISOR->alcanza(Rol::VIGILANTE));
        $this->assertTrue(Rol::SUPERVISOR->alcanza(Rol::SUPERVISOR));
        $this->assertFalse(Rol::SUPERVISOR->alcanza(Rol::ADMINISTRADOR));
    }

    #[Test]
    public function el_vigilante_no_alcanza_a_nadie_mas(): void
    {
        $this->assertTrue(Rol::VIGILANTE->alcanza(Rol::VIGILANTE));
        $this->assertFalse(Rol::VIGILANTE->alcanza(Rol::SUPERVISOR));
        $this->assertFalse(Rol::VIGILANTE->alcanza(Rol::ADMINISTRADOR));
    }

    #[Test]
    public function los_tres_roles_del_readme_y_ninguno_mas(): void
    {
        $this->assertSame(
            ['vigilante', 'supervisor', 'administrador'],
            array_column(Rol::cases(), 'value'),
        );
    }
}
