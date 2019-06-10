<?php namespace Vsb;


use Illuminate\Queue\QueueManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;


class TradeMechanicsServiceProvider extends LaravelServiceProvider {
    protected $defer = true;// Delay initializing this service for good performance
    public function provides() {
        return [];
    }
    public function boot() {
        $this->registerEvents();
    }
    public function registerEvents(){
        $events = $this->app->make(Dispatcher::class);
        $events->listen($event, $listener);
    }
    public function register() {
        $this->mergeConfigFrom(__DIR__.'/../config/trade-mechanics.php', 'trade-mechanics');
        $this->registerServices();
    }
    public function registerServices(){
        $this->app->singleton('test.locations', function ($app) {
            return new LocationManager($app);
        });
    }

}
?>
