<?php

use App\Http\Controllers\ProfileController;
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

use App\Http\Controllers\VmController;

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('/vms', [VmController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('vms.store');

Route::post('/vms/{vmid}/power', [VmController::class, 'power'])
    ->middleware(['auth', 'verified'])
    ->name('vms.power');

Route::post('/vms/{vmid}/migrate', [VmController::class, 'migrate'])
    ->middleware(['auth', 'verified'])
    ->name('vms.migrate');

Route::post('/vms/{vmid}/reinstall', [VmController::class, 'reinstall'])
    ->middleware(['auth', 'verified'])
    ->name('vms.reinstall');

Route::get('/vms/{vmid}/console', [VmController::class, 'console'])
    ->middleware(['auth', 'verified'])
    ->name('vms.console');

Route::get('/vms/{vmid}', [VmController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('vms.show');

Route::post('/vms/{vmid}/config', [VmController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('vms.update');

Route::post('/vms/{vmid}/rescue', [VmController::class, 'rescue'])
    ->middleware(['auth', 'verified'])
    ->name('vms.rescue');

use App\Http\Controllers\FirewallController;

Route::get('/vms/{vmid}/firewall', [FirewallController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('vms.firewall');

Route::post('/vms/{vmid}/firewall', [FirewallController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('vms.firewall.store');

use App\Http\Controllers\SnapshotController;

Route::get('/vms/{vmid}/snapshots', [SnapshotController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('vms.snapshots');

Route::post('/vms/{vmid}/snapshots', [SnapshotController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('vms.snapshots.store');

Route::post('/vms/{vmid}/snapshots/{snapname}/rollback', [SnapshotController::class, 'rollback'])
    ->middleware(['auth', 'verified'])
    ->name('vms.snapshots.rollback');

Route::delete('/vms/{vmid}/snapshots/{snapname}', [SnapshotController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('vms.snapshots.destroy');

use App\Http\Controllers\StorageController;

Route::get('/storage', [StorageController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('storage.index');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
