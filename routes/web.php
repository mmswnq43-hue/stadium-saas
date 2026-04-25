<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Stadium SaaS API is running',
        'version' => '1.0.0',
        'status' => 'healthy'
    ]);
});
