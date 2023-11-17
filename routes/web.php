<?php

use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HomeTestController;
use App\Http\Controllers\TestController;
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

Route::get('/sync', [SalesOrderController::class, 'index']);
Route::get('/soc/{console}/{waste}', [SalesOrderController::class, 'index']);
Route::get('/', [HomeController::class, 'index']);
Route::get('/hometest', [HomeTestController::class, 'index']);
Route::post('/send', [HomeController::class, 'send']);
Route::post('/sendtest', [HomeTestController::class, 'send']);
Route::get('/test', [TestController::class, 'index']);
