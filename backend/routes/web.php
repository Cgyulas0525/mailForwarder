<?php

use Illuminate\Support\Facades\Route;

$serveSpa = function () {
    $spa = public_path('index.html');
    if (is_file($spa)) {
        return response()->file($spa);
    }

    return view('welcome');
};

Route::get('/', $serveSpa);
Route::get('/{any}', $serveSpa)->where('any', '^(?!api(?:/|$)|up$).*');
