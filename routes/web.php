<?php

use App\Http\Controllers\TrelloController;
use Illuminate\Support\Facades\Route;

Route::post('/trello', TrelloController::class);
