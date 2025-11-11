<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'ad.group'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    })->name('home');

    Route::get('/dashboard', function () {
        // Redirect to home after auth
        return redirect()->route('home');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/submit-record', [RecordController::class, 'store'])->name('record.store');
    Route::post('/search', [RecordController::class, 'search'])->name('record.search');
});

require __DIR__.'/auth.php';
