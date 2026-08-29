<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('google-ads-conversions::landing');
});
