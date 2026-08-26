# Sistema de seguridad — Registro de entradas y salidas

Sistema para el puesto de vigilancia: se teclea una cédula, el sistema dice si esa persona
pertenece al personal o es un invitado, y con un botón se deja constancia de la **entrada** o la
**salida**. Reemplaza la hoja de cálculo que hoy se llena a mano.

Laravel + Livewire + Tailwind. El sistema está **en uso**: las tres partes con las que empezó
—marcar, el registro y los usuarios— funcionan, y encima han ido creciendo el estacionamiento, los
pases de visitante, las alertas y el reconocimiento facial.

---

## Qué hace

Empezó con tres partes, que son las que sostienen todo lo demás.

### Parte 1 · Marcar e invitados

La pantalla que el vigilante tiene abierta todo el turno.

- Un campo grande para la cédula: se teclea o se pasa el carnet por el lector.
- Respuesta inmediata: foto, nombre y dependencia de la persona.
- Dos botones grandes: entrada y salida.
- El sistema propone cuál corresponde. Si la persona ya entró y no ha salido, propone salida.
- Al terminar, la pantalla se limpia sola y queda lista para el siguiente.
- Si la cédula **no aparece** en la lista del personal, es un invitado: aviso claro y un formulario
  de dos campos (nombre y a quién viene a ver). De ahí sigue igual, con los mismos botones.
- Si ese invitado vuelve otro día, con teclear la cédula ya salen sus datos.
- El invitado vive solo en este sistema: no es personal, no lleva carnet, no está en ninguna nómina.

**Está listo cuando** se marca a una persona en menos de cinco segundos sin escribir nada más que
la cédula, y un invitado que ya vino antes se marca solo con la cédula.

### Parte 2 · El registro

Lo que reemplaza a la hoja de cálculo. Se llena solo, con cada marcaje.

- Lista del día: hora, nombre, tipo de persona y si fue entrada o salida.
- Contador de cuántas personas están dentro en este momento.
- Búsqueda por cédula o nombre, con el histórico de esa persona.
- Filtros por fecha y por tipo.
- Exportación a Excel para el reporte por escrito.

**Regla del módulo:** los movimientos **no se editan ni se borran**. Un error se corrige con un
movimiento nuevo. Cada movimiento guarda qué usuario lo registró.

**Está listo cuando** el reporte del día sale con un botón y nadie necesita abrir la hoja de cálculo.

### Parte 3 · Usuarios y roles

Atraviesa a las otras dos: lo que se defina aquí, las demás lo respetan. **Va primero** — si queda
para el final, hay que reescribir las otras dos partes.

- Ingreso con usuario y clave, uno por persona. Nada de un usuario compartido para el puesto.
- Tres roles: vigilante, supervisor y administrador. Cada uno ve lo suyo y nada más.
  - Vigilante: solo la pantalla de marcar. Nunca la lista completa del personal.
  - Supervisor: además, el registro y las correcciones.
  - Administrador: todo, más la gestión de usuarios y la auditoría.
- Pantalla de usuarios: crear, desactivar y reiniciar clave.
- Rastro de todo: quién consultó cuál cédula, quién exportó, quién corrigió un movimiento.

**Está listo cuando** se puede responder, mirando el sistema, quién consultó los datos de una
persona y en qué momento.

---

## Lo que se construyó después

Todo esto vive sobre las tres partes de arriba y ninguna de ellas tuvo que reescribirse.

### Estacionamiento

Qué vehículos hay dentro, en qué plaza y de quién. Un vehículo es una **estadía**: se anota al
entrar y se saca al salir, con **dos personas distintas** guardadas —a quién se le entregó y desde
qué cuenta se dio por entregado—, porque un carro puede entrar con uno y salir con otro.

- Catálogo de plazas y catálogo de **vehículos de la empresa** (la flota).
- El vehículo se anota **en el mismo gesto de marcar a la persona**: no hay un segundo formulario
  donde volver a teclear su cédula, que con cola detrás nadie rellenaba.
- Se puede salir con el vehículo de un compañero o con uno de la empresa. La regla que gobierna
  todo: **solo se saca lo que está dentro**.
- Un vehículo no puede tener dos estadías abiertas. `php artisan estacionamiento:duplicados`
  limpia los que quedaron así antes de esa regla.
- Historial por placa: qué ha hecho ese carro, cuándo, con quién y quién lo dejó pasar.

### Pases de visitante

Las credenciales numeradas que se prestan en la puerta. Es el mismo problema que las plazas —un
objeto numerado que se presta y se devuelve— y se resolvió igual.

- Catálogo con alta por tanda: «V-» del 1 al 20 de una vez.
- Se entrega al marcar la entrada del visitante y vuelve al marcarle la salida. Se puede desmarcar:
  si se va con el pase puesto, **queda constando que sigue fuera** en vez de darse por devuelto.
- La pantalla enseña quién está dentro **sin pase**, para ponerse al día.
- Alerta desde que se entrega; **urgente en cuanto esa persona marca su salida y el pase no vuelve**.

### Alertas

Sobre lo que ya está guardado, sin inventar nada. Permanencias largas, aforo del edificio y del
estacionamiento, vehículos de la empresa fuera y pases sin devolver. Los umbrales se ajustan en
Ajustes; **en 0 se apaga cada aviso**.

### Cotejo con el sistema de carnets

Las dos listas de personal se llevan por separado y se separan solas. Desde **Trabajadores →
Comparar con carnets** (o `php artisan padron:cotejar`) sale qué no cuadra, con su acción:

| Situación | Qué se hace |
|---|---|
| Activo allá, no existe aquí | **Cargar**, con nombre y gerencia del carnets |
| Existe aquí pero desactivado, y allá activo | **Reactivar** (no se recrea: pisaría su ficha) |
| Activo aquí y de baja allá | **Desactivar**, uno a uno o todos |
| Activo aquí y no aparece allá | Solo se dice: puede ser un dato mal cargado |

El carnets es **solo del CIIP**: Marca País y VENAPP quedan fuera del cotejo, y quien no tenga ente
asignado se lista aparte sin juzgarlo.

### Reconocimiento facial

Para quien llega sin el carnet. **Propone quién es y el vigilante confirma con la foto**: nunca
marca solo, y hay una prueba que lo fija.

Todo ocurre en el navegador —los modelos se sirven desde este mismo servidor, la imagen no se envía
a ninguna parte— y al servidor solo llegan 128 números por cara. Eso identifica a una persona igual
que una foto, así que se trata como un dato personal: se borra con ella, quién indexa y quién borra
queda en la auditoría, y el índice se vacía entero desde la pantalla.

- Se indexa del sistema de carnets, y **solo al personal**.
- Cada persona puede tener **varias caras** (la del carnet más las que se tomen con la cámara): la
  del carnet es de hace años, y al comparar se usa la que mejor case.
- Para decir un nombre hacen falta **cuatro condiciones**, no una: estar cerca, estar más cerca que
  el segundo candidato, verse bien y repetirse en dos cuadros. Sin la segunda confunde personas.
- Se ajusta desde la pantalla, porque el punto bueno depende de las fotos que haya y de cuánta
  gente.

---

## Los atajos de la puerta se encienden y se apagan

Teclear la cédula es lo único que **siempre** funciona: no depende de que la persona traiga el
carnet, ni de la cámara, ni de la luz, ni de estar indexada. Por eso abre la pantalla, y lo demás
va debajo como lo que es.

Los tres se encienden y se apagan en **Administración → Ajustes → Qué ofrece la puerta**:

| Atajo | Por omisión |
|---|---|
| Teclear la cédula | **encendido** |
| Escanear el carnet con la cámara | **encendido** |
| Buscar por la cara | **apagado** |

**Los dos primeros no se pueden apagar a la vez**: la puerta se quedaría sin ninguna forma de
marcar a nadie, y eso se descubriría en mitad de un turno. La cara no cuenta para esa regla —se
queda sin servir el día que alguien vacíe el índice—.

Son interruptores y no código comentado: **se cambian sin desplegar nada**. Un puesto sin cámara
decente o una entrada a contraluz convierten un atajo en un botón que estorba encima del campo que
sí sirve, y eso hay que poder quitarlo el mismo día que pasa.

El reconocimiento viene apagado a propósito: es lo único de todo el sistema que puede equivocarse
diciendo el nombre de **otra** persona. Se enciende cuando alguien haya decidido que se fía, no por
venir puesto de fábrica.

---

## Reglas que cumplen las tres partes

Aquí se guarda dónde está cada persona a cada hora, que es más delicado que una nómina.

1. **De a una cédula.** Se consulta una y se muestra lo mínimo: nombre, foto y dependencia. Nunca
   la lista completa, ni teléfono ni dirección.
2. **Cada quien con su usuario.** Si varias personas entran con la misma clave, el registro no
   prueba nada.
3. **Del invitado, lo mínimo.** Nombre y a quién visita. Nada de foto del documento ni dirección.
4. **Todo deja rastro.** Consultar y exportar quedan registrados, para cualquier rol.

Y una regla técnica que no se rompe: **los permisos y las validaciones se revisan en el servidor**.
Esconder un botón en la pantalla no es seguridad.

---

## Requisitos

| Herramienta | Versión |
|---|---|
| PHP | 8.3 o superior, con las extensiones `pdo_pgsql`, `mbstring`, `intl`, `zip`, `gd` |
| Composer | 2.x |
| PostgreSQL | 14 o superior |
| Node.js | 18 o superior (con npm) |

---

## Cómo montarlo

```bash
# 1 · Clonar el repositorio y entrar en la carpeta
git clone <url-del-repositorio>
cd sistema-de-seguridad

# 2 · Dependencias de PHP
composer install

# 3 · Dependencias del front
npm install

# 4 · Archivo de configuración local
cp .env.example .env
php artisan key:generate
```

**5 · Crear la base de datos local** (cada quien la suya, en su propia máquina):

```bash
createdb -h 127.0.0.1 -U <tu_usuario_postgres> registro_accesos
createdb -h 127.0.0.1 -U <tu_usuario_postgres> registro_accesos_pruebas
```

Son **dos**: la de trabajar y la de las pruebas. Las pruebas corren en PostgreSQL igual que el
sistema —antes iban en un SQLite en memoria, y probar una base distinta de la de producción salió
caro—, y se llevan la suya por delante en cada corrida. La de trabajar no se toca nunca.

El servidor, el puerto y las credenciales de la de pruebas salen de tu propio `.env`: solo su
nombre está fijado, en `phpunit.xml`.

**6 · Poner los datos de tu base en el `.env`** — solo estas cuatro líneas:

```env
DB_DATABASE=registro_accesos
DB_USERNAME=<tu_usuario_postgres>
DB_PASSWORD=<tu_clave_postgres>
DB_HOST=127.0.0.1
```

**7 · Crear las tablas y compilar los estilos:**

```bash
php artisan migrate
npm run build
```

**8 · Activar los avisos de git** (una sola vez por máquina):

```bash
git config core.hooksPath .githooks
```

Con eso, después de cada `git pull` o de cambiar de rama, el proyecto te avisa **si alguien
cambió las tablas y a ti te falta correr las migraciones**. Sin ese aviso te encuentras con
errores de columna que no explican nada, y pierdes un rato buscando dónde se rompió algo que no
está roto. Ver «Cuando otra parte cambia las tablas».

**9 · Levantar el sistema** — dos terminales abiertas:

```bash
php artisan serve     # terminal 1 · el servidor, en http://localhost:8000
npm run dev           # terminal 2 · recompila los estilos al guardar
```

Abre <http://localhost:8000>. Si ves la página de inicio **con estilos y con las tres tarjetas**,
el entorno quedó bien montado.

---

## Comandos del día a día

```bash
php artisan serve                  # servidor local
npm run dev                        # front en modo desarrollo (déjalo corriendo)
npm run build                      # compilar los estilos para producción

php artisan make:livewire NombreDelComponente   # nueva pantalla Livewire
php artisan make:model NombreDelModelo -m       # modelo + migración
php artisan migrate                             # aplicar migraciones nuevas
php artisan migrate:fresh                       # borrar todo y volver a crear (solo en local)

php artisan test                   # correr las pruebas
./vendor/bin/pint                  # formatear el código antes de hacer commit

php artisan migraciones:pendientes # ¿me falta correr alguna migración?
```

---

## Cuando otra parte cambia las tablas

Las tres partes tocan las mismas tablas. Quien se baja los cambios de otro y **se olvida de correr
las migraciones** se topa con errores de columna que no dicen nada de lo que pasa de verdad —un
`Unknown column` cualquiera— y pierde un rato buscando dónde se rompió algo que no está roto.

El proyecto avisa por dos caminos, y los dos dicen lo mismo:

| Cuándo | Dónde sale |
|---|---|
| Al hacer `git pull` o cambiar de rama | En la terminal, con la lista de lo que falta |
| Al abrir cualquier pantalla | Una franja roja arriba del todo, **solo en desarrollo** |

En los dos casos la solución es la misma:

```bash
php artisan migrate
```

El aviso de la terminal necesita que hayas activado los hooks (paso 8 de «Cómo montarlo»). El de
la pantalla no necesita nada: viene en el código.

**Qué cambia cada migración y por qué está en [`docs/esquema.md`](docs/esquema.md)**, que es donde
se acuerdan las tablas entre las tres partes. Si cambias una columna, se habla y se anota ahí — no
se mete suelta en una rama.

---

## Cómo está armado

```
app/
  Http/Controllers/     controladores delgados: validan y delegan
  Livewire/             componentes de pantalla (marcar, registro, usuarios)
  Models/               modelos Eloquent
  Services/             la lógica de negocio de cada parte
database/migrations/    estructura de las tablas
resources/views/
  layouts/app.blade.php plantilla base con Tailwind
  inicio.blade.php      página de arranque
routes/web.php          las rutas
```

Todo es **un solo proyecto Laravel**: la pantalla y su lógica viven juntas, no hay una API aparte
ni un proyecto de front separado. Livewire actualiza la pantalla sin recargar la página, pero quien
decide siempre es el servidor.

Lo que hay que acordar entre todos el primer día: **cómo se ve una persona y cómo se ve un
movimiento** — los nombres de las tablas y de sus columnas. Con eso definido, cada parte avanza sin
esperar a las otras.

---

## Ramas

Cada parte trabaja en su propia rama. `main` se mantiene siempre funcionando.

| Rama | Parte |
|---|---|
| `main` | Base común. No se trabaja directo aquí. |
| `parte-1-marcar-invitados` | Parte 1 · Marcar e invitados |
| `parte-2-registro` | Parte 2 · El registro |
| `parte-3-usuarios-roles` | Parte 3 · Usuarios y roles |

```bash
git checkout parte-1-marcar-invitados     # cambiar a la rama de tu parte
git pull                                  # traer lo último antes de empezar
# ... trabajar, hacer commits ...
git push                                  # subir tu avance
```

Cuando una parte esté lista, se integra a `main` con un **pull request** que revisa otra persona.
Para traer a tu rama lo que ya se integró en `main`:

```bash
git checkout main && git pull
git checkout tu-rama && git merge main
```

## Convenciones

- Formatear con `./vendor/bin/pint` antes de cada commit.
- Los controladores no llevan lógica: validan la entrada y llaman a un service.
- Toda validación se repite en el servidor, aunque la pantalla ya haya validado.
- Los movimientos no se editan ni se borran: se corrigen con un asiento nuevo.
- Nada se integra a `main` sin que otra persona lo revise.
- Antes de maquetar una pantalla, mirar la página `/diseno`: los botones, campos y etiquetas ya
  están hechos, para que las tres partes se vean como un solo sistema.

## Qué nunca se sube al repositorio

- El archivo `.env` (ya está en el `.gitignore`). Solo se versiona `.env.example`.
- Claves, contraseñas, certificados o rutas internas de la red.
- Datos reales de personas. Para desarrollo se usan **datos inventados**; la base real no se copia
  a la máquina de nadie.

## El sistema de carnets

Dos sistemas distintos que se hablan. De ahí salen las fotos del personal, la verificación de los
QR y el padrón para el cotejo y el reconocimiento facial.

```bash
CARNETS_URL=https://carnet.ciip.com.ve                   # verificar un QR, y la API del padrón
CARNETS_FOTOS=https://carnet.ciip.com.ve/imgs/usuarios   # una URL, o una carpeta del disco
CARNETS_TOKEN=                                           # el token de su API (X-API-Token)
```

**Las fotos tienen dos vías, y con token gana la API.** El carnets sacó sus fotos de la carpeta
pública —cualquiera con una cédula se descargaba la de esa persona— y ahora las sirve solo por
ruta. Con `CARNETS_TOKEN` puesto se piden a `/api/seguridad/personal/{cedula}/foto`, que es la
única que queda; sin token se sigue usando `CARNETS_FOTOS`, para un carnets que aún no lo haya
movido.

`CARNETS_FOTOS` acepta las dos formas y el sistema distingue una de otra solo: una **carpeta**
cuando los dos están en la misma máquina, o una **URL** cuando el carnets vive en otro servidor. En
los dos casos la petición sale del servidor de seguridad, no del navegador, así que funciona aunque
el puesto vaya por VPN.

Sin `CARNETS_TOKEN` se pierden además el cotejo del padrón y el saber a quién le cambió la foto.

Si las caras dejan de salir en la puerta, `php artisan rostros:diagnostico` dice por cuál de las
dos vías está pidiendo y si llegan.

**Después de cambiar el `.env` en un servidor con la config cacheada**: `php artisan config:clear &&
php artisan config:cache`. Es la causa típica de «puse el token y me sigue dando 401».

---

## Notas de entorno

- El servidor donde esto va a correr **no tiene salida a Internet**. Por eso el proyecto no usa
  fuentes, iconos ni librerías traídas de un CDN: todo lo que se muestre debe venir del propio
  proyecto. Si necesitas una librería, instálala con `npm` o `composer` para que quede compilada
  dentro.
- El montaje del servidor, su puesta en la red y el enlace con el sistema de carnets que ya existe
  **no forman parte de estas tres partes**: se resuelven aparte y no bloquean el desarrollo.
- Si al compilar aparece `Cannot find native binding`, borra `node_modules` y `package-lock.json`
  y vuelve a correr `npm install`.
