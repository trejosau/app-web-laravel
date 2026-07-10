@extends('layouts.app', ['title' => 'Panel'])

@section('content')
    @switch($dashboard ?? 'guest')
        @case('admin')
            <x-dashboard.admin />
            @break

        @case('user')
            <x-dashboard.user />
            @break

        @default
            <x-dashboard.guest />
    @endswitch
@endsection
