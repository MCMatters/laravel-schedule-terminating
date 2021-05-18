<?php

declare(strict_types=1);

namespace McMatters\LaravelScheduleTerminating;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use McMatters\LaravelScheduleTerminating\Console\Commands\Terminate;

use const true;

/**
 * Class ServiceProvider
 *
 * @package McMatters\LaravelScheduleTerminating
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = true;

    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.schedule.terminate', Terminate::class);

        $this->commands(['command.schedule.terminate']);
    }

    /**
     * @return array
     */
    public function provides(): array
    {
        return ['command.schedule.terminate'];
    }
}
