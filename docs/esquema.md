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
| `foto_ruta` | varchar(255), nula | Solo trabajador. Nula mientras no haya enlace con los carnets. |
| `visita` | varchar(120), nula | Solo invitado: a quién viene a ver **la última vez**. |
| `activo` | boolean, por omisión `true` | |
| `created_at`, `updated_at` | timestamps | |

Las columnas que solo aplican a un tipo van **nulas** en el otro. Del invitado se guarda lo mínimo
que manda el README: nombre y a quién visita. Nada de foto del documento, teléfono ni dirección.

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
| `visita` | varchar(120), nula | Copia de a quién visitaba el invitado **ese día**. |

Índice compuesto `(persona_id, ocurrio_en)` para resolver «quién está dentro».

### Esta tabla no lleva `updated_at`

Los movimientos **no se editan ni se borran**: un error se corrige con un movimiento nuevo. Tener
un `updated_at` sería mentir, y además invitaría a actualizarlos. El modelo `Movimiento` tiene
`public $timestamps = false;` por eso. Si escribes `$movimiento->created_at` **no existe** — usa
`ocurrio_en`.

### Por qué `visita` está repetida en las dos tablas

En `personas` es el dato **actual** (para que al invitado que vuelve no haya que preguntárselo otra
vez). En `movimientos` es una **copia congelada** del día del asiento. Si Carlos vino el lunes a ver
a Ana y el jueves a ver a Luis, el asiento del lunes tiene que seguir diciendo «Ana».

---

## La cédula

Se guarda y se busca **siempre normalizada a solo dígitos**, con
`Persona::normalizarCedula($cedula)`. Así `12345678`, `12.345.678` y `V-12.345.678` son la misma
persona. Si escribes una consulta por cédula, normaliza antes o no encontrarás nada.

Se valida en el servidor con `Marcaje::exigirCedulaValida()`: entre 6 y 9 dígitos.

---

## Lo que ya hay hecho y se puede reutilizar

`app/Services/Marcaje.php` — la lógica de la puerta. La pantalla no decide nada, se lo pregunta
todo a este servicio:

| Método | Para qué |
|---|---|
| `buscarPorCedula(string): ?Persona` | El **único** sitio por donde se consulta una cédula. |
| `movimientoSugerido(Persona): string` | `entrada` o `salida`, según dónde esté la persona. |
| `registrar(Persona, string $tipo, ?int $usuarioId, ?string $visita): Movimiento` | El **único** sitio por donde se escribe un movimiento. |
| `registrarInvitado(string, string, string): Persona` | Da de alta un invitado con lo mínimo. |
| `cuantosDentro(): int` | **Le sirve a la parte 2** para su contador de quién está dentro. |

En el modelo `Persona`: `estaDentro()`, `ultimoMovimiento()`, `esInvitado()`, `esTrabajador()`,
`iniciales()` (para el hueco de la foto, sin pedir imágenes a Internet) y `cedulaConPuntos()`.

---

## Lo que falta, y de quién es

**`usuario_id` está nulo.** El ingreso con usuario y clave es la **parte 3**, y todavía no existe;
la pantalla de marcar ya le pasa `auth()->id()` al servicio, así que en cuanto haya sesión se
empieza a llenar solo. Cuando la parte 3 esté lista hay que:

1. Hacer `usuario_id` obligatorio con una migración nueva.
2. Poner la ruta `/marcar` detrás del ingreso, y que solo la vea el rol vigilante.

**La auditoría es de la parte 3**, pero la parte 1 ya deja el enganche puesto: como toda consulta
de cédula pasa por `Marcaje::buscarPorCedula()` y toda escritura por `Marcaje::registrar()`,
basta con registrar el rastro **dentro de esos dos métodos** para cubrir la parte 1 completa. No
hace falta tocar la pantalla.

**Las pruebas corren en SQLite en memoria** (`phpunit.xml`), no en PostgreSQL. Para la parte 1
da igual porque no usa SQL propio de Postgres, pero la parte 2 va a usar `ILIKE` en su búsqueda por
nombre, y eso **no existe en SQLite**. Cuando llegue ese momento hay que decidir entre cambiar las
pruebas a una base PostgreSQL de prueba, o usar `whereRaw` con algo que valga en las dos.

---

## Datos de desarrollo

`database/seeders/TrabajadoresSeeder.php` crea 11 trabajadores **inventados** con cédulas fáciles
de teclear (`11111111`, `22222222`, … y `12345678`, `87654321`). El de cédula `99999999` está
**desactivado** a propósito, para probar que el sistema no lo deja marcar.

```bash
php artisan migrate:fresh --seed    # borrar todo y volver a empezar (solo en local)
```

Datos reales de personas no se copian a la máquina de nadie.
