<?php

use App\Http\Controllers\Clone\CloneController;
use Illuminate\Support\Facades\Route;

Route::get('/clone', [CloneController::class, 'index'])->name('clone.index');
Route::post('/clone', [CloneController::class, 'store'])->name('clone.store');
