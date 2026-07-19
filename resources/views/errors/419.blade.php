@extends('layouts.app')

@section('title', __('errors.419.title') . ' — ' . __('common.brand'))

@section('content')
    @include('partials.error-page', [
        'icon' => '⏳',
        'heading' => __('errors.419.heading'),
        'body' => __('errors.419.body'),
        'hint' => __('errors.419.hint'),
        'ctaUrl' => route('cart.show'),
        'ctaLabel' => __('errors.419.cta'),
    ])
@endsection
