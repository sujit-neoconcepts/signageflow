<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => ['name' => $request->user()->name ?? '', 'id' => $request->user()->id ?? null],
            ],/*
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy())->toArray(), [
                    'location' => $request->url(),
                ]);
            },*/
            'flash' => [
                'financial_year' => fn() => $request->session()->get('financial_year'),
                'message' => fn() => $request->session()->get('message'),
                'msg_type' => fn() => $request->session()->get('msg_type')
            ],
            /*
            'can' => function () use ($request) {
                $perm = $request->user() ? $request->user()->getAllPermissions()->toArray() : [];
                $allperm = $request->user() ? ($request->user()->hasRole(env('APP_SUPER_ADMIN', 'super-admin')) ? ['all' => true] : []) : [];
                for ($i = 0; $i < count($perm); $i++) {
                    $allperm[$perm[$i]['name']] = true;
                }
                return (object)$allperm;
            },*/
        ]);
    }
}
