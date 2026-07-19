@extends('layouts.app')

@section('title', __('errors.403.title') . ' — ' . __('common.brand'))

@section('content')
    @include('partials.error-page', [
        'icon' => '🔗',
        'heading' => __('errors.403.heading'),
        'body' => __('errors.403.body'),
        'hint' => __('errors.403.hint'),
        'ctaUrl' => route('orders.track.show'),
        'ctaLabel' => __('errors.403.cta'),
    ])
@endsection
