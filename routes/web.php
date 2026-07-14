<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\BookController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\SearchController;
use Illuminate\Support\Facades\Route;

/*
| مسارات المتجر العامة (Storefront) — «قصص أطفال»
| kebab-case + أسماء واضحة، وربط النماذج عبر الـ slug.
*/

Route::get('/', HomeController::class)->name('home');

// التصفح الكامل + الفلاتر (?cat[]=&pub[]=&age[]=&min=&max=&sale=1&sort=)
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// العروض = تصفّح الكتب مع فلتر الخصم مفعّلًا
Route::redirect('/offers', '/books?sale=1')->name('books.offers');

// البحث (نتائج قابلة للمشاركة). throttle لمنع الإساءة.
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('search');

// الاقتراح الفوري (autocomplete) — JSON خفيف، throttle أشد لكثرة النداءات.
Route::get('/search/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:60,1')
    ->name('search.suggest');

// صفحة الكتاب
Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');

// صفحة القسم (تبقى كل الأقسام الستة موجودة حتى الفارغة)
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
