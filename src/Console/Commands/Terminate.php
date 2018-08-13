<?php

declare(strict_types = 1);

namespace McMatters\LaravelScheduleTerminating\Console\Commands;

use Illuminate\Console\Command;
use McMatters\LaravelScheduleTerminating\Managers\ScheduleManager;

/**
 * Class Terminate
 *
 * @package McMatters\LaravelScheduleTerminating\Console\Commands
 */
class Terminate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'schedule:terminate
                           {--F|filter= : Filter names}
                           {--S|signal=15 : Signal to send}';

    /**
     * @var string
     */
    protected $description = 'Terminate scheduling processes';

    /**
     * @var ScheduleManager
     */
    protected $manager;

    /**
     * Terminate constructor.
     *
     * @param ScheduleManager $manager
     */
    public function __construct(ScheduleManager $manager)
    {
        $this->manager = $manager;

        parent::__construct();
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    public function handle()
    {
        $schedulingCommands = $this->manager->getSchedulingCommands();

        if (empty($schedulingCommands)) {
            $this->warn('There are no scheduling commands');

            return;
        }

        $runningCommands = $this->manager->getRunningCommands();

        $this->terminate(
            $this->manager->getRunningSchedulingCommands(
                $schedulingCommands,
                $runningCommands
            )
        );
    }

    /**
     * @param array $commands
     *
     * @return void
     */
    protected function terminate(array $commands)
    {
        $filtered = $this->manager->getFilteredRunningSchedulingCommands(
            $this->option('filter') ?: '',
            $commands
        );

        if (empty($filtered)) {
            if (!empty($commands)) {
                $this->info('There are no running commands with your keyword');
            } else {
                $this->info('There are no running scheduling commands');
            }

            return;
        }

        $this->manager->terminate($filtered, ((int) $this->option('signal')) ?: 15);
    }
}
