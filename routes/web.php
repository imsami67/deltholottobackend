<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\SaleReportController;
use Illuminate\Support\Facades\Route;

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


Route::get('/', function () {
    return view('index');
});


Route::get('/admin', function () {
    return view('login');
});
Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/saleReport', [SaleReportController::class, 'saleReport']);
});

Route::get('/print', function () {
    return view('print');
});

Route::get('/printOrder/{id}', [OrderController::class, 'printOrder']);
