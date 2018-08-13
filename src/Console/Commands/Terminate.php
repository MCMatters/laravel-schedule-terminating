<?php

declare(strict_types = 1);

namespace McMatters\LaravelScheduleTerminating\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use RuntimeException;
use Symfony\Component\Process\Process;
use const false;
use const PREG_SPLIT_NO_EMPTY, PREG_SPLIT_DELIM_CAPTURE;
use function array_keys, explode, implode, is_callable, ob_end_clean,
    ob_get_contents, ob_start, preg_split, stripos, system, trim;

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
     * @var Schedule
     */
    protected $schedule;

    /**
     * Terminate constructor.
     *
     * @param Schedule $schedule
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;

        parent::__construct();
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    public function handle()
    {
        $this->checkRequirements();

        $schedulingCommands = $this->getSchedulingCommands();

        if (empty($schedulingCommands)) {
            $this->warn('There are no scheduling commands');

            return;
        }

        $runningCommands = $this->getRunningCommands();

        $this->terminate(
            $this->getRunningSchedulingCommands(
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
        $filtered = $this->getFilteredRunningSchedulingCommands($commands);

        if (empty($filtered)) {
            if (!empty($commands)) {
                $this->info('There are no running commands with your keyword');
            } else {
                $this->info('There are no running scheduling commands');
            }

            return;
        }

        system("kill -{$this->option('signal')} ".implode(' ', array_keys($filtered)));
    }

    /**
     * @return array
     */
    protected function getSchedulingCommands(): array
    {
        $commands = [];

        foreach ($this->schedule->events() as $event) {
            $commands[] = $event->command;
        }

        return $commands;
    }

    /**
     * @return array
     */
    protected function getRunningCommands(): array
    {
        $commands = [];

        ob_start();
        system('ps ax -o pid,command');
        $result = ob_get_contents();
        ob_end_clean();

        foreach (explode("\n", $result) as $key => $item) {
            $item = trim($item);

            if ('' === $item || $key === 0) {
                continue;
            }

            list($pid, $command) = preg_split(
                '/(\d+)\s+(.+)/',
                $item,
                -1,
                PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
            );

            $commands[$pid] = $command;
        }

        return $commands;
    }

    /**
     * @param array $schedulingCommands
     * @param array $runningCommands
     *
     * @return array
     */
    protected function getRunningSchedulingCommands(
        array $schedulingCommands,
        array $runningCommands
    ): array {
        $commands = [];

        foreach ($schedulingCommands as $schedulingCommand) {
            $schedulingCommandParts = preg_split(
                "/'([^']*)'/",
                $schedulingCommand,
                -1,
                PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
            );

            foreach ($runningCommands as $pid => $runningCommand) {
                foreach ($schedulingCommandParts as $schedulingCommandPart) {
                    if (false === stripos($runningCommand, trim($schedulingCommandPart))) {
                        continue 2;
                    }
                }

                $commands[$pid] = $runningCommand;
            }
        }

        return $commands;
    }

    /**
     * @param array $commands
     *
     * @return array
     */
    protected function getFilteredRunningSchedulingCommands(array $commands): array
    {
        if (!$filter = $this->option('filter')) {
            return $commands;
        }

        $filtered = [];

        foreach ($commands as $pid => $command) {
            if (false !== stripos($filter, $command)) {
                $filtered[$pid] = $command;
            }
        }

        return $filtered;
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    protected function checkRequirements()
    {
        if (!is_callable('system')) {
            throw new RuntimeException('"system" function is disabled');
        }

        $process = new Process('ps');
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('"ps" is not reachable');
        }
    }
}
