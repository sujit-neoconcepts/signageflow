<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        <!-- Global Permissions Object -->
        @auth
        <script>
            window.permissions = {
                @if(auth()->user()->hasRole(env('APP_SUPER_ADMIN', 'super-admin')))
                    all: true,
                @else
                    @php
                        $userPermissions = auth()->user()->getAllPermissions()->pluck('name')->toArray();
                    @endphp
                    @foreach($userPermissions as $permission)
                        "{{ $permission }}": true,
                    @endforeach
                @endif
            };
        </script>
        @else
        <script>
            window.permissions = {};
        </script>
        @endauth
    </body>
</html>
