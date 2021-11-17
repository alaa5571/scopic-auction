<?php

use Illuminate\Support\Facades\Route;
// use Illuminate\Support\Facades\Hash;
use \App\Events\TestEvent;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


// Route::post('hash', function () {
//     return Hash::make('secret');
// });

Route::get('/{view?}', function () {
    return view('layout');
})->name('spa')->where('view', '^(?!api).*$');

Auth::routes();
