<?php

use App\Http\Controllers\LeadCategoryController;
use App\Http\Middleware\ResolveCurrentAccount;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', ResolveCurrentAccount::class])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('categorias', [LeadCategoryController::class, 'index'])->name('categories.index');
    Route::post('categorias', [LeadCategoryController::class, 'store'])->name('categories.store');
    Route::patch('categorias/{category}', [LeadCategoryController::class, 'update'])
        ->whereNumber('category')->name('categories.update');
    Route::delete('categorias/{category}', [LeadCategoryController::class, 'destroy'])
        ->whereNumber('category')->name('categories.destroy');
});

require __DIR__.'/settings.php';
