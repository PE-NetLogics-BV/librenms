@extends('layouts.librenmsv1')

@section('title', __('Bill') . ': ' . $bill->bill_name)

@section('content')
<div class="container-fluid">
    <h2>
        {{ __('Bill') }}: {{ $bill->bill_name }}
        <small class="pull-right"><a href="{{ route('bills.index') }}"><i class="fa fa-arrow-left fa-lg icon-theme" aria-hidden="true"></i> {{ __('Back to Bills') }}</a></small>
    </h2>

    <x-option-bar name="{{ __('Bill') }}" :options="$options" :selected="$view" />

    @include('bills.show.' . $template)
</div>
@endsection
