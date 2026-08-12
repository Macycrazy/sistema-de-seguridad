# Sistema de seguridad — Registro de entradas y salidas

Sistema para el puesto de vigilancia: se teclea una cédula, el sistema dice si esa persona
pertenece al personal o es un invitado, y con un botón se deja constancia de la **entrada** o la
**salida**. Reemplaza la hoja de cálculo que hoy se llena a mano.

Este repositorio es **la base del proyecto**: Laravel + Livewire + Tailwind ya configurados y
funcionando. Encima de esto se construyen las tres partes descritas abajo.

---

## Qué se va a construir

El trabajo está dividido en tres partes que avanzan en paralelo.

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
```

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

**8 · Levantar el sistema** — dos terminales abiertas:

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
```

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

## Notas de entorno

- El servidor donde esto va a correr **no tiene salida a Internet**. Por eso el proyecto no usa
  fuentes, iconos ni librerías traídas de un CDN: todo lo que se muestre debe venir del propio
  proyecto. Si necesitas una librería, instálala con `npm` o `composer` para que quede compilada
  dentro.
- El montaje del servidor, su puesta en la red y el enlace con el sistema de carnets que ya existe
  **no forman parte de estas tres partes**: se resuelven aparte y no bloquean el desarrollo.
- Si al compilar aparece `Cannot find native binding`, borra `node_modules` y `package-lock.json`
  y vuelve a correr `npm install`.
