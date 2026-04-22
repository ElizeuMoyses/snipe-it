<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ Helper::determineLanguageDirection() }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EULA') - {{ ($snipeSettings) && ($snipeSettings->site_name) ? $snipeSettings->site_name : 'Snipe-IT' }}</title>

    <link rel="shortcut icon" type="image/ico" href="{{ ($snipeSettings) && ($snipeSettings->favicon!='') ? Storage::disk('public')->url(e($snipeSettings->favicon)) : config('app.url').'/favicon.ico' }}">
    <link rel="stylesheet" href="{{ url(mix('css/dist/all.css')) }}">

    <script nonce="{{ csrf_token() }}">
        window.snipeit = {
            settings: {
                "per_page": 50
            }
        };
    </script>

    @if (($snipeSettings) && ($snipeSettings->header_color))
        <style>
        .main-header .navbar, .main-header .logo {
            background-color: {{ $snipeSettings->header_color }};
            border-color: {{ $snipeSettings->header_color }};
        }
        </style>
    @endif

    @if (($snipeSettings) && ($snipeSettings->custom_css))
        <style>
            {!! $snipeSettings->show_custom_css() !!}
        </style>
    @endif

    @stack('css')
</head>

<body class="hold-transition login-page">
    <div class="container" style="padding-top: 30px; padding-bottom: 30px;">
        @if (($snipeSettings) && ($snipeSettings->logo!=''))
            <div class="text-center" style="margin-bottom: 20px;">
                <a href="{{ config('app.url') }}">
                    <img id="login-logo" src="{{ Storage::disk('public')->url('').e($snipeSettings->logo) }}" alt="{{ $snipeSettings->site_name }}" style="max-height: 80px;">
                </a>
            </div>
        @endif

        @yield('content')

        <div class="text-center" style="padding-top: 50px;">
            @if (($snipeSettings) && ($snipeSettings->privacy_policy_link!=''))
                <a target="_blank" rel="noopener" href="{{ $snipeSettings->privacy_policy_link }}">{{ trans('admin/settings/general.privacy_policy') }}</a>
            @endif
        </div>
    </div>

    <script src="{{ url(mix('js/dist/all.js')) }}" nonce="{{ csrf_token() }}"></script>

    @stack('js')
</body>

</html>
