<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los usuarios del sistema: quién puede entrar, con qué nombre de usuario y con qué rol.
 *
 * Aquí no se registra el correo de nadie, en ningún rol: ni del administrador ni del vigilante.
 * A un usuario lo identifican sus datos personales y su nombre de usuario. Por eso se van «email»,
 * «email_verified_at» y la tabla «password_reset_tokens», que Laravel indexa por correo. Tampoco
 * habría cómo usarlos: el servidor donde esto va a correr no tiene salida a Internet, así que no
 * hay correo de recuperación que mandar ni dirección que verificar.
 *
 * La tabla se sigue llamando «users» porque «movimientos.usuario_id» ya apunta ahí (ver
 * docs/esquema.md). Las columnas que pone la parte 3 van en español, como el resto del proyecto;
 * «password» se queda con su nombre porque es el que busca Authenticatable::getAuthPassword().
 */
return new class extends Migration
{
    public function up(): void
    {
        // La tabla se vacía antes de tocarla. Lo único que puede haber en ella es el «Test User»
        // que trae el DatabaseSeeder de Laravel: el ingreso no existía, así que usuarios de verdad
        // no hay. Sin esto, añadir «usuario» —obligatorio y único— fallaría con esa fila puesta.
        DB::table('users')->delete();

        // SQLite no deja soltar una columna que esté en un índice, y las pruebas corren en SQLite.
        // Por eso el índice se va en su propia llamada, antes que la columna.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
        });

        Schema::table('users', function (Blueprint $table) {
            // Con lo que se entra. Único, porque la regla 2 del README es que cada quien entre con
            // el suyo: si varias personas comparten clave, el registro no prueba nada.
            $table->string('usuario', 40)->unique()->after('id');

            // Solo dígitos y normalizada con Persona::normalizarCedula(), igual que en «personas».
            // Nula porque un usuario del sistema puede no tener ficha en la puerta.
            $table->string('cedula', 20)->nullable()->unique()->after('nombre');

            $table->string('rol', 20)->index()->after('cedula');

            // Un usuario que se va no se borra: se desactiva, igual que un trabajador. Si se
            // borrara, el rastro de la auditoría quedaría apuntando al vacío y dejaría de probar
            // nada.
            $table->boolean('activo')->default(true)->after('rol');
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        DB::table('users')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_usuario_unique');
            $table->dropUnique('users_cedula_unique');
            $table->dropIndex('users_rol_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['usuario', 'cedula', 'rol', 'activo']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
