<?php

use App\Http\Controllers\Api\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Return a list of Top Posts ordered by their number of Comments.
Route::get('getTopPosts', [PostController::class, 'getTopPosts']);

// Return a list of Top Posts ordered by their number of Comments with filter.
Route::post('searchPost', [PostController::class, 'searchPost']);