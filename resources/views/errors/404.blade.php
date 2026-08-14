@extends('layouts.app')

@section('title', __('errors.404.title') . ' — ' . __('common.brand'))

{{-- صفحة غير موجودة: تُخدَم بحالة 404 صحيحة (الرمز يضبطه Laravel)، بهوية العلامة وروابط
     تعافٍ للزائر، وnoindex كي لا يفهرس Google صفحات الخطأ. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.error-page', [
        'icon' => '🔎',
        'heading' => __('errors.404.heading'),
        'body' => __('errors.404.body'),
        'hint' => __('errors.404.hint'),
        'ctaUrl' => route('books.index'),
        'ctaLabel' => __('errors.404.cta'),
    ])
@endsection
