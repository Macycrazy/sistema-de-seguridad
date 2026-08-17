<?php

namespace Tests\Unit\Registro;

use App\Services\Registro\Ente;
use App\Services\Registro\Movimiento;
use App\Services\Registro\Persona;
use App\Services\Registro\RegistroInventado;
use App\Services\Registro\Sentido;
use App\Services\Registro\TipoDePersona;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RegistroInventadoTest extends TestCase
{
    private function fuente(): RegistroInventado
    {
        return new RegistroInventado;
    }

    #[Test]
    public function los_datos_son_los_mismos_en_cada_instancia(): void
    {
        // Si esto falla, la tabla parpadea en el navegador: Livewire rehace el componente
        // en cada interacción y cada vez vería datos distintos.
        $hoy = CarbonImmutable::today();

        $primera = $this->fuente()->movimientosDelDia($hoy);
        $segunda = $this->fuente()->movimientosDelDia($hoy);

        $this->assertSame($primera->count(), $segunda->count());
        $this->assertSame(
            $primera->map(fn (Movimiento $m) => $m->persona->id.'|'.$m->ocurrioEn->toDateTimeString())->all(),
            $segunda->map(fn (Movimiento $m) => $m->persona->id.'|'.$m->ocurrioEn->toDateTimeString())->all(),
        );
    }

    #[Test]
    public function los_datos_no_dependen_del_motor_aleatorio_global(): void
    {
        // Esta es la regresión que costó encontrar: si la generación se apoyara en el
        // motor global (Faker, mt_rand, shuffle), bastaría con que cualquier otro código
        // del proceso consumiera una tirada para desplazar toda la secuencia. Carbon lo
        // hace al inicializarse. El generador tiene que ser inmune a esto.
        $hoy = CarbonImmutable::today();

        $antes = $this->fuente()->movimientosDelDia($hoy)->count();

        mt_srand(12345);
        for ($i = 0; $i < 37; $i++) {
            mt_rand();
        }
        str_shuffle('abcdefghij');

        $despues = $this->fuente()->movimientosDelDia($hoy)->count();

        $this->assertSame($antes, $despues);
    }

    #[Test]
    public function ningun_movimiento_se_derrama_al_dia_siguiente(): void
    {
        // Una salida que cruzara la medianoche aparecería en la lista del día siguiente
        // y descuadraría el contador de quién quedó dentro.
        //
        // El domingo el puesto no registra, así que ese día no sirve para esta prueba: si «ayer»
        // cae domingo —cuando hoy es lunes— se toma el sábado, que sí tiene movimientos.
        $ayer = CarbonImmutable::today()->subDay();

        if ($ayer->isSunday()) {
            $ayer = $ayer->subDay();
        }

        $movimientos = $this->fuente()->movimientosDelDia($ayer);

        $this->assertGreaterThan(0, $movimientos->count());

        foreach ($movimientos as $movimiento) {
            $this->assertSame($ayer->toDateString(), $movimiento->ocurrioEn->toDateString());
        }
    }

    #[Test]
    public function hoy_siempre_tiene_movimientos(): void
    {
        // Las fechas se generan relativas a hoy, no cableadas: se abra el día que se abra.
        $this->assertGreaterThan(0, $this->fuente()->movimientosDelDia(CarbonImmutable::today())->count());
    }

    #[Test]
    public function el_dia_viene_ordenado_del_mas_reciente_al_mas_viejo(): void
    {
        $horas = $this->fuente()
            ->movimientosDelDia(CarbonImmutable::today())
            ->map(fn (Movimiento $m) => $m->ocurrioEn->getTimestamp())
            ->all();

        $ordenadas = $horas;
        rsort($ordenadas);

        $this->assertSame($ordenadas, $horas);
    }

    #[Test]
    public function filtrar_por_tipo_deja_solo_ese_tipo(): void
    {
        $invitados = $this->fuente()->movimientosDelDia(CarbonImmutable::today(), TipoDePersona::Invitado);

        $this->assertGreaterThan(0, $invitados->count());

        foreach ($invitados as $movimiento) {
            $this->assertSame(TipoDePersona::Invitado, $movimiento->persona->tipo);
        }
    }

    #[Test]
    public function filtrar_por_ente_deja_solo_ese_ente(): void
    {
        $hoy = CarbonImmutable::today();

        foreach (Ente::cases() as $ente) {
            $movimientos = $this->fuente()->movimientosDelDia($hoy, null, $ente);

            $this->assertGreaterThan(0, $movimientos->count(), "El ente {$ente->value} no tiene movimientos hoy.");

            foreach ($movimientos as $movimiento) {
                $this->assertSame($ente, $movimiento->persona->ente);
            }
        }
    }

    #[Test]
    public function los_tres_entes_suman_el_total_de_trabajadores(): void
    {
        $hoy = CarbonImmutable::today();
        $fuente = $this->fuente();

        $porEnte = 0;
        foreach (Ente::cases() as $ente) {
            $porEnte += $fuente->movimientosDelDia($hoy, null, $ente)->count();
        }

        $trabajadores = $fuente->movimientosDelDia($hoy, TipoDePersona::Trabajador)->count();

        $this->assertSame($trabajadores, $porEnte);
    }

    #[Test]
    public function los_invitados_no_pertenecen_a_ningun_ente(): void
    {
        $invitados = $this->fuente()->movimientosDelDia(CarbonImmutable::today(), TipoDePersona::Invitado);

        foreach ($invitados as $movimiento) {
            $this->assertNull($movimiento->persona->ente);
        }
    }

    #[Test]
    public function dentro_cuenta_a_quien_entro_y_no_ha_salido(): void
    {
        $fuente = $this->fuente();
        $hoy = CarbonImmutable::today();

        // Recuento independiente: por persona, quedarse con su último movimiento.
        $ultimoPorPersona = [];

        foreach ($fuente->movimientosDelDia($hoy)->sortBy(fn (Movimiento $m) => $m->ocurrioEn->getTimestamp()) as $movimiento) {
            $ultimoPorPersona[$movimiento->persona->id] = $movimiento->sentido;
        }

        $esperado = count(array_filter($ultimoPorPersona, fn (Sentido $s) => $s === Sentido::Entrada));

        $this->assertSame($esperado, $fuente->dentroEn($hoy));
    }

    #[Test]
    public function un_olvido_de_ayer_no_infla_el_contador_de_hoy(): void
    {
        // Si el recuento mirara todo el histórico, quien entró un día y se fue sin que le
        // anotaran la salida seguiría contando como «dentro» todos los días siguientes,
        // y el contador se separaría de la realidad sin manera de volver.
        $fuente = $this->fuente();
        $hoy = CarbonImmutable::today();

        $personasQueSeMovieronHoy = $fuente->movimientosDelDia($hoy)
            ->map(fn (Movimiento $m) => $m->persona->id)
            ->unique();

        $this->assertLessThanOrEqual($personasQueSeMovieronHoy->count(), $fuente->dentroEn($hoy));
    }

    #[Test]
    public function la_cifra_de_dentro_es_creible_para_un_puesto_de_vigilancia(): void
    {
        $dentro = $this->fuente()->dentroEn(CarbonImmutable::today());

        $this->assertGreaterThan(150, $dentro);
        $this->assertLessThan(230, $dentro);
    }

    #[Test]
    public function hay_gente_sin_documento_registrado(): void
    {
        // El listado real trae cinco personas con «*» en la columna de cédula. Si los
        // datos de prueba fueran todos perfectos, la pantalla nunca se probaría contra
        // este caso y reventaría con la lista de verdad.
        $sinDocumento = $this->todasLasPersonas()->filter(fn (Persona $p) => ! $p->tieneDocumento());

        $this->assertGreaterThan(0, $sinDocumento->count());
        $this->assertSame('Sin documento', $sinDocumento->first()->documento());
    }

    #[Test]
    public function hay_gente_con_pasaporte_en_vez_de_cedula(): void
    {
        // En el listado real: RD7368881, FZ350899, N01870456…
        $conPasaporte = $this->todasLasPersonas()
            ->filter(fn (Persona $p) => $p->tieneDocumento() && ! str_starts_with((string) $p->cedula, 'V-'));

        $this->assertGreaterThan(0, $conPasaporte->count());

        // Y se muestra tal cual, sin convertirse en «V-0».
        foreach ($conPasaporte->take(5) as $persona) {
            $this->assertSame($persona->cedula, $persona->documento());
            $this->assertStringNotContainsString('V-0', $persona->documento());
        }
    }

    #[Test]
    public function ningun_documento_se_muestra_como_v_cero(): void
    {
        foreach ($this->todasLasPersonas() as $persona) {
            $this->assertNotSame('V-0', $persona->documento());
        }
    }

    #[Test]
    public function el_personal_de_venapp_no_trae_cargo(): void
    {
        // Así viene en el listado real, y el sistema no debe inventarlo.
        $venapp = $this->todasLasPersonas()->filter(fn (Persona $p) => $p->ente === Ente::Venapp);

        $this->assertGreaterThan(0, $venapp->count());

        foreach ($venapp as $persona) {
            $this->assertNull($persona->cargo);
        }
    }

    #[Test]
    public function el_personal_de_ciip_si_trae_cargo_y_piso(): void
    {
        $ciip = $this->todasLasPersonas()->filter(fn (Persona $p) => $p->ente === Ente::Ciip);

        foreach ($ciip->take(20) as $persona) {
            $this->assertNotNull($persona->cargo);
            $this->assertNotNull($persona->piso);
            $this->assertNotNull($persona->dependencia);
        }
    }

    #[Test]
    public function hay_fichas_con_los_apellidos_repetidos_en_los_nombres(): void
    {
        // Está así en el listado real (fila 30: «HERRERA MEDINA | HERRERA MEDINA»).
        $malCargadas = $this->todasLasPersonas()->filter(fn (Persona $p) => $p->nombresRepitenApellidos());

        $this->assertGreaterThan(0, $malCargadas->count());
    }

    #[Test]
    public function una_ficha_mal_cargada_no_se_muestra_con_el_nombre_repetido(): void
    {
        $mala = new Persona(
            id: 'p-1',
            cedula: 'V-12.393.986',
            apellidos: 'Herrera Medina',
            nombres: 'Herrera Medina',
            tipo: TipoDePersona::Trabajador,
        );

        $this->assertTrue($mala->nombresRepitenApellidos());
        $this->assertSame('Herrera Medina', $mala->nombre());
        $this->assertSame('Herrera Medina', $mala->nombreCompleto());

        // Pero los campos crudos quedan intactos: el reporte tiene que reflejar la
        // fuente para que el error se pueda ver y corregir.
        $this->assertSame('Herrera Medina', $mala->apellidos);
        $this->assertSame('Herrera Medina', $mala->nombres);
    }

    #[Test]
    public function una_ficha_bien_cargada_no_se_marca_como_defectuosa(): void
    {
        $buena = new Persona(
            id: 'p-2',
            cedula: 'V-12.345.678',
            apellidos: 'Pérez González',
            nombres: 'José Rafael',
            tipo: TipoDePersona::Trabajador,
        );

        $this->assertFalse($buena->nombresRepitenApellidos());
        $this->assertSame('José Rafael Pérez González', $buena->nombre());
    }

    #[Test]
    public function el_nombre_se_arma_en_los_dos_ordenes(): void
    {
        $persona = new Persona(
            id: 'p-1',
            cedula: 'V-12.345.678',
            apellidos: 'Pérez González',
            nombres: 'José Rafael',
            tipo: TipoDePersona::Trabajador,
        );

        $this->assertSame('José Rafael Pérez González', $persona->nombre());
        $this->assertSame('Pérez González, José Rafael', $persona->nombreCompleto());
    }

    #[Test]
    public function se_busca_por_nombre_sin_importar_tildes_ni_mayusculas(): void
    {
        $fuente = $this->fuente();
        $alguien = $fuente->movimientosDelDia(CarbonImmutable::today())->first()->persona;

        // Se busca por el nombre completo: un apellido suelto como «Rodríguez» devuelve
        // más gente de la que cabe en las sugerencias, y eso está bien —el vigilante
        // teclea la cédula, no juega a las adivinanzas.
        $tecleado = Str::upper(Str::ascii($alguien->nombre()));

        $hallados = $fuente->buscarPersonas($tecleado);

        $this->assertTrue($hallados->contains(fn (Persona $p) => $p->id === $alguien->id));
    }

    #[Test]
    public function las_sugerencias_nunca_vuelcan_la_lista_completa(): void
    {
        // «De a una cédula»: aunque el apellido sea el más común de la nómina, la
        // pantalla devuelve un puñado y nada más.
        $hallados = $this->fuente()->buscarPersonas('rodriguez', limite: 8);

        $this->assertLessThanOrEqual(8, $hallados->count());
    }

    #[Test]
    public function se_busca_por_documento_con_o_sin_puntos(): void
    {
        $fuente = $this->fuente();

        $alguien = $this->todasLasPersonas()
            ->first(fn (Persona $p) => str_starts_with((string) $p->cedula, 'V-'));

        // El vigilante teclea números sueltos; el lector de carnet mete el formato largo.
        $soloDigitos = preg_replace('/[^0-9]/', '', $alguien->cedula);

        $this->assertTrue($fuente->buscarPersonas($alguien->cedula)->contains(fn (Persona $p) => $p->id === $alguien->id));
        $this->assertTrue($fuente->buscarPersonas($soloDigitos)->contains(fn (Persona $p) => $p->id === $alguien->id));
    }

    #[Test]
    public function una_busqueda_demasiado_corta_no_devuelve_nada(): void
    {
        // De a una cédula: la pantalla nunca vuelca la lista completa del personal.
        $this->assertTrue($this->fuente()->buscarPersonas('a')->isEmpty());
    }

    #[Test]
    public function el_historico_trae_varios_dias_de_esa_persona(): void
    {
        $fuente = $this->fuente();
        $alguien = $fuente->movimientosDelDia(CarbonImmutable::today())->first()->persona;

        $historico = $fuente->historicoDe($alguien->id);

        $this->assertGreaterThan(1, $historico->count());

        foreach ($historico as $movimiento) {
            $this->assertSame($alguien->id, $movimiento->persona->id);
        }
    }

    #[Test]
    public function del_invitado_se_guarda_lo_minimo(): void
    {
        // Nombre y a quién visita. Ni ente, ni cargo, ni piso, ni dependencia.
        $invitado = $this->fuente()
            ->movimientosDelDia(CarbonImmutable::today(), TipoDePersona::Invitado)
            ->first()
            ->persona;

        $this->assertNotNull($invitado->visitaA);
        $this->assertNull($invitado->dependencia);
        $this->assertNull($invitado->cargo);
        $this->assertNull($invitado->piso);
        $this->assertNull($invitado->ente);
    }

    /** @return Collection<int, Persona> */
    private function todasLasPersonas(): Collection
    {
        return (new \ReflectionProperty(RegistroInventado::class, 'personas'))
            ->getValue($this->fuente())
            ->values();
    }
}
