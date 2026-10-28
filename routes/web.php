<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardGoalController;
use App\Http\Controllers\LeadCategoryController;
use App\Http\Controllers\LeadController;
use App\Http\Middleware\ResolveCurrentAccount;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', ResolveCurrentAccount::class])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::put('dashboard/meta', [DashboardGoalController::class, 'update'])->name('dashboard.goal.update');
    Route::delete('dashboard/meta', [DashboardGoalController::class, 'destroy'])->name('dashboard.goal.destroy');
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('leads/etapas', [LeadController::class, 'categoryOptions'])->name('leads.category-options');
    Route::post('leads/{lead}/complete', [LeadController::class, 'complete'])->whereNumber('lead')->name('leads.complete');
    Route::patch('leads/{lead}/location', [LeadController::class, 'location'])->whereNumber('lead')->name('leads.location');
    Route::patch('leads/{lead}', [LeadController::class, 'update'])->whereNumber('lead')->name('leads.update');
    Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->whereNumber('lead')->name('leads.destroy');
    Route::get('categorias', [LeadCategoryController::class, 'index'])->name('categories.index');
    Route::post('categorias', [LeadCategoryController::class, 'store'])->name('categories.store');
    Route::patch('categorias/{category}', [LeadCategoryController::class, 'update'])
        ->whereNumber('category')->name('categories.update');
    Route::delete('categorias/{category}', [LeadCategoryController::class, 'destroy'])
        ->whereNumber('category')->name('categories.destroy');
});

require __DIR__.'/settings.php';
