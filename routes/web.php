<?php

use App\Http\Controllers\LeadCategoryController;
use App\Http\Middleware\ResolveCurrentAccount;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', ResolveCurrentAccount::class])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('categorias', [LeadCategoryController::class, 'index'])->name('categories.index');
    Route::post('categorias', [LeadCategoryController::class, 'store'])->name('categories.store');
});

require __DIR__.'/settings.php';
