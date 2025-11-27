<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use TCG\Voyager\Facades\Voyager;


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
 * Middleware para validar el token
 */
$validateToken = function (Request $request) use ($deployToken) {
    if ($request->query('token') !== $deployToken) {
        abort(403, 'Unauthorized - Invalid token');
    }
};


// ============================================
// GRUPO DE RUTAS DE DEPLOY
// ============================================
Route::prefix('deploy')->group(function () use ($validateToken) {

    /**
     * Ejecutar: composer install --no-dev --optimize-autoloader
     *
     * URL: https://tu-dominio.com/deploy/composer-install?token=tu-token
     *
     * Esto instala las dependencias directamente en el servidor
     */
    Route::get('/composer-install', function (Request $request) use ($validateToken) {
        $validateToken($request);

        set_time_limit(300); // 5 minutos timeout

        try {
            $basePath = base_path();
            $composerPath = $basePath . '/composer.phar';

            // Verificar si composer.phar existe, si no, usar composer global
            $composer = file_exists($composerPath) ? "php $composerPath" : 'composer';

            // Comando a ejecutar
            $command = "cd $basePath && $composer install --no-dev --optimize-autoloader --no-interaction 2>&1";

            // Ejecutar comando
            $output = shell_exec($command);

            return response()->json([
                'success' => true,
                'message' => 'Composer install executed successfully',
                'command' => $command,
                'output' => $output,
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Composer install failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('deploy.composer-install');

    /**
     * Ejecutar: composer update (con precaución)
     *
     * URL: https://tu-dominio.com/deploy/composer-update?token=tu-token
     */
    Route::get('/composer-update', function (Request $request) use ($validateToken) {
        $validateToken($request);

        set_time_limit(300);

        try {
            $basePath = base_path();
            $composerPath = $basePath . '/composer.phar';
            $composer = file_exists($composerPath) ? "php $composerPath" : 'composer';

            $command = "cd $basePath && $composer update --no-dev --optimize-autoloader --no-interaction 2>&1";
            $output = shell_exec($command);

            return response()->json([
                'success' => true,
                'message' => 'Composer update executed successfully',
                'output' => $output,
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('deploy.composer-update');

    /**
     * Ejecutar: composer dump-autoload
     *
     * URL: https://tu-dominio.com/deploy/composer-dump-autoload?token=tu-token
     */
    Route::get('/composer-dump-autoload', function (Request $request) use ($validateToken) {
        $validateToken($request);

        try {
            $basePath = base_path();
            $composerPath = $basePath . '/composer.phar';
            $composer = file_exists($composerPath) ? "php $composerPath" : 'composer';

            $command = "cd $basePath && $composer dump-autoload --optimize --no-dev 2>&1";
            $output = shell_exec($command);

            return response()->json([
                'success' => true,
                'message' => 'Composer dump-autoload executed',
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('deploy.dump-autoload');

    /**
     * Pipeline completo de deploy
     *
     * URL: https://tu-dominio.com/deploy/full-pipeline?token=tu-token
     *
     * Ejecuta en orden:
     * 1. composer install
     * 2. php artisan migrate --force
     * 3. php artisan config:cache
     * 4. php artisan route:cache
     * 5. php artisan view:cache
     */
    Route::get('/full-pipeline', function (Request $request) use ($validateToken) {
        $validateToken($request);

        set_time_limit(600); // 10 minutos

        $results = [];

        try {
            $basePath = base_path();
            $composerPath = $basePath . '/composer.phar';
            $composer = file_exists($composerPath) ? "php $composerPath" : 'composer';

            // 1. Composer install
            $results['composer_install'] = [
                'command' => "$composer install --no-dev --optimize-autoloader",
                'output' => shell_exec("cd $basePath && $composer install --no-dev --optimize-autoloader --no-interaction 2>&1")
            ];

            // 2. Migraciones (opcional - comenta si no quieres)
            // $results['migrations'] = [
            //     'command' => 'php artisan migrate --force',
            //     'output' => Artisan::call('migrate', ['--force' => true])
            // ];

            // 3. Limpiar cachés
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            $results['cache_clear'] = 'All caches cleared';

            // 4. Cachear para producción
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            $results['cache_optimize'] = 'All caches optimized';

            // 5. Optimizar autoloader
            Artisan::call('optimize');

            return response()->json([
                'success' => true,
                'message' => 'Full deploy pipeline executed successfully',
                'results' => $results,
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pipeline failed',
                'error' => $e->getMessage(),
                'results' => $results,
            ], 500);
        }
    })->name('deploy.full-pipeline');

    /**
     * Verificar estado del servidor
     *
     * URL: https://tu-dominio.com/deploy/status?token=tu-token
     */
    Route::get('/status', function (Request $request) use ($validateToken) {
        $validateToken($request);

        $basePath = base_path();
        $composerPath = $basePath . '/composer.phar';

        return response()->json([
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'composer_exists' => file_exists($composerPath) ? 'Local (composer.phar)' : 'Global',
            'vendor_exists' => is_dir(base_path('vendor')),
            'storage_writable' => is_writable(storage_path()),
            'exec_enabled' => function_exists('shell_exec'),
            'disk_space' => disk_free_space(base_path()) / 1024 / 1024 . ' MB',
            'timestamp' => now()->toDateTimeString(),
        ]);
    })->name('deploy.status');

    /**
     * Limpiar todas las cachés
     *
     * URL: https://tu-dominio.com/deploy/clear-cache?token=tu-token
     */
    Route::get('/clear-cache', function (Request $request) use ($validateToken) {
        $validateToken($request);

        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('event:clear');

            return response()->json([
                'success' => true,
                'message' => 'All caches cleared successfully',
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('deploy.clear-cache');

    /**
     * Optimizar aplicación
     *
     * URL: https://tu-dominio.com/deploy/optimize?token=tu-token
     */
    Route::get('/optimize', function (Request $request) use ($validateToken) {
        $validateToken($request);

        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Artisan::call('optimize');

            return response()->json([
                'success' => true,
                'message' => 'Application optimized successfully',
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('deploy.optimize');

    /**
     * Ver logs recientes
     *
     * URL: https://tu-dominio.com/deploy/logs?token=tu-token&lines=50
     */
    Route::get('/logs', function (Request $request) use ($validateToken) {
        $validateToken($request);

        $lines = $request->query('lines', 50);
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Log file not found',
            ], 404);
        }

        // Leer últimas N líneas del log
        $command = "tail -n $lines $logFile";
        $output = shell_exec($command);

        return response()->json([
            'success' => true,
            'lines' => $lines,
            'logs' => $output,
            'log_file' => $logFile,
        ]);
    })->name('deploy.logs');

});

/**
 * Ruta de prueba (sin token) - Eliminar en producción
 *
 * URL: https://tu-dominio.com/deploy-test
 */
Route::get('/deploy-test', function () {
    return response()->json([
        'message' => 'Deploy routes are working!',
        'instructions' => 'Use /deploy/* routes with ?token=your-token',
        'available_routes' => [
            '/deploy/composer-install?token=xxx',
            '/deploy/composer-update?token=xxx',
            '/deploy/composer-dump-autoload?token=xxx',
            '/deploy/full-pipeline?token=xxx',
            '/deploy/status?token=xxx',
            '/deploy/clear-cache?token=xxx',
            '/deploy/optimize?token=xxx',
            '/deploy/logs?token=xxx&lines=50',
        ],
    ]);
});
