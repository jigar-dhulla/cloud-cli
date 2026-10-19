<?php

namespace Tests\Fixtures;

use App\Commands\InstanceUpdate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of instance:update.
 */
class InteractiveInstanceUpdateCommand extends InstanceUpdate
{
    public function __construct()
    {
        $this->signature = str_replace('instance:update', 'test:interactive-instance-update', $this->signature);

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
