<?php namespace Vsb;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;


class TradeMechanicsServiceProvider extends LaravelServiceProvider {
    protected $defer = true;// Delay initializing this service for good performance
    public function provides() {
        return [];
    }
    public function boot() {

    }
    public function register() {
        // Register Locations as service
        // $this->app->bind('vsb\Locations\LocationManager', function ($app) {
        //     return new LocationManager($app);
        // });
        // $this->app->singleton('test.locations', function ($app) {
        //     return new LocationManager($app);
        // });
        $this->mergeConfigFrom(__DIR__.'/../config/trade-mechanics.php', 'trade-mechanics');
    }
}
?>
