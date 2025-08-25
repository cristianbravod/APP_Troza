<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AdminWebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal - redirect a admin o mostrar info de API
Route::get('/', function () {
    return view('welcome', [
        'api_url' => url('/api/v1'),
        'admin_url' => url('/admin'),
        'version' => '1.0.0'
    ]);
});

// ===========================================
// PANEL ADMINISTRATIVO WEB
// ===========================================
Route::prefix('admin')->name('admin.')->group(function () {
    
    // Rutas de autenticación web
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AdminWebController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminWebController::class, 'login'])->name('login.post');
    });
    
    // Rutas protegidas del admin
    Route::middleware(['auth:web', 'admin.access'])->group(function () {
        Route::get('/dashboard', [AdminWebController::class, 'dashboard'])->name('dashboard');
        Route::get('/usuarios', [AdminWebController::class, 'usuarios'])->name('usuarios');
        Route::get('/grupos', [AdminWebController::class, 'grupos'])->name('grupos');
        Route::get('/modulos', [AdminWebController::class, 'modulos'])->name('modulos');
        Route::get('/registros', [AdminWebController::class, 'registros'])->name('registros');
        Route::get('/estadisticas', [AdminWebController::class, 'estadisticas'])->name('estadisticas');
        Route::get('/sincronizacion', [AdminWebController::class, 'sincronizacion'])->name('sincronizacion');
        
        // Logout
        Route::post('/logout', [AdminWebController::class, 'logout'])->name('logout');
    });
});

// Rutas de storage para acceso a archivos subidos
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    
    if (!file_exists($fullPath)) {
        abort(404);
    }
    
    return response()->file($fullPath);
})->where('path', '.*')->name('storage.file');