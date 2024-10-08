<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\BookController;
use App\Http\Controllers\booking_data;


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

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/home', [booking_data::class, 'index']); 
    Route::get('/list', [booking_data::class, 'list']);
    Route::get('/list/{list_id}', [booking_data::class, 'list_show']);
    
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});


Route::get('/estate', [BookController::class, 'index']);
Route::get('/estate/{id}', [BookController::class, 'getEstate']);

Route::get('admin', function () {
        return Inertia::render('Admin');
    })->name('admin');

Route::get('/', [booking_data::class, 'index']);
Route::get('/booking_data/{booking_id}', [booking_data::class, 'booking_page']);

Route::get('/booking_data-map', [booking_data::class, 'booking_data_map']);

Route::get('/setting_priority', [booking_data::class, 'setting_priority']);

Route::post('/get-report', [booking_data::class, 'get_report']);
Route::get('/get-report', function () {
    return redirect('/booking_data-map');
});

Route::get('/get_all/{id}', [booking_data::class, 'get_all']);
Route::get('/list/share/{token}', [booking_data::class, 'accessSharedList']);