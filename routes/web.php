<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VmController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\SnapshotController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\Admin\VmController as AdminVmController;
use App\Http\Controllers\Api\VmMetricsController;
use App\Http\Controllers\Api\VmBandwidthController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Admin Routes (Full Control)
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // VM Management (Admin Only)
    Route::get('/vms', [AdminVmController::class, 'index'])->name('vms.index');
    Route::get('/vms/create', [AdminVmController::class, 'create'])->name('vms.create');
    Route::post('/vms', [AdminVmController::class, 'store'])->name('vms.store');
    Route::delete('/vms/{vmid}', [AdminVmController::class, 'destroy'])->name('vms.destroy');
    
    // Migration (Admin Only)
    Route::post('/vms/{vmid}/migrate', [VmController::class, 'migrate'])->name('vms.migrate');
    
    // Firewall (Admin Only)
    Route::get('/vms/{vmid}/firewall', [FirewallController::class, 'index'])->name('firewall.index');
    Route::post('/vms/{vmid}/firewall', [FirewallController::class, 'store'])->name('firewall.store');
    
    // Templates (Admin Only)
    Route::get('/templates', [\App\Http\Controllers\Admin\TemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [\App\Http\Controllers\Admin\TemplateController::class, 'store'])->name('templates.store');
    
    // Backups (Admin Only)
    Route::get('/vms/{vmid}/backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('backups.index');
    Route::post('/vms/{vmid}/backups', [\App\Http\Controllers\Admin\BackupController::class, 'store'])->name('backups.store');
    Route::post('/backups/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restore'])->name('backups.restore');
    Route::delete('/backups', [\App\Http\Controllers\Admin\BackupController::class, 'destroy'])->name('backups.destroy');
    
    // Storage Browser (Admin Only)
    Route::get('/storage', [StorageController::class, 'index'])->name('storage.index');
});

// Client Routes (Limited - Management Only)
Route::middleware(['auth', 'verified'])->prefix('client')->name('client.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // VM Management (Assigned VMs Only)
    Route::post('/vms/{vmid}/power', [VmController::class, 'power'])->name('vms.power');
    Route::post('/vms/{vmid}/reinstall', [VmController::class, 'reinstall'])->name('vms.reinstall');
    Route::post('/vms/{vmid}/rescue', [VmController::class, 'rescue'])->name('vms.rescue');
    Route::get('/vms/{vmid}/console', [VmController::class, 'console'])->name('vms.console');
    Route::get('/vms/{vmid}', [VmController::class, 'show'])->name('vms.show');
    Route::post('/vms/{vmid}/config', [VmController::class, 'update'])->name('vms.update');
    
    // Snapshots & Backups (Client Access)
    Route::get('/vms/{vmid}/snapshots', [SnapshotController::class, 'index'])->name('snapshots.index');
    Route::post('/vms/{vmid}/snapshots', [SnapshotController::class, 'store'])->name('snapshots.store');
    Route::post('/vms/{vmid}/snapshots/{snapname}/rollback', [SnapshotController::class, 'rollback'])->name('snapshots.rollback');
    Route::delete('/vms/{vmid}/snapshots/{snapname}', [SnapshotController::class, 'destroy'])->name('snapshots.destroy');
});

// Legacy routes - redirect based on role
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('client.dashboard');
    })->name('dashboard');
});

// API Routes (Authenticated)
Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    Route::get('/vms/{vmid}/metrics', [VmMetricsController::class, 'show']);
    Route::get('/vms/{vmid}/bandwidth', [VmBandwidthController::class, 'show']);
});

// Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
