<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MqttController;

Route::get('/', function () {
    return view('Dashboard');
});

// Route untuk mengirim perintah via AJAX
Route::post('/api/mqtt/send', [MqttController::class, 'sendCommand']);
