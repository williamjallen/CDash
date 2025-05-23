@php
    use App\Http\Controllers\AbstractController;

    $cdash_version = AbstractController::getCDashVersion();
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if(isset($angular) && $angular === true)
        ng-app="CDash"
        ng-controller="HeadController"
        ng-strict-di
        ng-cloak
    @endif
>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta
        name="description"
        content="
            CDash is an open source, web-based software testing server. CDash aggregates, analyzes, and displays the
            results of software testing processes submitted from clients located around the world. CDash is a part of a
            larger software process that integrates Kitware’s CMake, CTest, and CPack tools, as well as other external
            packages used to design, manage and maintain large-scale software systems
        "
    >
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}"/>

    {{-- Framework-specific details --}}
    @if(isset($angular) && $angular === true)
        <link rel="stylesheet" type="text/css" href="{{ asset(mix('assets/css/legacy_3rdparty.css')) }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset(mix(get_css_file())) }} }}"/>
        <link rel="stylesheet" href="{{ asset(mix('assets/css/bootstrap.min.css')) }}"/>
        <script src="{{ asset(mix('assets/js/3rdparty.min.js')) }}"></script>
        <script src="{{ asset(mix('assets/js/legacy_1stparty.min.js')) }}"></script>
    @elseif(isset($vue) && $vue === true)
        <link rel="stylesheet" type="text/css" href="{{ asset(mix(get_css_file())) }}"/>
        @if(isset($daisyui) && $daisyui === true)
            <link rel="stylesheet" type="text/css" href="{{ asset(mix('assets/css/app.css')) }}"/>
        @else
            <link rel="stylesheet" type="text/css" href="{{ asset(mix('assets/css/legacy_3rdparty.css')) }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset(mix('assets/css/vue_common.css')) }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset(mix('assets/css/bootstrap.min.css')) }}"/>
        @endif
        <script src="{{ asset(mix('assets/js/3rdparty.min.js')) }}" type="text/javascript" defer></script>
        <script src="{{ asset(mix('assets/js/app.js')) }}" type="text/javascript" defer></script>
    @else
        <link rel="stylesheet" type="text/css" href="{{ asset(mix('assets/css/legacy_3rdparty.css')) }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset(mix(get_css_file())) }}"/>
        <link rel="stylesheet" href="{{ asset(mix('assets/css/bootstrap.min.css')) }}"/>
        <script src="{{ asset(mix('assets/js/3rdparty.min.js')) }}"></script>
        <script src="{{ asset(mix('assets/js/legacy_1stparty.min.js')) }}"></script>
        @if(str_contains(request()->url(), 'viewCoverage.php')) {{-- This last XSL page needs special treatment... --}}
            <link rel="stylesheet" type="text/css" href="{{ asset(mix('assets/css/jquery.dataTables.css')) }}"/>
            <script src="{{ asset(mix('assets/js/jquery.dataTables.min.js')) }}" defer></script>
        @endif
    @endif

    @yield('header_script')

    <title
        @if(isset($angular) && $angular === true)
            ng-bind="::title"
        @endif
    >@if(isset($title)) {{ $title }} @else CDash @endif</title>
</head>
<body
    @if(isset($angular) && $angular === true)
        ng-controller="{{ $angular_controller }}"
    @endif
>
{{-- This is a horrible hack which allows AngularJS to show the login page when prompted by the API --}}
@if(isset($angular) && $angular === true)
    <div ng-if="cdash.requirelogin == 1" ng-include="'login'"></div>
    <div ng-if="cdash.requirelogin != 1" id="app">
@else
    <div
        id="app"
        data-app-url="{{ url('/') }}"
    >
@endif
        @section('header')
            @include('components.header')
        @show

        <div id="main_content"
            @if(isset($angular) && $angular === true)
                ng-if="!loading"
            @endif
        >

            @if(isset($angular) && $angular === true)
                <div ng-if="cdash.error">@{{cdash.error}}</div>
            @endif

            @hasSection('main_content')
                @yield('main_content')
            @elseif(isset($xsl) && $xsl === true)
                {!! $xsl_content !!}
            @endif
        </div>

        @section('footer')
            @include('components.footer')
        @show
    </div>
</body>
</html>
