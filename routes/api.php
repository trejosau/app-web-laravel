<?php

use Illuminate\Support\Facades\Route;

Route::fallback(fn () => response()->json(['message' => 'Not Found'], 404));
