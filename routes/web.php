<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/todo', function () {
    return view('todo');
});
Route::get('/todo/note', function () {
    return view('note');
});

