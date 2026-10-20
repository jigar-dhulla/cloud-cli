<?php

namespace Tests\Fixtures;

use App\Commands\BackgroundProcessUpdate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of background-process:update.
 */
class InteractiveBackgroundProcessUpdateCommand extends BackgroundProcessUpdate
{
    public function __construct()
    {
        $this->signature = str_replace('background-process:update', 'test:interactive-background-process-update', $this->signature);

        parent::__construct();
    }

    protected function isInteractive(): bool
    {
        return true;
    }

    protected function configurePrompts(InputInterface $input): void
    {
        parent::configurePrompts($input);

        Prompt::interactive(true);
    }
}
