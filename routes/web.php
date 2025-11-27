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


// Token de seguridad
$deployToken = env('DEPLOY_TOKEN', 'change-me-in-production');

// Middleware para validar token
$validateToken = function (Request $request) use ($deployToken) {
    if ($request->query('token') !== $deployToken) {
        abort(403, 'Unauthorized - Invalid token');
    }
};

// ============================================
// RUTAS DE DEPLOY MEJORADAS
// ============================================
Route::prefix('deploy')->group(function () use ($validateToken) {

    /**
     * 🧪 RUTA DE PRUEBA - Sin token para verificar que funciona
     */
    Route::get('/test-public', function () {
        return response()->json([
            'status' => 'OK',
            'message' => 'Deploy routes are working!',
            'timestamp' => now()->toDateTimeString(),
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
        ]);
    })->name('deploy.test-public');

    /**
     * 📊 Estado del servidor (con mejor diagnóstico)
     */
    Route::get('/status', function (Request $request) use ($validateToken) {
        $validateToken($request);

        $basePath = base_path();
        $composerPath = $basePath . '/composer.phar';

        // Verificar funciones PHP
        $phpFunctions = [
            'exec' => function_exists('exec'),
            'shell_exec' => function_exists('shell_exec'),
            'system' => function_exists('system'),
            'passthru' => function_exists('passthru'),
        ];

        // Verificar permisos
        $permissions = [
            'storage_writable' => is_writable(storage_path()),
            'bootstrap_cache_writable' => is_writable(base_path('bootstrap/cache')),
            'base_writable' => is_writable(base_path()),
        ];

        // Info de composer
        $composerInfo = 'Not found';
        if (file_exists($composerPath)) {
            $composerInfo = 'Local composer.phar';
        } elseif (shell_exec('which composer 2>/dev/null')) {
            $composerInfo = 'Global composer: ' . trim(shell_exec('which composer 2>/dev/null'));
        }

        return response()->json([
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'php_functions' => $phpFunctions,
            'permissions' => $permissions,
            'composer' => $composerInfo,
            'vendor_exists' => is_dir(base_path('vendor')),
            'storage_linked' => is_link(public_path('storage')),
            'disk_space_mb' => round(disk_free_space(base_path()) / 1024 / 1024, 2),
            'disabled_functions' => ini_get('disable_functions'),
            'timestamp' => now()->toDateTimeString(),
        ]);
    })->name('deploy.status');

    /**
     * 🔗 Crear symlink de storage
     */
    Route::get('/storage-link', function (Request $request) use ($validateToken) {
        $validateToken($request);

        try {
            // Método 1: Usar comando artisan
            Artisan::call('storage:link');
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Storage link created successfully',
                'output' => $output,
                'public_storage_exists' => file_exists(public_path('storage')),
                'is_link' => is_link(public_path('storage')),
            ]);

        } catch (\Exception $e) {
            // Método 2: Crear symlink manualmente
            try {
                $target = storage_path('app/public');
                $link = public_path('storage');

                // Eliminar si ya existe
                if (file_exists($link)) {
                    if (is_link($link)) {
                        unlink($link);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'public/storage exists but is not a symlink',
                        ], 500);
                    }
                }

                // Crear symlink
                symlink($target, $link);

                return response()->json([
                    'success' => true,
                    'message' => 'Storage link created manually',
                    'target' => $target,
                    'link' => $link,
                ]);

            } catch (\Exception $e2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create storage link',
                    'error' => $e2->getMessage(),
                ], 500);
            }
        }
    })->name('deploy.storage-link');

    /**
     * 🔧 Composer Install (con MEJOR manejo de errores)
     */
    Route::get('/composer-install', function (Request $request) use ($validateToken) {
        $validateToken($request);

        set_time_limit(600); // 10 minutos
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);

        $errors = [];
        $basePath = base_path();
        $composerPath = $basePath . '/composer.phar';

        try {
            // Verificar que shell_exec esté disponible
            if (!function_exists('shell_exec')) {
                return response()->json([
                    'success' => false,
                    'message' => 'shell_exec function is disabled',
                    'disabled_functions' => ini_get('disable_functions'),
                    'solution' => 'Contact your hosting to enable shell_exec or use deploy-with-vendor.yml workflow',
                ], 500);
            }

            // Verificar composer
            if (file_exists($composerPath)) {
                $composer = "php $composerPath";
                $errors[] = "Using local composer.phar";
            } else {
                // Intentar encontrar composer global
                $composerCheck = shell_exec('which composer 2>&1');
                if (empty($composerCheck)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Composer not found',
                        'solution' => 'Install composer or upload composer.phar to project root',
                        'checked_paths' => [
                            'local' => $composerPath,
                            'global' => 'not found',
                        ],
                    ], 500);
                }
                $composer = 'composer';
                $errors[] = "Using global composer: $composerCheck";
            }

            // Verificar permisos
            if (!is_writable($basePath)) {
                $errors[] = "WARNING: Base path not writable: $basePath";
            }

            if (!is_dir($basePath . '/vendor')) {
                $errors[] = "INFO: vendor/ directory does not exist yet";
            }

            // Construir comando
            $command = "cd $basePath && $composer install --no-dev --optimize-autoloader --no-interaction 2>&1";
            $errors[] = "Executing: $command";

            // Ejecutar
            $startTime = microtime(true);
            $output = shell_exec($command);
            $duration = round(microtime(true) - $startTime, 2);

            $errors[] = "Execution time: {$duration}s";

            // Verificar resultado
            if (empty($output)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No output from composer command',
                    'command' => $command,
                    'duration_seconds' => $duration,
                    'logs' => $errors,
                ], 500);
            }

            // Verificar si hubo errores en el output
            if (stripos($output, 'error') !== false || stripos($output, 'failed') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Composer install completed with errors',
                    'output' => $output,
                    'duration_seconds' => $duration,
                    'logs' => $errors,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Composer install completed successfully',
                'output' => $output,
                'duration_seconds' => $duration,
                'vendor_exists' => is_dir($basePath . '/vendor'),
                'logs' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception during composer install',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'logs' => $errors,
            ], 500);
        }
    })->name('deploy.composer-install');

    /**
     * 🧪 Test Shell Exec
     */
    Route::get('/test-shell', function (Request $request) use ($validateToken) {
        $validateToken($request);

        $results = [];

        // Test 1: shell_exec existe
        $results['shell_exec_exists'] = function_exists('shell_exec');

        if ($results['shell_exec_exists']) {
            // Test 2: Comando simple
            $results['simple_command'] = shell_exec('echo "Hello from shell_exec"');

            // Test 3: Which composer
            $results['which_composer'] = shell_exec('which composer 2>&1');

            // Test 4: PHP version
            $results['php_version_shell'] = shell_exec('php -v 2>&1');

            // Test 5: Composer version
            $results['composer_version'] = shell_exec('composer --version 2>&1');

            // Test 6: PWD
            $results['current_directory'] = shell_exec('pwd 2>&1');

            // Test 7: Permisos
            $results['ls_base'] = shell_exec('ls -la ' . base_path() . ' 2>&1');
        }

        return response()->json([
            'results' => $results,
            'disabled_functions' => ini_get('disable_functions'),
        ]);
    })->name('deploy.test-shell');

    /**
     * 🚀 Pipeline Completo
     */
    Route::get('/full-pipeline', function (Request $request) use ($validateToken) {
        $validateToken($request);

        set_time_limit(600);

        $results = [];

        try {
            // 1. Storage link
            try {
                Artisan::call('storage:link');
                $results['storage_link'] = 'Created';
            } catch (\Exception $e) {
                $results['storage_link'] = 'Failed: ' . $e->getMessage();
            }

            // 2. Composer install (simplificado para pipeline)
            $basePath = base_path();
            $composer = file_exists($basePath . '/composer.phar') ? "php $basePath/composer.phar" : 'composer';
            $results['composer_install'] = shell_exec("cd $basePath && $composer install --no-dev --optimize-autoloader --no-interaction 2>&1");

            // 3. Clear caches
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            $results['cache_clear'] = 'Done';

            // 4. Optimize
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Artisan::call('optimize');
            $results['optimize'] = 'Done';

            return response()->json([
                'success' => true,
                'message' => 'Full pipeline executed',
                'results' => $results,
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
     * 🧹 Clear Cache
     */
    Route::get('/clear-cache', function (Request $request) use ($validateToken) {
        $validateToken($request);

        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'All caches cleared',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('deploy.clear-cache');

    /**
     * ⚡ Optimize
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
                'message' => 'Application optimized',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    })->name('deploy.optimize');

    /**
     * 📝 View Logs
     */
    Route::get('/logs', function (Request $request) use ($validateToken) {
        $validateToken($request);

        $lines = $request->query('lines', 50);
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Log file not found',
                'path' => $logFile,
            ], 404);
        }

        $command = "tail -n $lines $logFile 2>&1";
        $output = shell_exec($command);

        return response()->json([
            'success' => true,
            'log_file' => $logFile,
            'lines' => $lines,
            'logs' => $output,
        ]);
    })->name('deploy.logs');

});

/**
 * 🧪 Ruta de Test Pública (ELIMINAR EN PRODUCCIÓN)
 */
Route::get('/deploy-test', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Deploy routes are working!',
        'php_version' => phpversion(),
        'laravel_version' => app()->version(),
        'available_routes' => [
            '/deploy-test' => 'This test route (no token)',
            '/deploy/test-public' => 'Another test route (no token)',
            '/deploy/status?token=xxx' => 'Server status',
            '/deploy/storage-link?token=xxx' => 'Create storage symlink',
            '/deploy/composer-install?token=xxx' => 'Run composer install',
            '/deploy/test-shell?token=xxx' => 'Test shell_exec',
            '/deploy/full-pipeline?token=xxx' => 'Run full deploy pipeline',
            '/deploy/clear-cache?token=xxx' => 'Clear all caches',
            '/deploy/optimize?token=xxx' => 'Optimize application',
            '/deploy/logs?token=xxx&lines=50' => 'View logs',
        ],
        'timestamp' => now()->toDateTimeString(),
    ]);
});
