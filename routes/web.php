<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();


    Route::group([
        'as'     => 'voyager.security.', // Prefijo para los nombres de ruta
        'prefix' => 'security-check',    // URL: /admin/security-check

        // ¡Esta es la magia! Protege el grupo de rutas con el permiso
      //  'middleware' => ['web', 'admin.user', 'can:browse_security_check'],

    ], function () {

        // Ruta GET para mostrar el formulario
        Route::get('/', [\App\Http\Controllers\SecurityController::class, 'index'])
            ->name('index'); // Nombre: voyager.security.index

        // Ruta POST para procesar el formulario
        Route::post('/check', [\App\Http\Controllers\SecurityController::class, 'check'])
            ->name('check'); // Nombre: voyager.security.check
    });
    // --- FIN DE RUTAS PERSONALIZADAS ---
});


/**
 * Rutas para ejecutar comandos Artisan vía HTTP
 *
 * IMPORTANTE: Estas rutas deben estar protegidas en producción
 * Opciones de seguridad:
 * 1. Usar un token secreto en la URL
 * 2. Restringir por IP
 * 3. Deshabilitar después del deploy
 *
 * Agregar estas rutas a routes/web.php
 */


// Token secreto para proteger las rutas
// Genera uno con: php artisan tinker -> Str::random(32)
$deployToken = env('DEPLOY_TOKEN', 'd8UUbndtLqnwFDkcYdpYWJR7hXBtLVwC');

/**
 * Limpiar todas las cachés
 * URL: https://tu-dominio.com/artisan-cache-clear?token=tu-token
 */
Route::get('/artisan-cache-clear', function () use ($deployToken) {
    if (request('token') !== $deployToken) {
        abort(403, 'Unauthorized');
    }

    try {
        // Limpiar cachés
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Cachear para producción
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared and optimized successfully',
            'commands' => [
                'cache:clear',
                'config:clear',
                'route:clear',
                'view:clear',
                'config:cache',
                'route:cache',
                'view:cache',
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

/**
 * Ejecutar migraciones
 * URL: https://tu-dominio.com/artisan-migrate?token=tu-token
 *
 * ⚠️ PELIGRO: Solo usar si estás seguro
 */
Route::get('/artisan-migrate', function () use ($deployToken) {
    if (request('token') !== $deployToken) {
        abort(403, 'Unauthorized');
    }

    try {
        Artisan::call('migrate', ['--force' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Migrations executed successfully',
            'output' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

/**
 * Optimizar aplicación
 * URL: https://tu-dominio.com/artisan-optimize?token=tu-token
 */
Route::get('/artisan-optimize', function () use ($deployToken) {
    if (request('token') !== $deployToken) {
        abort(403, 'Unauthorized');
    }

    try {
        Artisan::call('optimize');

        return response()->json([
            'success' => true,
            'message' => 'Application optimized successfully',
            'output' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});

/**
 * Ver estado de la aplicación
 * URL: https://tu-dominio.com/artisan-status?token=tu-token
 */
Route::get('/artisan-status', function () use ($deployToken) {
    if (request('token') !== $deployToken) {
        abort(403, 'Unauthorized');
    }

    return response()->json([
        'app_name' => config('app.name'),
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
        'app_url' => config('app.url'),
        'laravel_version' => app()->version(),
        'php_version' => phpversion(),
        'database_connection' => config('database.default'),
    ]);
});
