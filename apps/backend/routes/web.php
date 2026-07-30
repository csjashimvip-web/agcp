<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => response()->json(['service' => 'agcp-api', 'message' => 'Use /api/v1.']));
