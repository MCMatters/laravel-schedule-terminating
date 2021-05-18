<?php

declare(strict_types=1);

namespace McMatters\LaravelScheduleTerminating\Managers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

use function array_keys, explode, implode, is_callable, ob_end_clean,
    ob_get_contents, ob_start, preg_split, stripos, system, trim;

use const false, PREG_SPLIT_NO_EMPTY, PREG_SPLIT_DELIM_CAPTURE;

/**
 * Class ScheduleManager
 *
 * @package McMatters\LaravelScheduleTerminating\Managers
 */
class ScheduleManager
{
    /**
     * @var \Illuminate\Console\Scheduling\Schedule
     */
    protected $schedule;

    /**
     * ScheduleManager constructor.
     *
     * @param \Illuminate\Contracts\Container\Container $app
     *
     * @throws \RuntimeException
     */
    public function __construct(Container $app)
    {
        $this->checkRequirements();

        $app->make(Kernel::class);

        $this->schedule = $app->make(Schedule::class);
    }

    /**
     * @param array $commands
     * @param int $signal
     *
     * @return void
     */
    public function terminate(array $commands, int $signal = 15)
    {
        $pids = implode(' ', array_keys($commands));

        system("kill -{$signal} {$pids}");
    }

    /**
     * @return array
     */
    public function getSchedulingCommands(): array
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
    public function getRunningCommands(): array
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
    public function getRunningSchedulingCommands(
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
     * @param string $keyword
     * @param array $commands
     *
     * @return array
     */
    public function getFilteredRunningSchedulingCommands(
        string $keyword,
        array $commands
    ): array {
        if ('' === trim($keyword)) {
            return $commands;
        }

        $filtered = [];

        foreach ($commands as $pid => $command) {
            if (false !== stripos($keyword, $command)) {
                $filtered[$pid] = $command;
            }
        }

        return $filtered;
    }

    /**
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function checkRequirements()
    {
        if (!is_callable('system')) {
            throw new RuntimeException('"system" function is disabled');
        }

        try {
            $process = new Process('ps');
            $process->run();

            $ps = $process->isSuccessful();
        } catch (Throwable $e) {
            $ps = false;
        }

        if (!$ps) {
            throw new RuntimeException('"ps" is not reachable');
        }
    }
}
