<?php

namespace Tests\Feature\Estacionamiento;

use App\Livewire\Estacionamiento\Panel;
use App\Models\Puesto;
use App\Models\User;
use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use App\Usuarios\Rol;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PantallaEstacionamientoTest extends TestCase
{
    use RefreshDatabase;

    private function estadia(string $placa, array $extra = []): VehiculoFijo
    {
        return VehiculoFijo::create(array_merge([
            'placa' => $placa,
            'tipo_vehiculo' => DatosVehiculo::CARRO,
            'entro_en' => CarbonImmutable::now()->subHour(),
        ], $extra));
    }

    #[Test]
    public function el_guardia_la_ve_sin_permiso_especial(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->get(route('estacionamiento'))->assertOk();
    }

    #[Test]
    public function lista_un_vehiculo_dentro_con_su_placa_y_conductor(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->estadia('AB123CD', ['conductor_nombre' => 'ANA PÉREZ']);

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('AB123CD')
            ->assertSee('ANA PÉREZ');
    }

    #[Test]
    public function desde_el_panel_se_le_asigna_el_puesto_a_un_vehiculo_dentro(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $puesto = Puesto::create(['codigo' => 'A-1', 'orden' => 1]);
        $estadia = $this->estadia('AB123CD');

        Livewire::test(Panel::class)
            ->call('asignarPuesto', $estadia->id, (string) $puesto->id);

        $this->assertSame($puesto->id, $estadia->fresh()->puesto_id);
    }

    #[Test]
    public function el_panel_avisa_de_los_que_pernoctan(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->estadia('AB123CD', ['entro_en' => CarbonImmutable::yesterday()->setTime(21, 0)]);

        Livewire::test(Panel::class)
            ->assertOk()
            ->assertSee('Pernoctan')
            ->assertSee('AB123CD');
    }

    #[Test]
    public function el_movimiento_muestra_el_dia_que_se_elige_y_no_solo_hoy(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $anteayer = CarbonImmutable::today()->subDays(2);
        $this->estadia('VIE123', [
            'entro_en' => $anteayer->setTime(8, 0),
            'salio_en' => $anteayer->setTime(17, 0),
            'conductor_nombre' => 'ANA PÉREZ',
            'salida_conductor_nombre' => 'LUIS GÓMEZ',
        ]);

        $panel = Livewire::test(Panel::class)->set('verHistorial', true);

        // Hoy no pasó nada: el vehículo de anteayer no tiene por qué salir.
        $panel->assertDontSee('VIE123');

        // Al elegir su día aparecen sus dos asientos, cada uno con quien lo movió.
        $panel->set('fechaHistorial', $anteayer->format('Y-m-d'))
            ->assertSee('VIE123')
            ->assertSee('ANA PÉREZ')
            ->assertSee('LUIS GÓMEZ');
    }

    #[Test]
    public function una_fecha_ilegible_en_el_movimiento_cae_a_hoy_sin_reventar(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->estadia('HOY123', ['salio_en' => CarbonImmutable::now()->subMinutes(10)]);

        Livewire::test(Panel::class)
            ->set('verHistorial', true)
            ->set('fechaHistorial', 'no-es-una-fecha')
            ->assertOk()
            ->assertSee('HOY123');
    }

    #[Test]
    public function al_buscar_una_placa_sale_su_historial_y_no_solo_si_esta_dentro(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        // Una estadía ya cerrada: no está dentro, así que sin historial no se vería en ninguna parte.
        $this->estadia('VIE123', [
            'entro_en' => CarbonImmutable::today()->subDays(3)->setTime(8, 0),
            'salio_en' => CarbonImmutable::today()->subDays(3)->setTime(17, 0),
            'salida_conductor_nombre' => 'LUIS GÓMEZ',
        ]);

        Livewire::test(Panel::class)
            ->set('busqueda', 'vie')
            ->assertSee('Historial')
            ->assertSee('VIE123')
            ->assertSee('LUIS GÓMEZ');
    }

    #[Test]
    public function el_formulario_de_anotar_se_abre_aunque_no_haya_puestos_cargados(): void
    {
        // El botón se veía siempre pero el formulario estaba escondido tras «si hay puestos»:
        // sin plazas cargadas se pulsaba y no pasaba nada. El puesto es opcional desde que un
        // vehículo puede entrar sin plaza.
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $this->assertSame(0, Puesto::count());

        Livewire::test(Panel::class)
            ->call('abrirFijo')
            ->assertSee('De la flota')
            ->call('abrirFlota')
            ->assertSee('Agregar a la flota');
    }

    #[Test]
    public function un_vehiculo_de_la_flota_se_anota_desde_su_fila_del_catalogo(): void
    {
        // Cargarlo en la flota es catálogo, no entrada. Antes había que cerrar la flota, abrir
        // «Anotar vehículo» y buscarlo en el desplegable, sin que nada lo dijera.
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO]);

        Livewire::test(Panel::class)
            ->call('abrirFlota')
            ->assertSee('Anotar entrada')
            ->call('anotarDeLaFlota', $flota->id)
            ->assertSet('agregandoFijo', true)
            ->assertSet('gestionandoFlota', false)
            ->assertSet('flotaFija', (string) $flota->id)
            ->call('agregarFijo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehiculos_fijos', ['placa' => 'EMP001', 'flota_id' => $flota->id, 'salio_en' => null]);
    }

    #[Test]
    public function un_vehiculo_de_la_flota_que_ya_esta_dentro_no_se_ofrece_para_anotar(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $flota = VehiculoDeFlota::create(['placa' => 'EMP001', 'tipo_vehiculo' => DatosVehiculo::CARRO]);
        $this->estadia('EMP001', ['flota_id' => $flota->id]);

        Livewire::test(Panel::class)
            ->call('abrirFlota')
            ->assertSee('está dentro')
            ->call('anotarDeLaFlota', $flota->id)
            ->assertSet('agregandoFijo', false);
    }

    #[Test]
    public function la_pantalla_se_refresca_sola_salvo_con_un_formulario_abierto(): void
    {
        // La puerta también mueve vehículos ahora: quien tenga esto abierto vería un vehículo que
        // ya se fue. Pero un refresco a media escritura le movería el sitio bajo las manos a quien
        // está tecleando una placa.
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));

        $panel = Livewire::test(Panel::class);
        $this->assertStringContainsString('wire:poll', $panel->html());

        $panel->call('abrirFijo');
        $this->assertStringNotContainsString('wire:poll', $panel->html(), 'Con el formulario abierto no se sondea.');

        $panel->call('cancelarFijo');
        $this->assertStringContainsString('wire:poll', $panel->html());
    }

    #[Test]
    public function buscar_por_placa_deja_solo_la_que_coincide(): void
    {
        $this->actingAs(User::factory()->create(['rol' => Rol::vigilante()]));
        $this->estadia('ABC123');
        $this->estadia('XYZ789');

        Livewire::test(Panel::class)
            ->set('busqueda', 'abc')
            ->assertSee('ABC123')
            ->assertDontSee('XYZ789');
    }
}
