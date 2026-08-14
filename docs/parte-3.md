# Parte 3 · Usuarios y roles — el plan

Qué se va a construir, en qué orden y por qué ese orden. Escrito antes de tocar código, para que
las decisiones que afectan a las otras dos partes se acuerden y no aparezcan de sorpresa en un
pull request.

Estado al 13 de agosto de 2026: **los bloques A, B y C están hechos, y sin commit**. Se entra con
usuario y clave, cada rol abre lo suyo, el supervisor da de alta y cambia claves, y el administrador
decide desde una pantalla qué puede hacer cada rol.
Del bloque B queda fuera una sola cosa, `movimientos.usuario_id` obligatorio, porque es decisión de
las tres partes. Falta el bloque D, la auditoría, que es con el que el README da la parte por
terminada.
La rama `parte-3-usuarios-roles` salió del mismo commit que `main`, con las partes 1 y 2 ya
integradas.

---

## El punto de partida

El README dice que esta parte **va primero**, porque atraviesa a las otras dos. No fue así: las
partes 1 y 2 ya están en `main`. No es un drama —dejaron los enganches puestos a propósito— pero
cambia la forma de trabajar: lo que toque a las otras dos partes **se acuerda, no se impone**.

Esto es lo que dejaron esperando:

| Sitio | Qué espera |
|---|---|
| `Marcaje::buscarPorCedula()` | El rastro de quién consultó qué cédula. Es el único sitio por donde se consulta. |
| `Marcaje::registrar()` | El rastro de quién registró qué movimiento. |
| `Marcar::registrar()` | Ya pasa `auth()->id()`: en cuanto haya sesión, `movimientos.usuario_id` se llena solo. |
| `FotoPersonaController` | El permiso por rol y el rastro de quién miró la cara de quién. |
| `movimientos.usuario_id` | Columna creada y nula, esperando ser obligatoria. |
| `routes/web.php` | Tres comentarios marcando qué ruta va detrás del ingreso. |

La parte 2 **no dejó enganches**: su pantalla lee de `FuenteDelRegistro`, cuya única implementación
hoy es `RegistroInventado` (datos de mentira, en memoria). Sus tres acciones delicadas —búsqueda de
personas, histórico de una persona y exportación a Excel— no tienen dónde dejar rastro todavía. Ver
la decisión 4.

---

## El orden: A → B → C → D

**A · Ingreso** primero porque sin sesión no hay nada más: ni rol que revisar ni usuario que anotar
en el rastro. **B · Roles** en seguida, porque una sesión sin permisos no protege nada. **C ·
Pantalla de usuarios** antes que la auditoría porque sin ella el equipo no puede crear usuarios más
que con un seeder, y el sistema no se puede probar de verdad. **D · Auditoría** al final, aunque sea
el criterio con el que el README mide si la parte está lista: sus enganches son baratos una vez que
A y B existen, y ponerlos antes sería anotar rastros sin usuario que anotar.

Cada bloque termina con sus pruebas pasando. Nada se integra a `main` sin que otra persona lo revise.

---

## Bloque A · El ingreso

Entrar con usuario y clave, uno por persona. Nada de un usuario compartido para el puesto.

**Migración** `agregar_rol_y_usuario_a_users`, sobre la tabla `users` que trae Laravel:

| Columna | Tipo | Notas |
|---|---|---|
| `usuario` | varchar(40), **única** | Con lo que se entra. Es el único identificador que hay. |
| `cedula` | varchar(20), única, nula | Solo dígitos, normalizada con `Persona::normalizarCedula()`, igual que en `personas`. Nula porque quien opera el sistema puede no tener ficha en la puerta. |
| `rol` | varchar(20), indexada | `vigilante`, `supervisor` o `administrador`. |
| `activo` | boolean, por omisión `true` | Un usuario que se va **no se borra**: se desactiva, igual que una persona. Si se borrara, el rastro quedaría huérfano y dejaría de probar nada. |

**El correo se elimina.** No se registra el de nadie, en ningún rol: ni el del administrador ni el
del vigilante. A un usuario lo identifican sus datos personales y su nombre de usuario, y nada más.
Se van, entonces:

- La columna `email` y su índice único, y `email_verified_at`.
- La tabla `password_reset_tokens`, que Laravel indexa por correo y que sin correo no sirve.
- En `App\Models\User`, `email` del `#[Fillable]` y el cast de `email_verified_at`.
- `database/factories/UserFactory.php`, que genera correos y un `email_verified_at`.

No es solo que aquí sobre: el servidor no tiene salida a Internet, así que no habría cómo mandar un
correo de recuperación ni cómo verificar una dirección. `Auth::attempt(['usuario' => ...,
'password' => ...])` funciona igual — el correo solo es obligatorio en el scaffolding de los starter
kits, no en el núcleo de Laravel.

Los datos personales del usuario son su nombre y su cédula, y la cédula va nula porque quien opera
el sistema puede no tener ficha en la puerta. No hay clave foránea a `personas`: se reconocen por
la cédula y nada más. Ver las decisiones cerradas al final.

**Código**

- `App\Usuarios\Rol` — el enum con los tres roles, y `alcanza()`, que dice si un rol llega a lo
  que se le pide. Una sola definición, como `Marcaje::DIGITOS_MINIMOS`: la usarán el modelo, el
  middleware, los gates y las pantallas.
- `App\Models\User` — casts (`rol` al enum, `activo` a boolean, `password` a `hashed`), el scope
  `activos()`, los tres `esVigilante()`/`esSupervisor()`/`esAdministrador()`
  y `nombreCorto()` para la barra de arriba. La cédula se normaliza al asignarla, no en quien
  llame, que es donde se olvida.
- `App\Livewire\Ingresar` + `resources/views/livewire/ingresar.blade.php` — la pantalla, con los
  componentes que ya existen (`<x-campo>`, `<x-boton>`, `<x-tarjeta>`).
- `App\Http\Controllers\SalirController` — cierra la sesión. Por POST, no por enlace.
- `App\Http\Middleware\ExigirUsuarioActivo` — colgado del grupo `web` en `bootstrap/app.php`.
- `routes/web.php` — `/ingresar` con `guest`, y todo lo demás dentro de un grupo `auth`.
- `database/seeders/UsuariosSeeder.php` — uno por rol más uno desactivado, para desarrollo. El
  «Test User» que traía Laravel se fue con el correo.

**Nada de starter kit.** Breeze o Fortify traen registro público, verificación por correo y
recuperación de clave: tres cosas que este sistema no debe tener, en pantallas en inglés que habría
que desmontar. El ingreso son treinta líneas: `Auth::attempt()`, `session()->regenerate()` y un
mensaje de error. Se escribe a mano.

**Reglas que no se negocian**

1. Se comprueba `activo` **al entrar y en cada petición**. Desactivar a alguien tiene que echarlo
   del sistema, no esperar a que cierre sesión. (`AuthenticateSession` o una comprobación propia.)
2. `session()->regenerate()` al entrar e `invalidate()` + `regenerateToken()` al salir. Sin eso, un
   identificador de sesión conocido de antes sigue sirviendo.
3. El mensaje de error es siempre el mismo —«Usuario o clave incorrectos»— pase lo que pase. Decir
   «ese usuario no existe» es regalar la mitad del trabajo a quien esté probando.
4. Límite de intentos con `RateLimiter`, por usuario y por IP. El README no lo pide; un sistema que
   guarda dónde está cada persona a cada hora sí.

**Listo.** `/marcar` no se abre sin haber entrado, y `movimientos.usuario_id` se llena solo: la
parte 1 ya le pasaba `auth()->id()` al servicio, y ahora ese id existe.

**Pruebas**: 16 en `tests/Feature/Ingreso/IngresarTest.php` y 4 en `tests/Unit/Usuarios/RolTest.php`.
Entra con la clave buena y no con la mala; el mensaje es el mismo en los dos casos; un desactivado
no entra aunque sepa su clave, y al que desactivan estando dentro se le echa en la siguiente
petición; sin sesión no responde ninguna pantalla; el identificador de sesión cambia al entrar;
salir solo va por POST; al sexto intento hay que esperar.

**Esto tocó las pruebas de las partes 1 y 2**, y no había forma de evitarlo: al quedar todo detrás
del ingreso, las que abrían una pantalla empezaron a recibir un 302. Se resolvió con un
`entrandoComo()` en `tests/TestCase.php` —crea un usuario **sin guardarlo en la base**, para que
sirva también en las pruebas que no montan tablas, como las del registro— y una llamada en el
`setUp()` de cada una. Entra como administrador, que alcanza a todo, para que el bloque B no las
tumbe por algo que no están mirando. Lo que esas pruebas comprueban no se cambió.

---

## Bloque B · Los roles

Tres roles, y cada uno ve lo suyo y nada más.

| | Vigilante | Supervisor | Administrador |
|---|---|---|---|
| `/marcar` y la foto de una persona | sí | sí | sí |
| `/registro` (listado, filtros, histórico) | **no** | sí | sí |
| Exportar a Excel | **no** | sí | sí |
| `/usuarios` | no | **sí** | sí |
| `/roles` (permisos) | no | no | sí |
| `/auditoria` | no | no | sí |

`/usuarios` se aparta del README, que se la daba solo al administrador. Lo decidió el CIIP, y la
razón es de turno: el supervisor es quien está delante cuando a un vigilante se le olvida la clave.
El tope no está en el gate sino en el servicio — ver «nadie toca por encima de su rol», abajo.

**Esta tabla ya no está en el código: es el estado inicial de `permisos_de_rol`**, y el
administrador la cambia desde `/roles`. Ver «Bloque C bis».

El vigilante nunca ve la lista completa del personal: esa es la regla 1 del README, y el registro
de la parte 2 es exactamente esa lista.

**Código**

- `App\Http\Middleware\ExigirRol` — alias `rol` en `bootstrap/app.php`. Se usa `rol:supervisor` y
  se lee «de supervisor para arriba», porque los roles son acumulativos. Un rol mal escrito en una
  ruta revienta con una excepción en vez de dejar pasar a todos: un agujero silencioso es peor que
  una pantalla caída.
- Los cinco gates, juntos en `AppServiceProvider::definirPermisos()`: `ver-registro`,
  `exportar-registro`, `gestionar-usuarios`, `ver-auditoria` y `ver-foto`. Juntos a propósito, para
  que la tabla de arriba se lea de corrido en vez de repartida por los componentes.
- `FotoPersonaController` — `Gate::authorize('ver-foto')`. Hoy deja pasar a los tres roles: el
  vigilante necesita la foto para comprobar que quien tiene delante es quien dice ser. Está puesto
  igual porque es el único portero por donde sale una foto, y es donde el bloque D anotará quién
  miró la cara de quién.
- `resources/views/inicio.blade.php` — al vigilante la tarjeta del registro se le muestra sin
  enlace. Eso es cortesía, no seguridad.

**El permiso se revisa también dentro del componente, no solo en la ruta.** Livewire manda sus
acciones a su propia ruta, no a la de la pantalla, así que el `rol:supervisor` del grupo de rutas
cierra la puerta de entrada y nada más. Se cubre por dos lados:

- `Livewire::addPersistentMiddleware([ExigirRol::class])`, para que ese middleware se vuelva a
  aplicar en las peticiones de Livewire.
- `Gate::authorize('ver-registro')` **en el `boot()`** del componente, no en el `mount()`. Es la
  diferencia que importa: `mount()` corre una sola vez, en la primera carga, y las acciones
  posteriores rehidratan el componente sin volver a montarlo. Un permiso comprobado solo al montar
  le seguiría funcionando a quien le quitaran el rol con la pantalla abierta. `boot()` corre en
  todas. Hay una prueba que baja a un supervisor a vigilante con la pantalla ya abierta y comprueba
  que la siguiente acción se corta.
- `exportar()` lleva además su propio `Gate::authorize('exportar-registro')`.

Es la regla técnica del README: esconder un botón no es seguridad, y quitar una ruta del menú
tampoco.

**`movimientos.usuario_id` obligatorio se quedó fuera, a propósito.** Es lo único del bloque B que
no está hecho. La columna ya se llena sola desde el bloque A, pero sigue admitiendo nulos, y
volverla obligatoria tiene tres consecuencias que hay que resolver antes, no después:

- Los movimientos que ya existen tienen la columna nula. En local se limpia con `migrate:fresh`;
  si alguien tiene datos que le importan, hace falta un usuario «sistema» al que apuntarlos.
- `MarcajeTest` y `MarcarPantallaTest` llaman a `registrar()` sin usuario. Se caen. Hay que
  actualizarlas, y eso es tocar la parte 1.
- `Marcaje::registrar()` tendría que dejar de aceptar `?int $usuarioId = null`.

Por eso es una decisión de las tres partes y no de esta rama. Ver la decisión 1.

**Listo.** Un vigilante que escribe `/registro` a mano en la barra de direcciones se topa con un
403, y no con la lista del personal.

**Pruebas**: 30 en `tests/Feature/Roles/PermisosTest.php`. La matriz de arriba entera, rol por ruta
y rol por permiso —es la tabla del README escrita como prueba—, más las cuatro que importan de
verdad: el vigilante no entra al registro ni hablándole a Livewire directamente; al que le bajan el
rol con la pantalla abierta deja de funcionarle la siguiente acción; exportar pide su propio
permiso; y un rol mal escrito en una ruta revienta en vez de dejar pasar.

---

## Bloque C · La pantalla de usuarios

Crear, desactivar y cambiar la clave de alguien. Solo el administrador.

**Código**

- `App\Services\GestionDeUsuarios` — la lógica: crear, desactivar, reactivar, ponerle una clave a
  alguien y cambiar la propia. La pantalla no decide nada, igual que la de marcar le pregunta todo
  a `Marcaje`.
- `App\Livewire\Usuarios\ListaDeUsuarios` en `/usuarios`, con `rol:supervisor` en la ruta y
  `Gate::authorize('gestionar-usuarios')` en el `boot()`.
- `App\Livewire\CambiarClave` en `/clave`, a la que se entra por el nombre del encabezado.
- `App\Console\Commands\CrearUsuario` — `php artisan usuario:crear`.

**Las claves las teclea el administrador. El sistema no inventa ninguna.** En el alta hay un campo
«Clave», obligatorio; en la lista, el botón «Cambio de clave» abre uno debajo de esa fila. Se la
dicta a su dueño y listo.

Hubo un rato en que el sistema las generaba y las enseñaba en pantalla para poder dictarlas. Se
quitó, y bien quitado: una clave escrita en la pantalla de un puesto de vigilancia la lee cualquiera
que pase por detrás, y ese es justo el sitio donde esto va a correr.

Por eso **la clave no se escribe nunca en la pantalla**, ni al crear ni al cambiarla: la tecleó el
administrador, ya la sabe. Lo único que sale es un aviso de que quedó puesta. Y el mínimo de
caracteres es el mismo la ponga quien la ponga —si dependiera de quién la escribe, la puerta más
floja sería la que valdría—.

**Cambiarla es voluntario, y lo decidió el CIIP.** La clave que pone el administrador es la clave:
quien entre con ella sigue trabajando y el sistema no lo manda a ningún lado. Si quiere una suya, la
cambia cuando le parezca en `/clave`, a la que se entra pulsando su nombre en el encabezado. Ahí se
le pide la clave actual aunque ya tenga sesión abierta: en el puesto la máquina se queda sola cada
dos por tres.

Hubo un rato en que el sistema obligaba a cambiarla en el primer ingreso, con una columna
`debe_cambiar_clave` y un middleware. Se quitó entero —columna incluida— porque no se iba a usar, y
una comprobación que corre en cada petición y nunca dispara es peor que no tenerla.

**Lo que eso cuesta, escrito para que nadie se sorprenda después:** mientras esa persona no cambie
la clave por su cuenta, la saben dos —ella y quien se la puso—. El rastro del bloque D dirá «lo hizo
Ana», y eso valdrá lo que valga esa clave compartida. Es un apartamiento consciente de la regla 2
del README («si varias personas entran con la misma clave, el registro no prueba nada»), a cambio de
que nadie se quede trancado en la puerta a mitad de un turno. Si algún día hay que responder ante
una auditoría por un movimiento concreto, esta es la línea que hay que releer.

**Nadie toca ni asciende a quien esté por encima de su propio rol.** Es la regla que sostiene todo
lo anterior, y vive en `GestionDeUsuarios`, no en la pantalla. Sin ella, abrirle la gestión al
supervisor sería regalarle el sistema: se crea un administrador, o le pone otra clave a uno que ya
exista, y entra con él. Ponerle la clave a alguien **es** poder entrar como esa persona, así que ahí
es donde más importa.

En la práctica: un supervisor gestiona vigilantes y supervisores, y a los administradores ni los ve
—en su fila dice «fuera de tu alcance»—; el selector de rol no le ofrece «Administrador»; y un
administrador sí gestiona a otros administradores. Lo de la pantalla es cortesía: quien mande la
acción por Livewire sin pasar por los botones se topa con el servicio igual, y hay pruebas de eso.

Desde la consola no hay rol que respetar (`quienLoHace` va nulo): quien puede correr artisan en el
servidor ya tiene el servidor, y es la única forma de crear el primer administrador.

**Cambiar el rol de alguien** se hace desde la misma lista. El README no pide esta pantalla, pero sin
ella un ascenso se arregla entrando a la base a mano, y eso lo acaba haciendo cualquiera de
cualquier manera. Lleva sus topes: nadie se cambia el rol a sí mismo —es la escalada más corta que
hay— y al último administrador activo no se le baja, igual que no se le desactiva.

**Desactivar, nunca borrar.** No hay botón de borrar y no es un olvido: un usuario borrado dejaría
el rastro de la auditoría apuntando al vacío. Se desactiva, y se puede volver a activar.

**Quién crea el primer administrador.** Un comando de consola,
`php artisan usuario:crear`, porque en un servidor recién montado no hay ningún administrador y la
pantalla solo la abre un administrador. Un seeder no servía: los seeders son de desarrollo y traen
datos inventados. La clave se teclea ahí también —con `secret`, para que no quede escrita en la
terminal ni en el historial— o se pasa con `--clave`.

**Listo.** Un supervisor da de alta a un vigilante y le dicta la clave, ese vigilante entra con ella
y marca a alguien, sin ningún paso intermedio. Recorrido hecho a mano en el navegador, no solo en
las pruebas.

**Pruebas**: 34 entre `tests/Feature/Usuarios/` y las filas de `PermisosTest`. Además de lo obvio
—no se repite un nombre de usuario, un rol inventado no pasa aunque llegue por Livewire, nadie se
desactiva a sí mismo, desactivar no borra, sin clave no se da de alta a nadie, la clave no aparece
nunca en la pantalla y en la base solo queda su hash— está entero el alcance del supervisor en
`AlcanceDelSupervisorTest`: no se crea un administrador, no le pone la clave a uno, no lo desactiva,
no asciende a nadie a administrador, pero sí gestiona vigilantes y supervisores. Y el cambio de
clave propio tiene su límite de intentos, como el ingreso.

**Un hallazgo que cambió una regla del plan.** La regla «el último administrador activo no se puede
desactivar» **no se alcanza desde la pantalla**: quien la usa es siempre un administrador activo, así
que si desactiva a otro quedan dos, y si se desactiva a sí mismo lo corta antes la regla de no
desactivarse a uno mismo. Se dejó igual —en el servicio, que usa también el comando de consola— pero
está probada llamando al servicio directamente, no fingiendo que la pantalla llega ahí. Lo que sí se
quitó del plan es «ni quitarse el rol»: el README no pide editar roles, y la pantalla no lo hace.

---

## Bloque D · El rastro

Lo que el README usa para dar la parte por terminada: poder responder, mirando el sistema, quién
consultó los datos de una persona y en qué momento.

**Tabla `auditorias`** — un asiento por cosa que pasó. Como `movimientos`, **no se edita ni se
borra**, así que tampoco lleva `updated_at`:

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `usuario_id` | FK → `users`, nula | Nula solo en el ingreso fallido: ahí todavía no hay usuario. |
| `accion` | varchar(40), indexada | De la lista de abajo. |
| `persona_id` | FK → `personas`, nula | A quién le cayó, cuando aplica. |
| `detalle` | varchar(255), nula | La cédula consultada, el filtro exportado, el usuario creado. |
| `ip` | varchar(45), nula | |
| `ocurrio_en` | timestamp, indexada | Como en `movimientos`: la hora del hecho, no la de la fila. |

Índice compuesto `(persona_id, ocurrio_en)`, que es como se hace la pregunta de verdad: «quién
consultó a esta persona y cuándo».

**Un solo sitio para escribir**: `App\Services\Rastro::deja(string $accion, ...)`. Igual que
`Marcaje` es el único sitio por donde se escribe un movimiento.

**Las acciones**: `ingreso.correcto`, `ingreso.fallido`, `salida`, `consulta.cedula`,
`movimiento.registrado`, `foto.vista`, `registro.busqueda`, `registro.historico`,
`registro.exportado`, `usuario.creado`, `usuario.desactivado`, `usuario.clave-reiniciada`.

**Pantalla `/auditoria`**, solo administrador, con filtros por usuario, por acción y por fecha.

### El problema del ruido, que hay que resolver antes de escribir esto

`Marcaje::buscarPorCedula()` **no se llama una vez por consulta**. El campo de la cédula va con
`wire:model.live.debounce.400ms`: buscar `25375258` dispara una consulta por cada pausa al teclear.
Anotarlas todas llenaría la tabla de basura y enterraría las consultas de verdad justo cuando
alguien las necesite.

Tres salidas, y hay que elegir una (decisión 2):

1. Anotar solo la consulta que **encuentra** a alguien. Simple, pero se pierde el rastro de quién
   anduvo probando cédulas que no existen —que es justo lo que uno querría ver.
2. Anotar todas, y agrupar por usuario y cédula dentro de una ventana de tiempo, como ya hace
   `Marcaje::SEGUNDOS_ANTIDUPLICADO` con los movimientos repetidos. Más código, mejor rastro.
3. Anotar solo cuando la cédula está completa según `DIGITOS_MINIMOS`. Es la más barata y deja fuera
   los tecleos a medias, pero sigue anotando de más.

**Está listo cuando** se pregunta por una cédula en `/auditoria` y sale quién la consultó, quién vio
su foto y quién la exportó, con hora y usuario.

**Pruebas**: cada enganche deja su asiento; el ingreso fallido queda anotado sin usuario; la
aplicación no ofrece ninguna forma de borrar ni editar un asiento.

---

## El doble `<header>`: hubo que arreglarlo

`resources/views/layouts/app.blade.php` tenía **dos `<header>`**, y el primero no cerraba ninguna
de sus etiquetas: ni el `<header>`, ni su `<div>`, ni el `<a>`, ni el `<span>`. Salió del merge
`25d47f0`, que juntó los encabezados hechos en paralelo por las partes 1 y 2; en vez de quedar uno,
quedaron los dos pegados.

La idea era reportarlo y no tocarlo desde esta rama. **No se pudo**: como el `<a>` no cerraba, el
sistema entero quedaba dentro de un enlace a la página de inicio. Todo encimado y sin poder pulsar
nada — no era un defecto cosmético, era el sistema inservible. Se quitó el primer encabezado y se
dejó el azul (`bg-marca` + `logo-ciip-blanco.png`), que es el que dan por bueno los commits y el
propio `EncabezadoTest`. `public/img/logo-ciip.jpg` queda sin usar.

`EncabezadoTest` no lo cazaba porque comprobaba que ciertos textos y rutas **estuvieran presentes**,
nunca que el marcado cerrara: un `<header>` de más pasaba todas sus comprobaciones. Ahora cuenta
aperturas y cierres de las etiquetas de bloque en cada pantalla, incluida la de ingreso.

**Hay que avisarlo igual**, porque toca las partes 1 y 2: si alguien está trabajando ese encabezado
en su rama, esto se le va a pisar en el merge.

---

## Bloque C bis · Roles y permisos

Los permisos dejaron de estar escritos en el código. Ahora viven en la tabla `permisos_de_rol` y se
cambian desde `/roles`, solo el administrador. Lo que se marque ahí vale desde que se guarda, sin
que nadie tenga que volver a entrar.

**La distinción que sostiene todo esto**, y que conviene entender antes de tocar la pantalla:

- Un **permiso** dice **a qué pantallas llega** un rol. Es configurable.
- El **orden de los roles** dice **a quién puede tocar** cada quien —un supervisor no le pone la
  clave a un administrador—. **No es configurable**: vive en `Rol::alcanza()` y en
  `GestionDeUsuarios`, en código.

Si el orden fuera editable desde una pantalla, quien entrara a esa pantalla se ascendería solo y no
habría permiso que valiera. Por eso darle «gestionar usuarios» al vigilante le deja crear
vigilantes, no administradores — y hay una prueba que lo comprueba exactamente así.

**Código**

- `App\Usuarios\Permiso` — el enum, con etiqueta, explicación y a quién le toca de fábrica.
- Migración `permisos_de_rol` — una fila por concesión, y siembra los valores por omisión. No es un
  seeder de desarrollo: sin esas filas el sistema arranca sin que nadie pueda hacer nada.
- `App\Services\Permisos` — singleton, lee una vez por petición. Los gates preguntan aquí en cada
  comprobación, y sin memoria sería una consulta por cada botón de cada pantalla.
- `AppServiceProvider` — los gates ya no se escriben a mano: se define uno por cada caso del enum y
  la respuesta sale de la tabla.
- `App\Livewire\Roles\PermisosPorRol` en `/roles`, con `can:gestionar-permisos`.

**Las rutas pasaron de pedir un rol a pedir un permiso** (`can:ver-registro` en vez de
`rol:supervisor`). Si no, mover un permiso en la pantalla no movería la puerta, y la pantalla sería
un adorno. Con eso `ExigirRol` se quedó sin uso y se retiró; `can:` es además de los middleware que
Livewire vuelve a aplicar en sus acciones.

**«Gestionar permisos» está clavado**: se lo queda el administrador y no lo tiene nadie más, venga
lo que venga en la petición. Quitárselo cerraría esa pantalla para siempre —el arreglo sería entrar
a la base a mano— y dárselo a otro rol le dejaría concederse todo lo demás en dos clics. En la
pantalla ni se dibuja la casilla, y el servicio lo reimpone aunque llegue por Livewire.

Hay también un **«Devolver a como venía»**, que reescribe la tabla con los valores del enum.

**Los tres roles siguen siendo fijos.** No se crean ni se borran desde ninguna pantalla: los define
el README, y el orden entre ellos es lo que no se toca.

**Pruebas**: 12 en `tests/Feature/Roles/GestionDePermisosTest.php`, más las filas nuevas de
`PermisosTest`. Quitar un permiso cierra la pantalla de verdad y darlo la abre —comprobado entrando
después con un usuario de ese rol, no mirando la tabla—; a «gestionar permisos» no se le puede
quitar ni dar; un permiso inventado que llegue por Livewire no entra en la base; y los permisos no
mueven el orden de los roles.

Esto obligó a añadir `RefreshDatabase` a `ExampleTest` y a `RegistroDelDiaTest`, que no montaban
tablas: ahora el gate que abre esas pantallas consulta la base.

---

## Dos ajustes de entorno que hacían falta

**La sesión ahora dura 60 minutos y muere al cerrar el navegador** (`SESSION_LIFETIME=60`,
`SESSION_EXPIRE_ON_CLOSE=true`, en el `.env.example` para que le llegue al equipo). Estaba en 120
minutos y sobrevivía al cierre: en una máquina donde se turnan personas, eso es la regla 2 del README
descosiéndose sola —el vigilante del turno siguiente se encuentra la sesión del anterior abierta—.

**`UsuariosSeeder` ya no corre fuera de `local`.** Siembra cuatro usuarios con una clave escrita a la
vista en el propio archivo, que está en el repositorio; un `php artisan db:seed` tecleado por
costumbre en el servidor dejaba cuatro puertas abiertas, una de administrador. Ahora avisa y no hace
nada. En el servidor, el primer usuario sale de `php artisan usuario:crear`.

---

## Lo que la parte 3 no hace

- El montaje del servidor, su puesta en la red y el enlace con el sistema de carnets.
- Pasar la parte 2 de `RegistroInventado` a la base de datos.

---

## Decisiones abiertas

Ya cerradas, y las cuatro están construidas:

- Se entra con **nombre de usuario**, y **no se registra correo en ningún rol**, del administrador
  al vigilante.
- **Los datos personales de un usuario son su nombre y su cédula**, y la cédula es **opcional**.
  **No hay clave foránea a `personas`**: una `Persona` es quien pasa por la puerta y un `User` es
  quien teclea, y alguien puede ser las dos cosas sin que una obligue a la otra. Se reconocen por
  la cédula, que se guarda normalizada en las dos tablas. Esto lo decidí yo al escribir el bloque A
  para no bloquear el trabajo; **si al equipo no le sirve, se cambia con una migración**.
- **El primer administrador sale de `php artisan usuario:crear`.**

Lo que queda abierto. La primera hay que cerrarla con las otras dos partes.

1. **`movimientos.usuario_id` obligatorio: ¿cuándo, y qué se hace con los que ya hay?** Rompe dos
   pruebas de la parte 1 y cambia la firma de `Marcaje::registrar()`. Es de las tres partes.
2. **El ruido de `consulta.cedula`**: cuál de las tres salidas de arriba.
3. **Cuánto tiempo se guarda el rastro.** Una tabla que crece con cada tecleo necesita una respuesta,
   aunque sea «todo, y ya veremos». Quien la responda debería ser quien responda ante una auditoría.
4. **La parte 2 sigue con datos inventados.** Anotar «quién consultó a esta persona» contra una
   fuente de mentira no prueba nada. O la parte 2 pasa a la base de datos antes que el bloque D, o
   el rastro del registro se pone y se queda esperando. Hay que hablarlo con quien lleve la parte 2.

---

## Antes de empezar

```bash
php artisan migrate:fresh --seed    # base limpia (BORRA TODO: solo en local)
php artisan test                    # las 129 pruebas que ya hay pasan: que sigan pasando
./vendor/bin/pint                   # formatear, antes de cada commit
```

Y `php --version` tiene que decir **8.4**: el `composer.lock` está fijado a Symfony 8, que exige
PHP >= 8.4.1. El README dice «8.3 o superior» y con 8.3 el `composer install` no pasa.
