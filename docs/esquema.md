# Cómo se ve una persona y cómo se ve un movimiento

Esto es lo que el README pide acordar el primer día entre las tres partes. Lo definió la
**parte 1** porque es la primera que necesitó escribir en la base. **Está abierto a discusión**:
si a la parte 2 o a la 3 les falta una columna, se cambia aquí y se avisa, no se añade suelta en
una rama.

Dos tablas: `personas` y `movimientos`.

---

## `personas`

Quien puede pasar por la puerta. Un trabajador y un invitado viven en la **misma tabla** porque en
la puerta se tratan igual: se teclea una cédula y se marca. La cédula es única entre los dos —
nadie es trabajador e invitado a la vez.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `cedula` | varchar(20), indexada | **Solo el número**, sin puntos ni letra. Ver «la cédula» abajo. |
| `nacionalidad` | char(1), por omisión `V` | La letra: `V`, `E` o `J`. **Única junto a `cedula`.** |
| `tipo` | varchar(20), indexada | `trabajador` o `invitado` |
| `nombre` | varchar(120) | |
| `dependencia` | varchar(120), nula | Solo trabajador. Viene del sistema de carnets. En pantalla se rotula «Gerencia». |
| `piso` | varchar(10), nula | Dónde labora, o a dónde va. **Normalizado**: ver «el piso». |
| `foto_ruta` | varchar(255), nula | Solo trabajador. Ruta relativa dentro del disco privado: `fotos/12345678.jpg`. Ver «las fotos». |
| `motivo` | varchar(120), nula | Solo invitado: el motivo de la visita **de la última vez**. |
| `activo` | boolean, por omisión `true` | |
| `created_at`, `updated_at` | timestamps | |

Las columnas que solo aplican a un tipo van **nulas** en el otro. Del invitado se guarda lo mínimo:
nombre y **motivo de la visita**. Nada de foto del documento, teléfono ni dirección.

> **AVISO A LAS PARTES 2 Y 3 · lo único de `personas` cambió.** Ya no es `cedula` a secas, sino la
> pareja **`(nacionalidad, cedula)`**. Antes la letra se tiraba al normalizar, y eso hacía que
> `V-12345678` y `E-12345678` fueran la misma ficha: al segundo que llegara le salían el nombre, la
> foto y la dependencia del primero. Si buscáis por cédula, hay que llevar también la letra o
> quedaros con el valor por omisión `V` —que es lo que se venía dando por sentado—. Las fichas que
> ya estaban quedaron todas en `V`; si alguna era de un extranjero, se corrige a mano.

> **El vehículo ya NO está aquí.** Estuvo en cinco columnas de esta tabla, y eso daba por sentado
> que cada quien tiene uno solo. No es cierto —hay quien viene en carro unos días y en moto
> otros—, así que ahora es la tabla `vehiculos`, una fila por vehículo. La migración
> `crear_tabla_vehiculos` mudó lo que había y quitó las columnas.

---

## `vehiculos`

Los vehículos de una persona. **Puede tener más de uno**, y en la puerta se marca cuál trae ese día.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `persona_id` | FK → `personas`, `CASCADE` al borrar | Se van con ella: no le sirven a nadie más. El histórico no se toca, porque los movimientos llevan su copia. |
| `tipo` | varchar(10) | `carro` o `moto`. **Obligatorio.** |
| `marca` | varchar(40), nula | Para reconocerlo de un vistazo. |
| `modelo` | varchar(40), nula | |
| `color` | varchar(30), nula | |
| `placa` | varchar(15), indexada | **Obligatoria y normalizada.** Es lo único que lo identifica de verdad. |
| `created_at`, `updated_at` | timestamps | |

Índice único `(persona_id, placa)`: la misma persona no puede tener dos veces la misma placa. **Dos
personas sí pueden compartirla** —un carro familiar que hoy trae uno y mañana otro—, así que no es
única a secas.

> El README dice «a quién viene a ver». Al usar la pantalla quedó claro que lo que se anota es el
> **motivo** —«videoconferencia», «consultor», «entrega de material»—, no el nombre de un
> anfitrión, así que la columna se llama `motivo`. Si el equipo prefiere volver a la letra del
> README, se habla y se cambia en las dos tablas.

Un trabajador que ya no labora aquí **no se borra**: se pone `activo = false` y su histórico queda.

---

## `movimientos`

Una entrada o una salida: el asiento que deja el botón de la puerta.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `persona_id` | FK → `personas`, `RESTRICT` al borrar | Si tiene movimientos, la persona no se puede borrar. |
| `tipo` | varchar(20) | `entrada` o `salida` |
| `ocurrio_en` | timestamp, indexada | **La hora del movimiento.** Es la que hay que usar para listar y filtrar. |
| `usuario_id` | FK → `users`, nula | Quién lo registró. Ver «lo que falta» abajo. |
| `motivo` | varchar(120), nula | Copia del motivo que traía el invitado **ese día**. |
| `piso` | varchar(10), nula, indexada | Copia del piso al que fue **ese día**. Lo llevan los dos tipos. |
| `tipo_vehiculo` | varchar(10), nula | `carro` o `moto`. |
| `marca` | varchar(40), nula | Copia del vehículo en el que llegó **ese día**. |
| `modelo` | varchar(40), nula | |
| `color` | varchar(30), nula | |
| `placa` | varchar(15), nula, indexada | **Normalizada**: ver «el vehículo». |

Índice compuesto `(persona_id, ocurrio_en)` para resolver «quién está dentro».

### Esta tabla no lleva `updated_at`

Los movimientos **no se editan ni se borran**: un error se corrige con un movimiento nuevo. Tener
un `updated_at` sería mentir, y además invitaría a actualizarlos. El modelo `Movimiento` tiene
`public $timestamps = false;` por eso. Si escribes `$movimiento->created_at` **no existe** — usa
`ocurrio_en`.

### Por qué `motivo` y el vehículo están repetidos en las dos tablas

En `personas` es el dato **actual** (para que al invitado que vuelve no haya que preguntárselo otra
vez). En `movimientos` es una **copia congelada** del día del asiento. Si Carlos vino el lunes a una
videoconferencia y el jueves a entregar material, el asiento del lunes tiene que seguir diciendo
«videoconferencia».

Las cuatro columnas del vehículo siguen exactamente la misma regla, y por el mismo motivo: si el
lunes llegó en su carro y el jueves en otro, cada asiento tiene que decir el de su día.

---

## El piso

Va con el código del edificio: **`2-1`, `2-2`** y así. Significa dos cosas parecidas pero no
iguales, y por eso comparte columna:

| Quién | Qué significa | Se le pregunta |
|---|---|---|
| Trabajador | **Dónde labora.** Es fijo, viene de su ficha. | **No.** La pantalla solo lo muestra, al lado de la gerencia. |
| Invitado | **A dónde se dirige hoy.** | **Siempre**, y es obligatorio. Puede cambiar de una visita a otra, igual que el motivo. |

Es obligatorio para el invitado porque es lo que permite responder «¿quién hay en el 2-1?», que es
media razón de ser de este registro.

En `movimientos` va la copia congelada del piso al que fue **ese día** —para los dos tipos—, con la
misma lógica que el motivo y el vehículo. Está indexada: «¿quién subió al 2-1 hoy?» es una pregunta
de la puerta, y **le sirve a la parte 2**.

### La lista de oficinas vive en `config/edificio.php`

El catálogo de sitios —`LOBBY`, `PB-1`, `2-1`… `8-2`— está ahí y no en la base de datos: es la
lista del edificio, no un dato del sistema. Cuando alguien se muda de oficina, se edita ese
archivo.

Tampoco se saca de las fichas del personal, aunque el código ya conste en ellas: **hay sitios donde
no labora nadie** —el LOBBY, un piso recién desocupado— y aun así se va de visita a ellos.

La **gerencia** de cada oficina sí sale de las fichas, así que no puede contradecirlas. Una oficina
sin nadie asignado se ofrece igual, solo que sin nombre debajo.

En la pantalla se pregunta en **dos pasos** —primero el piso, después la oficina— porque una lista
de treinta códigos delante de alguien que espera de pie no se lee, se busca. Los códigos sin guion
(`LOBBY`, `7`) son un sitio entero: se escogen de un toque, sin segundo paso.

Nada de esto valida: el vigilante puede escribir a mano un código que no esté en la lista. **Son
atajos, no una reja.**

### Se guarda normalizado

Sin espacios y en mayúsculas, con `Persona::normalizarPiso()`. Así `2-1` y `2 - 1` no acaban siendo
dos pisos distintos al buscar. **Si consultas por piso, normaliza antes.** La casilla hace lo mismo
mientras se teclea, para que no se vea una cosa y se guarde otra.

---

## El vehículo

Vive en **dos sitios y con dos formas distintas**, y conviene no confundirlas:

| Dónde | Qué es | Forma |
|---|---|---|
| `vehiculos` | Los que la persona **tiene**. Puede tener varios. | Una fila por vehículo, con `tipo` |
| `movimientos` | En cuál llegó **ese día**. Copia congelada. | Cinco columnas planas, con `tipo_vehiculo` |

**El asiento guarda una copia, no un enlace a `vehiculos`.** Es a propósito: un enlace diría lo que
el vehículo es HOY, y el asiento tiene que decir lo que era el día que se registró, aunque después
se corrija la ficha o se borre el vehículo.

**Es de cualquiera, invitado o trabajador.** El personal también estaciona aquí. (En la primera
versión era solo del invitado; se amplió después.)

**Nadie está obligado a tener uno.** La mayoría de la gente entra caminando, y obligar a inventarse
un vehículo llenaría la base de basura. Quien llega a pie deja el asiento con las cinco columnas en
`NULL`.

### Qué trae hoy

Como una persona puede tener varios, en la puerta se **señala** cuál trae, en vez de teclearlo cada
vez. La pantalla resuelve tres casos:

| Situación | Qué se ve |
|---|---|
| Tiene vehículos | La lista con los suyos, más «Vino a pie» y «Otro…». Sale marcado el de su última entrada. |
| No tiene ninguno | Las casillas para teclear uno. Es el caso del invitado nuevo. |
| Marcó «Otro…» | Las casillas para teclear, y al marcar **se le suma a su ficha** — la próxima vez ya sale en la lista. |

Que se marque uno **no borra los otros**: son suyos igual, y venir a pie un día tampoco se
deshace de ninguno. Lo único que cambia de un día para otro es lo que dice el asiento.

### `carro` o `moto`

`tipo_vehiculo` va aparte de la marca porque en la puerta son dos cosas distintas: no estacionan
en el mismo sitio, y «¿cuántas motos hay dentro?» es una pregunta que se hace. Metido dentro de la
marca («Bera BR-150») habría que conocer la marca para saber qué es.

Dos reglas que conviene tener claras:

1. **Sin vehículo no hay tipo.** En la pantalla el botón «Carro» está *siempre* marcado, así que
   si el tipo contara como dato, nadie podría entrar caminando. Por eso `vacio()` no lo mira y
   `desde()` lo pone en `null` cuando no hay nada más.
2. **Con vehículo y sin elegir, se asume `carro`**, que es lo más común. Cualquier valor que no
   sea `moto` se guarda como `carro`.

### Un vehículo no cambia de clase

**El tipo va pegado a la placa, no al día.** Si una persona ya tiene un vehículo anotado, marcar
la otra clase sobre la misma placa se rechaza: la moto de José es una moto todos los días, y
marcar «carro» encima solo puede ser un error de tecleo — un error que ensuciaría el histórico sin
que nadie se entere.

Lo comprueba `Marcaje::exigirQueElVehiculoNoCambieDeClase()`, en el servidor. La pantalla además
apaga el botón que no toca, pero eso es comodidad: esconder un botón no es seguridad.

Las dos salidas legítimas, y ninguna es una excepción a la regla:

| Situación | Qué hacer |
|---|---|
| Hoy llegó en **otro** vehículo | Poner la placa nueva. Otra placa es otro vehículo, y su clase se elige libre. En la pantalla, el botón **«Otro…»** vacía las casillas de un toque. |
| Hoy llegó **caminando** | Vaciar las casillas. Eso no es cambiarle la clase, es decir que hoy no trajo ninguno. |

La única regla, y la pone el servidor: **si se llena alguna, tiene que estar la placa.** «Toyota
gris» no identifica ningún carro —hay miles— y el día que haya que averiguar quién dejó ese carro
ahí no serviría de nada.

### La placa se guarda normalizada

Igual que la cédula: **solo letras y dígitos, en mayúsculas**, con
`App\Services\DatosVehiculo::normalizarPlaca()`. Así `AB123CD`, `ab-123-cd` y `AB 123 CD` son la
misma placa. **Si escribes una consulta por placa, normaliza antes o no encontrarás nada.**

### Dos clases con nombre parecido, y no son lo mismo

| Clase | Qué es |
|---|---|
| `App\Models\Vehiculo` | Una **fila** de `vehiculos`: el vehículo guardado, con su dueño. |
| `App\Services\DatosVehiculo` | Los **cinco datos sueltos**, limpios y validados. Ni tiene dueño ni está guardado. |

`DatosVehiculo` existe porque esos mismos datos entran por la pantalla, se guardan en `vehiculos` y
se congelan en el asiento: así la regla de cómo se limpian vive en un sitio y no en tres.

| Método de `DatosVehiculo` | Para qué |
|---|---|
| `desde($tipo, $marca, $modelo, $color, $placa)` | Lo construye ya limpio. **El tipo va primero.** Lo vacío queda en `null`. |
| `desdeModelo($fila)` | Los datos de un `Vehiculo` o de un `Movimiento` ya guardado. |
| `normalizarPlaca($placa)` | La placa como se guarda y como hay que buscarla. |
| `normalizarTipo($tipo)` | `carro` o `moto`; cualquier otra cosa, `carro`. |
| `vacio(): bool` | No trajo vehículo. **No mira el tipo.** |
| `esMoto(): bool` | Para contar motos o separarlas en un listado. |
| `exigirValido(): void` | Lanza `ValidationException` si hay datos pero falta la placa. |
| `paraGuardar(): array` | Las columnas **del asiento** (`tipo_vehiculo`). |
| `paraGuardarEnLaTabla(): array` | Las columnas de **`vehiculos`** (`tipo`). |
| `etiquetaTipo(): string` | `Carro` o `Moto`, para mostrar. Vacío si no hay vehículo. |
| `descripcion(): string` | Cómo se lee de un vistazo: `Carro · Toyota Corolla · Gris · AB123CD`. |

En los modelos: `$persona->vehiculos` (los que tiene), `$persona->tieneVehiculos()`,
`$persona->vehiculoConPlaca($placa)` y `$persona->placaDeLaUltimaEntrada()`. En `Vehiculo`:
`descripcion()`, `esMoto()` y `datos()`.

**A la parte 2 le sirve** para su listado, y sobre el MOVIMIENTO, que es lo que ella lista:
`$movimiento->tieneVehiculo()` dice si hay algo que mostrar,
`$movimiento->vehiculo()->descripcion()` lo deja en una línea y `->esMoto()` separa motos de carros.

### El asiento anota lo de ESE día, y nada más

En `Marcaje::registrar()`, el parámetro `$vehiculo` es **en qué llegó hoy**. Nulo y vacío
significan lo mismo —que no trajo ninguno—: el asiento no arrastra nada del día anterior.

Si el vehículo **no está entre los suyos**, se le suma a la ficha. Así, la próxima vez el vigilante
solo lo señala en la lista en vez de teclearlo entero.

El **motivo** sí sigue siendo solo del invitado: un trabajador viene a trabajar, y su asiento no
lo lleva.

---

## Las fotos

**No están en ninguna carpeta pública.** Viven en `storage/app/private/fotos/` (el disco `local`),
y salen únicamente por esta ruta:

```
GET /personas/{persona}/foto      ->  FotoPersonaController
```

Si estuvieran en `storage/app/public` o en `public/`, cualquiera con la URL vería la cara de un
trabajador sin pasar por el sistema, y en un sistema de seguridad eso no se sostiene. Al ir por una
ruta hay **un único portero**, que es donde la parte 3 pone el permiso por rol y el rastro de quién
miró la cara de quién. Hoy no filtra a nadie, igual que el resto de la parte 1.

La respuesta lleva `Cache-Control: private, no-store` para que la cara no se quede en la caché de
un proxy ni del navegador.

En la vista se usa `$persona->tieneFoto()` y no `$persona->foto_ruta`: comprueba además que el
archivo exista de verdad, porque una ficha puede venir del sistema de carnets con la ruta puesta y
sin que la imagen haya llegado. Cuando no hay foto se dibujan las iniciales con
`$persona->iniciales()`, que no pide nada a Internet.

`Persona::rutaFotoSegura()` exige que la ruta empiece por `fotos/` y no contenga `..`. Así, si
alguien lograra escribir otra ruta en la base, no serviría para leer un archivo cualquiera del
servidor. Hay una prueba que lo comprueba con `../.env` y `/etc/passwd`.

---

## No se entra dos veces, ni se sale sin haber entrado

**Quien ya está dentro no puede volver a entrar, y quien no ha entrado no puede salir.** Lo exige
`Marcaje::exigirQueElMovimientoTengaSentido()`, y la pantalla además apaga el botón que no toca
—comodidad, no seguridad: el servidor lo rechaza igual.

Un asiento que no ocurrió se quedaría en el histórico **para siempre**, porque los movimientos no
se borran. Por eso se ataja antes de escribirlo y no después.

> Esto cambió sobre la versión anterior. Antes, dos entradas seguidas separadas por más de la
> ventana del antiduplicado se consideraban **reales** y se guardaban las dos. Ya no: la segunda
> se rechaza. La prueba que decía lo contrario está reescrita, no borrada.

### Y hay que esperar entre dos entradas

Además, **entre dos entradas de la misma persona tienen que pasar
`Marcaje::MINUTOS_ENTRE_ENTRADAS` (10 min)**, haya salido en el medio o no. Es lo que evita que
alguien que entra y sale a cada rato llene el histórico de movimientos.

**Se cuenta desde la ENTRADA anterior, no desde la salida.** Si se contara desde la salida
bastaría con quedarse un minuto adentro para saltarse la regla, y no serviría de nada.

Es el único momento en que **no se puede marcar nada**: la persona está fuera, así que la salida
tampoco aplica. La pantalla apaga los dos botones y dice la hora exacta a partir de la cual se le
puede marcar la entrada — no un «no se puede» a secas.

| Hora | Qué pasa |
|---|---|
| 09:00 | Entrada · ✅ |
| 09:03 | Salida · ✅ (la espera no estorba a la salida) |
| 09:06 | Entrada · ❌ «a partir de las 09:10» |
| 09:10 | Entrada · ✅ |

`Marcaje::puedeEntrarDesde(Persona)` devuelve esa hora, o `null` si puede entrar ya. **A la
parte 2 le sirve** si quiere avisar de lo mismo en su pantalla.

### Y también hay que esperar para salir

Entre la entrada de alguien y su salida tienen que pasar
`Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA` (**5 min**). Nadie entra y se va al minuto: un par de
asientos separados por segundos casi siempre es el carnet leído dos veces o el botón equivocado, y
como los movimientos no se borran, ese asiento se quedaría en el histórico para siempre.

**Son dos plazos distintos y no tienen por qué valer igual.** El de arriba —10 min entre dos
entradas— evita que alguien llene el registro entrando a cada rato; este evita el asiento que no
ocurrió. `Marcaje::puedeSalirDesde(Persona)` devuelve la hora a partir de la cual se le puede
marcar la salida, o `null` si puede salir ya, igual que su hermana.

Si de verdad hubo que sacar a alguien antes de los cinco minutos, se corrige como todo aquí: con un
movimiento nuevo cuando se pueda, nunca editando el anterior.

> Efecto que hay que conocer: a quien baje un momento a la calle y vuelva **no se le podrá
> marcar el regreso** hasta que se cumpla el plazo. Es a propósito, pero conviene tenerlo claro
> antes de que pase en la puerta.

**Si alguien se queda «dentro» de un día para otro** porque olvidó marcar la salida, al día
siguiente le aparecerá el botón de entrada apagado. Se arregla como cualquier otro error en este
sistema —con un movimiento nuevo—: se le marca la salida que faltaba y ya puede entrar. Son dos
toques, y deja rastro de lo que pasó.

### Movimientos repetidos

Antes de esa comprobación hay otra: `registrar()` **no crea un asiento nuevo** si el último de esa
persona es del mismo tipo y ocurrió hace menos de `Marcaje::SEGUNDOS_ANTIDUPLICADO` (10 s):
devuelve el que ya existe. Cubre la doble pulsación del botón y la doble lectura del carnet.

**El orden entre las dos importa y no es casual.** El antiduplicado va primero: una doble
pulsación no es un error del vigilante y no debe sacarle un aviso rojo en pantalla — se resuelve
sola, en silencio. Solo lo que llega fuera de esa ventana se trata como un error de verdad.

Ninguna de las dos estorba a la regla de corregir con un asiento nuevo: marcar una salida después
de una entrada equivocada pasa siempre, porque el tipo es distinto.

---

## La cédula

Son **dos datos, no uno**: el número y la letra.

El **número** se guarda y se busca siempre normalizado a solo dígitos, con
`Persona::normalizarCedula($cedula)`. Así `12345678` y `12.345.678` son el mismo. Si escribes una
consulta por cédula, normaliza antes o no encontrarás nada.

La **letra** —`V` venezolano, `E` extranjero, `J` jurídico— va aparte, en `nacionalidad`, y se
escoge en un desplegable: no se teclea pegada al número. `Persona::normalizarNacionalidad()` la
deja en mayúscula y convierte en `V` cualquier cosa que no reconozca, que es lo que se daba por
sentado cuando no se preguntaba.

**Los dos juntos identifican a la persona.** `V-12345678` y `E-12345678` son dos fichas distintas,
y por eso lo único de la tabla es la pareja `(nacionalidad, cedula)` y no el número solo. Buscar
por número sin la letra devuelve al venezolano: es lo que hace `buscarPorCedula()` cuando no se le
pasa nada, para no romper a quien ya la llamaba con un solo argumento.

Para mostrarla entera se usa `Persona::cedulaCompleta()` — `V-12.345.678` —, que es como está en el
documento que el vigilante tiene en la mano.

Cuántos dígitos puede tener lo dicen `Marcaje::DIGITOS_MINIMOS` (6) y `Marcaje::DIGITOS_MAXIMOS`
(9), **salvo la jurídica**, que llega a `Marcaje::DIGITOS_MAXIMOS_JURIDICO` (10) porque su número es
un RIF. El rango de cada letra sale de `Marcaje::digitosMaximos($nacionalidad)`. **Es la única
definición**: la usa el servidor para validar en `exigirCedulaValida()` y la pantalla para el
`maxlength` del campo, así no se pueden desajustar.

Va por letra y no subiendo el máximo de todas a diez: una cédula `V` de diez dígitos no existe, y
dejarla pasar sería abrir la puerta a un error de tecleo que después nadie atajaría.

El campo de la cédula **solo admite dígitos**: `maxlength` corta por longitud y un `oninput` borra
al instante cualquier cosa que no sea un número, también lo que se pegue. Ojo con no confundirse:
eso es **comodidad para quien teclea, no seguridad** — quien mande una petición sin pasar por la
pantalla se topa igual con `exigirCedulaValida()`. Por eso hay pruebas de las dos cosas por
separado.

**La pantalla busca sola**, sin pulsar Enter: el campo va con `wire:model.live.debounce.400ms` y
`Marcar::updatedCedula()` hace el resto. Dos reglas de ahí que conviene conocer si la parte 2
monta su propia búsqueda:

1. **Fuera del rango de dígitos no se consulta nada.** Al teclear `25375258` se pasa por `253752`,
   que no existe; sin el mínimo, el aviso de invitado saltaría a media cédula. Por arriba se
   comprueba igual, sin fiarse de que el navegador respete el `maxlength`.
2. **Mientras se teclea no se muestran errores de validación.** Una cédula a medias no es un error,
   es una cédula a medias. Los errores solo salen al pulsar Enter (`Marcar::buscar()`), que es la
   forma de decir «ya terminé» — y es también como llega el carnet del lector.

---

## Lo que ya hay hecho y se puede reutilizar

`app/Services/Marcaje.php` — la lógica de la puerta. La pantalla no decide nada, se lo pregunta
todo a este servicio:

| Método | Para qué |
|---|---|
| `buscarPorCedula(string): ?Persona` | El **único** sitio por donde se consulta una cédula. |
| `movimientoSugerido(Persona): string` | `entrada` o `salida`, según dónde esté la persona. |
| `registrar(Persona, string $tipo, ?int $usuarioId, ?string $motivo, ?Vehiculo $vehiculo): Movimiento` | El **único** sitio por donde se escribe un movimiento. |
| `registrarInvitado(string $cedula, string $nombre, string $motivo, ?string $piso, ?DatosVehiculo $vehiculo): Persona` | Da de alta un invitado. El piso es **obligatorio**. |
| `puedeEntrarDesde(Persona): ?CarbonInterface` | Desde qué hora se le puede volver a marcar la entrada, o `null` si ya. |
| `cuantosDentro(): int` | **Le sirve a la parte 2** para su contador de quién está dentro. |
| `cuantosDentroPorTipo(): array` | Lo mismo, separado en `trabajador` e `invitado`. Devuelve siempre las dos claves. |

En el modelo `Persona`: `estaDentro()`, `ultimoMovimiento()`, `esInvitado()`, `esTrabajador()`,
`tieneFoto()`, `rutaFotoSegura()`, `iniciales()` (para el hueco de la foto, sin pedir imágenes a
Internet) y `cedulaConPuntos()`.

---

## Lo que falta, y de quién es

**`usuario_id` está nulo.** El ingreso con usuario y clave es la **parte 3**, y todavía no existe;
la pantalla de marcar ya le pasa `auth()->id()` al servicio, así que en cuanto haya sesión se
empieza a llenar solo. Cuando la parte 3 esté lista hay que:

1. Hacer `usuario_id` obligatorio con una migración nueva.
2. Poner la ruta `/marcar` detrás del ingreso, y que solo la vea el rol vigilante.

**La auditoría es de la parte 3**, pero la parte 1 ya deja los enganches puestos. Son tres sitios,
y cubren la parte 1 completa sin tocar la pantalla:

| Enganche | Qué rastro deja |
|---|---|
| `Marcaje::buscarPorCedula()` | Quién consultó qué cédula. Es el único sitio por donde se consulta. |
| `Marcaje::registrar()` | Quién registró qué movimiento (además del `usuario_id` de la tabla). |
| `FotoPersonaController` | Quién miró la cara de quién. Es el único sitio por donde sale una foto. |

**Las pruebas corren en SQLite en memoria** (`phpunit.xml`), no en PostgreSQL. Para la parte 1
da igual porque no usa SQL propio de Postgres, pero la parte 2 va a usar `ILIKE` en su búsqueda por
nombre, y eso **no existe en SQLite**. Cuando llegue ese momento hay que decidir entre cambiar las
pruebas a una base PostgreSQL de prueba, o usar `whereRaw` con algo que valga en las dos.

> **Ya mordió una vez, y conviene saber cómo.** `cuantosDentroPorTipo()` se escribió con
> `pluck(DB::raw('count(*)'), ...)`. SQLite llama a esa columna `count(*)` y PostgreSQL la llama
> `count`, así que **las pruebas pasaban en verde y la pantalla reventaba** en el servidor de
> verdad. La regla que evita toda esta familia de fallos es corta: **a toda columna calculada,
> alias.** `selectRaw('... count(*) as cuantos')` y luego `pluck('cuantos', ...)`. Y lo que no
> tenga prueba que lo cubra —porque la base de las pruebas no es la de producción— se comprueba a
> mano contra PostgreSQL antes de darlo por hecho.

---

## Datos de desarrollo

`database/seeders/TrabajadoresSeeder.php` crea 11 trabajadores **inventados** con cédulas fáciles
de teclear (`11111111`, `22222222`, … y `12345678`, `87654321`). El de cédula `99999999` está
**desactivado** a propósito, para probar que el sistema no lo deja marcar.

Los nombres y las cédulas son inventados, pero las **gerencias sí son las del CIIP** —Tecnología,
Planificación y Presupuesto, Gestión Humana y Consultoría Jurídica—, porque son las que hay que
ver en pantalla al probar. Están declaradas como constantes en el propio seeder.

**Tres de los once tienen vehículo**, y el resto entra caminando: es la proporción real, el
vehículo tiene que verse como la excepción y no como lo normal.

| Cédula | Quién | Qué tiene |
|---|---|---|
| `22222222` | Luis Hernández | **Carro Y moto** — es el caso con el que se prueba la casilla de «qué trae hoy» |
| `44444444` | José Martínez | Solo moto |
| `12345678` | Daniela Paredes | Solo carro |

> En la base la columna se llama **`dependencia`**; en pantalla se rotula **«Gerencia»**, que es
> como se dice aquí. Renombrar la columna sería un cambio de esquema y hay que hablarlo entre las
> tres partes: por ahora solo cambia el rótulo.

`database/seeders/FotosInventadasSeeder.php` genera con GD una foto de mentira —un color plano con
las iniciales— para **uno de cada dos** trabajadores, así en la pantalla se ven los dos casos: con
foto y con las iniciales de respaldo. Se generan en vez de venir en el repositorio para no
versionar imágenes binarias.

```bash
php artisan db:seed             # añadir los datos de prueba
php artisan migrate:fresh --seed    # BORRA TODO y vuelve a empezar (solo en local)
```

Cuidado con `migrate:fresh`: se lleva por delante los invitados y los movimientos que hayas
registrado probando a mano.

Datos reales de personas no se copian a la máquina de nadie, y las fotos de verdad tampoco.
