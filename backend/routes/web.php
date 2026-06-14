<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $spa = public_path('index.html');

    return is_file($spa) ? response()->file($spa) : view('welcome');
})->where('path', '^(?!api(?:/|$)|up$).*$');
