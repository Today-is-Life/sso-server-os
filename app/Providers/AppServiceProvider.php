<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Socialite Providers
        $socialite = $this->app->make(Factory::class);

        // Facebook Provider
        $socialite->extend('facebook', function ($app) use ($socialite) {
            $config = $app['config']['services.facebook'];
            return $socialite->buildProvider(\SocialiteProviders\Facebook\Provider::class, $config);
        });

        // Instagram Provider
        $socialite->extend('instagram', function ($app) use ($socialite) {
            $config = $app['config']['services.instagram'];
            return $socialite->buildProvider(\SocialiteProviders\Instagram\Provider::class, $config);
        });

        // LinkedIn Provider
        $socialite->extend('linkedin', function ($app) use ($socialite) {
            $config = $app['config']['services.linkedin'];
            return $socialite->buildProvider(\SocialiteProviders\LinkedIn\Provider::class, $config);
        });

        // Twitter Provider
        $socialite->extend('twitter', function ($app) use ($socialite) {
            $config = $app['config']['services.twitter'];
            return $socialite->buildProvider(\SocialiteProviders\Twitter\Provider::class, $config);
        });

        // Microsoft Provider
        $socialite->extend('microsoft', function ($app) use ($socialite) {
            $config = $app['config']['services.microsoft'];
            return $socialite->buildProvider(\SocialiteProviders\Microsoft\Provider::class, $config);
        });

        // Apple Provider
        $socialite->extend('apple', function ($app) use ($socialite) {
            $config = $app['config']['services.apple'];
            return $socialite->buildProvider(\SocialiteProviders\Apple\Provider::class, $config);
        });
    }
}
