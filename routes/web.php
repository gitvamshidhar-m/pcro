<?php

use App\Http\Controllers\CrawlController;
use App\Http\Controllers\CrawlDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analyses/new', [CrawlController::class, 'create'])->name('analyses.create');
    Route::post('/analyses', [CrawlController::class, 'store'])->name('analyses.store');
    Route::get('/analyses/history', [CrawlDashboardController::class, 'history'])->name('analyses.history');
    Route::get('/analyses/{crawl}', [CrawlController::class, 'show'])->name('analyses.show');
    Route::get('/analyses/{crawl}/dashboard', [CrawlDashboardController::class, 'show'])->name('analyses.dashboard');
    Route::get('/analyses/{crawl}/opportunities', [CrawlDashboardController::class, 'opportunities'])->name('analyses.opportunities');
    Route::get('/analyses/{crawl}/recommendations', [CrawlDashboardController::class, 'recommendations'])->name('analyses.recommendations');
    Route::get('/analyses/{crawl}/recommendations/{recommendation}', [CrawlDashboardController::class, 'recommendation'])->name('analyses.recommendation');
    Route::get('/analyses/{crawl}/pages', [CrawlDashboardController::class, 'pages'])->name('analyses.pages');
    Route::get('/analyses/{crawl}/pages/{page}', [CrawlDashboardController::class, 'page'])->name('analyses.page');
    Route::get('/analyses/{crawl}/friction', [CrawlDashboardController::class, 'friction'])->name('analyses.friction');
    Route::get('/analyses/{crawl}/health', [CrawlDashboardController::class, 'health'])->name('analyses.health');
    Route::get('/analyses/{crawl}/categories/{category?}', [CrawlDashboardController::class, 'categories'])->name('analyses.categories');
    Route::get('/analyses/{crawl}/compare/{other}', [CrawlDashboardController::class, 'compare'])->name('analyses.compare');
    Route::get('/analyses/{crawl}/report', [CrawlDashboardController::class, 'report'])->name('analyses.report');
    Route::get('/analyses/{crawl}/progress', [CrawlController::class, 'status'])->name('analyses.status');
    Route::post('/analyses/{crawl}/retry', [CrawlController::class, 'retry'])->name('analyses.retry');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
