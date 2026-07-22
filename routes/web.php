<?php

use App\Http\Controllers\PollController;
use App\Http\Controllers\PollManageController;
use App\Http\Controllers\PollOptionController;
use App\Http\Controllers\PublicPollController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Startseite mit Eingabefeld (Zero-Friction-Erstellung, Spec Abschnitt 2)
Route::get('/', fn () => Inertia::render('Landing'))->name('home');

Route::get('/datenschutz', fn () => Inertia::render('Legal/Privacy'))->name('legal.privacy');
Route::get('/impressum', fn () => Inertia::render('Legal/Imprint'))->name('legal.imprint');

Route::post('/polls', [PollController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('poll.store');

// Owner-Bereich – {poll} wird über manage_token aufgelöst
Route::prefix('/p/{poll:manage_token}/edit')->name('poll.')->group(function () {
    Route::get('/', [PollManageController::class, 'show'])->name('show');
    Route::get('/data', [PollManageController::class, 'data'])->name('data');
    Route::patch('/', [PollManageController::class, 'update'])->name('update');
    Route::post('/reset', [PollManageController::class, 'reset'])->name('reset');
    Route::post('/email', [PollManageController::class, 'sendManageLink'])
        ->middleware('throttle:5,1')
        ->name('email');
    Route::post('/options', [PollOptionController::class, 'store'])->name('options.store');
    Route::patch('/options/{option}', [PollOptionController::class, 'update'])->name('options.update');
    Route::delete('/options/{option}', [PollOptionController::class, 'destroy'])->name('options.destroy');
    Route::post('/reorder', [PollOptionController::class, 'reorder'])->name('options.reorder');
    Route::get('/questions', [QuestionController::class, 'indexForOwner'])->name('questions.index');
});

// Public-Bereich – {poll} wird über public_token aufgelöst
Route::prefix('/w/{poll:public_token}')->name('public.')->group(function () {
    Route::get('/', [PublicPollController::class, 'show'])->name('show');
    Route::post('/vote', [VoteController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('vote');
    Route::post('/questions', [QuestionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('questions.store');
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('questions.destroy');
    Route::get('/state', [PublicPollController::class, 'state'])->name('state');
    Route::get('/questions', [QuestionController::class, 'indexForPublic'])->name('questions.index');
});
