<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/docs', function () {
    return view('docs');
})->name('docs');

Route::get('/kb', function () {
    return redirect()->route('docs');
});
