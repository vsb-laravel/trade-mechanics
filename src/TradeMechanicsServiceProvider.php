<?php namespace Vsb;

// use Illuminate\Queue\QueueManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class TradeMechanicsServiceProvider extends LaravelServiceProvider {
    // protected $defer = true;// Delay initializing this service for good performance
    // public function provides() {
    //     return [];
    // }
    public function boot() {
        $this->registerEvents();
        $this->registerRoutes();
    }
    public function register() {
        $this->mergeConfigFrom(__DIR__.'/../config/trade-mechanics.php', 'trade-mechanics');
        $this->registerServices();
    }
    protected function registerServices(){
        $this->app->singleton('test.locations', function ($app) {
            return new DealManager($app);
        });
    }
    protected function registerRoutes(){
        Route::group([
            // 'prefix' => 'crm',
            'namespace' => 'Vsb\Http\Controllers',
            // 'middleware' => ['Vsb\Crm\Http\Middleware\UserOnline','Vsb\Crm\Http\Middleware\Google2FA'],
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }
    protected function registerEvents(){
        $events = $this->app->make(Dispatcher::class);
        $events->listen(Vsb\Events\PriceEvent::class, Vsb\Listeners\PriceEventListener::class);
        $events->listen(Vsb\Events\PriceTuneEvent::class, Vsb\Listeners\PriceTuneEventListener::class);
    }

}
?>
