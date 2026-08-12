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
| `cedula` | varchar(20), **única** | **Solo dígitos**, sin puntos ni letra. Ver «la cédula» abajo. |
| `tipo` | varchar(20), indexada | `trabajador` o `invitado` |
| `nombre` | varchar(120) | |
| `dependencia` | varchar(120), nula | Solo trabajador. Viene del sistema de carnets. |
| `foto_ruta` | varchar(255), nula | Solo trabajador. Ruta relativa dentro del disco privado: `fotos/12345678.jpg`. Ver «las fotos». |
| `motivo` | varchar(120), nula | Solo invitado: el motivo de la visita **de la última vez**. |
| `activo` | boolean, por omisión `true` | |
| `created_at`, `updated_at` | timestamps | |

Las columnas que solo aplican a un tipo van **nulas** en el otro. Del invitado se guarda lo mínimo:
nombre y **motivo de la visita**. Nada de foto del documento, teléfono ni dirección.

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

Índice compuesto `(persona_id, ocurrio_en)` para resolver «quién está dentro».

### Esta tabla no lleva `updated_at`

Los movimientos **no se editan ni se borran**: un error se corrige con un movimiento nuevo. Tener
un `updated_at` sería mentir, y además invitaría a actualizarlos. El modelo `Movimiento` tiene
`public $timestamps = false;` por eso. Si escribes `$movimiento->created_at` **no existe** — usa
`ocurrio_en`.

### Por qué `motivo` está repetido en las dos tablas

En `personas` es el dato **actual** (para que al invitado que vuelve no haya que preguntárselo otra
vez). En `movimientos` es una **copia congelada** del día del asiento. Si Carlos vino el lunes a una
videoconferencia y el jueves a entregar material, el asiento del lunes tiene que seguir diciendo
«videoconferencia».

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

## Movimientos repetidos

`Marcaje::registrar()` **no crea un asiento nuevo** si el último de esa persona es del mismo tipo y
ocurrió hace menos de `Marcaje::SEGUNDOS_ANTIDUPLICADO` (10 s): devuelve el que ya existe. Cubre la
doble pulsación del botón y la doble lectura del carnet.

Solo mira el último movimiento y solo si es del **mismo tipo**, así que no estorba a la regla de
corregir con un asiento nuevo: marcar una salida después de una entrada equivocada pasa siempre.

Si a la parte 2 le aparecen dos movimientos iguales separados por más de esos 10 segundos, **son
reales** y se corrigen como cualquier otro error: con un movimiento más, nunca editando.

---

## La cédula

Se guarda y se busca **siempre normalizada a solo dígitos**, con
`Persona::normalizarCedula($cedula)`. Así `12345678`, `12.345.678` y `V-12.345.678` son la misma
persona. Si escribes una consulta por cédula, normaliza antes o no encontrarás nada.

Cuántos dígitos puede tener lo dicen `Marcaje::DIGITOS_MINIMOS` (6) y `Marcaje::DIGITOS_MAXIMOS`
(9). **Es la única definición**: la usa el servidor para validar en `exigirCedulaValida()` y la
pantalla para el `maxlength` del campo, así no se pueden desajustar. Si el rango cambia, se cambia
ahí y ya.

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
| `registrar(Persona, string $tipo, ?int $usuarioId, ?string $motivo): Movimiento` | El **único** sitio por donde se escribe un movimiento. |
| `registrarInvitado(string, string, string): Persona` | Da de alta un invitado con lo mínimo. |
| `cuantosDentro(): int` | **Le sirve a la parte 2** para su contador de quién está dentro. |

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

---

## Datos de desarrollo

`database/seeders/TrabajadoresSeeder.php` crea 11 trabajadores **inventados** con cédulas fáciles
de teclear (`11111111`, `22222222`, … y `12345678`, `87654321`). El de cédula `99999999` está
**desactivado** a propósito, para probar que el sistema no lo deja marcar.

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
