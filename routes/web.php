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
    return redirect('/admin') ;
});


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();

    // Override Employees Update Route (Must be AFTER Voyager::routes())
    Route::put('employees/{id}', [\App\Http\Controllers\Voyager\EmployeesController::class, 'update']);


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
    // Reports Module Routes
    Route::group([
        'as'     => 'voyager.reports.',
        'prefix' => 'reports',
        'middleware' => ['web', 'admin.user'],
    ], function () {
        Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
        Route::post('/generate', [\App\Http\Controllers\ReportController::class, 'generate'])->name('generate');
        Route::get('/filters', [\App\Http\Controllers\ReportController::class, 'getFilters'])->name('filters');
        Route::get('/download', [\App\Http\Controllers\ReportController::class, 'download'])->name('download');
    });

    // Document Versioning Routes
    Route::group([
        'as'     => 'voyager.doc-versioning.',
        'prefix' => 'doc-versioning',
        'middleware' => ['web', 'admin.user'],
    ], function () {
        Route::post('/employees/{employee}/versions', [\App\Http\Controllers\DocVersioningController::class, 'createVersion'])->name('create-version');
        Route::post('/versions/{version}/upload', [\App\Http\Controllers\DocVersioningController::class, 'uploadFile'])->name('upload-file');
        Route::post('/files/{file}/approve', [\App\Http\Controllers\DocVersioningController::class, 'toggleApproval'])->name('toggle-approval');
        Route::post('/files/{file}/note', [\App\Http\Controllers\DocVersioningController::class, 'saveNote'])->name('save-note');
    });

    // CSV Import Routes
    Route::group([
        'as'     => 'voyager.csv-import.',
        'prefix' => 'import-csv',
        'middleware' => ['web', 'admin.user'],
    ], function () {
        Route::get('/{slug}/template', [\App\Http\Controllers\CsvImportController::class, 'downloadTemplate'])->name('template');
        Route::post('/{slug}', [\App\Http\Controllers\CsvImportController::class, 'import'])->name('import');
    });

    // --- FIN DE RUTAS PERSONALIZADAS ---
});


/**
 * Rutas de Deploy - Versión MÍNIMA para Diagnosticar
 *
 * COPIAR Y PEGAR al FINAL de routes/web.php
 * (Después de las rutas existentes)
 */

// Ruta de prueba básica
Route::get('/deploy-test-minimal', function () {
    return 'Deploy routes working!';
});

// Ruta de prueba con JSON
Route::get('/deploy-test', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Routes working',
        'php' => phpversion(),
    ]);
});

// Ruta con token
Route::get('/deploy/status', function () {
    $token = request()->query('token');
    $correctToken = env('DEPLOY_TOKEN', 'd8UUbndtLqnwFDkcYdpYWJR7hXBtLVwC');

    if ($token !== $correctToken) {
        return response()->json(['error' => 'Invalid token'], 403);
    }

    return response()->json([
        'php_version' => phpversion(),
        'shell_exec' => function_exists('shell_exec') ? 'enabled' : 'disabled',
        'composer_local' => file_exists(base_path('composer.phar')) ? 'yes' : 'no',
        'vendor_exists' => is_dir(base_path('vendor')) ? 'yes' : 'no',
    ]);
});

// Storage link
Route::get('/deploy/storage-link', function () {
    $token = request()->query('token');
    if ($token !== env('DEPLOY_TOKEN', 'd8UUbndtLqnwFDkcYdpYWJR7hXBtLVwC')) {
        return response()->json(['error' => 'Invalid token'], 403);
    }

    $target = storage_path('app/public');
    $link = public_path('storage');

    if (file_exists($link) && is_link($link)) {
        return response()->json(['message' => 'Link already exists']);
    }

    try {
        symlink($target, $link);
        return response()->json(['success' => true, 'message' => 'Link created']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Composer install
Route::get('/deploy/composer-install', function () {
    $token = request()->query('token');
    if ($token !== env('DEPLOY_TOKEN', 'd8UUbndtLqnwFDkcYdpYWJR7hXBtLVwC')) {
        return response()->json(['error' => 'Invalid token'], 403);
    }

    set_time_limit(600);

    if (!function_exists('shell_exec')) {
        return response()->json([
            'error' => 'shell_exec disabled',
            'disabled_functions' => ini_get('disable_functions')
        ], 500);
    }

    $basePath = base_path();

    // Encontrar composer
    if (file_exists("$basePath/composer.phar")) {
        $cmd = "cd $basePath && php composer.phar install --no-dev --optimize-autoloader 2>&1";
    } else {
        $cmd = "cd $basePath && composer install --no-dev --optimize-autoloader 2>&1";
    }

    $output = shell_exec($cmd);

    return response()->json([
        'command' => $cmd,
        'output' => $output,
        'vendor_exists' => is_dir("$basePath/vendor") ? 'yes' : 'no',
    ]);
});

// Optimize
Route::get('/deploy/optimize', function () {
    $token = request()->query('token');

    if ($token !== env('DEPLOY_TOKEN', 'd8UUbndtLqnwFDkcYdpYWJR7hXBtLVwC')) {
        return response()->json(['error' => 'Invalid token'], 403);
    }

    Artisan::call('config:cache');
    Artisan::call('route:cache');
    Artisan::call('view:cache');

    return response()->json(['message' => 'Optimized']);
});

// Ruta para visualizar el template de correo
Route::get('/test-email', function () {
    return view('emails.generic', [
        'category' => 'Reporte Diario',
        'title' => 'Resumen de Empleados Vencidos',
        'body' => '<p>Estimado/a,</p><p>Esto es una prueba de visualización del cuerpo del correo. Aquí iría el contenido dinámico.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris.</p>',
        'lawFirmName' => 'Firma de Abogados Demo',
        'platformName' => 'Legal Auditex'
    ]);
});

// Cron Job Routes
Route::get('/cron/check-expired-employees', [\App\Http\Controllers\CronJobController::class, 'checkExpiredEmployees']);
