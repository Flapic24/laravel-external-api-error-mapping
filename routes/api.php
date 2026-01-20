<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExternalApiDemoController;

Route::get('/external/demo', [ExternalApiDemoController::class, 'demo']);