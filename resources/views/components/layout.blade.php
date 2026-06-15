<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>SignageFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ropa+Sans&display=swap" rel="stylesheet">
    <link href="/genericons/genericons.css?ver=3.4.1" rel='stylesheet' type='text/css' media='all' />
    <link rel="stylesheet" type="text/css" href="/style.css">
</head>

<body>
    <header id="header">
        <div id="logo">
            <h1 class="logo" style="position: relative; display: inline-block; margin: 0;">
                <a href="/"><img src="images/banner.png" style="display: block;" /></a>
                <span class="version-badge" style="position: absolute; top: -8px; right: 5px; font-size: 0.9rem; font-weight: bold; color: #fff; background-color: #3b82f6; padding: 2px 8px; border-radius: 9999px; letter-spacing: normal; line-height: 1.2; text-transform: none; font-family: 'Ropa Sans', sans-serif; box-shadow: 0 2px 4px rgba(0,0,0,0.15); z-index: 10;">{{ config('custom.vite_ver') }}</span>
            </h1>
        </div>
        <div id="topNav">
            <nav class="main-nav">
                <ul>
                    <li class="page-collection"><a href="/">About</a></li>
                    <li class="page-collection"><a href="/admin/login">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>
    {{$slot}}
    <div id="social-navigation" class="social-navigation">
        <ul id="menu-social-menu" class="social-links-menu">
            <li><a href="https://www.linkedin.com"></a></li>
            <li><a href="https://www.instagram.com"></a></li>
            <li><a href="https://www.twitter.com"></a></li>
            <li><a href="mailto:xx@xx.x"></a></li>
        </ul>
    </div>
    @stack('scripts')
</body>

</html>