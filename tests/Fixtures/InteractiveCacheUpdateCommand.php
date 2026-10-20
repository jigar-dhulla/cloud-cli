<?php

namespace Tests\Fixtures;

use App\Commands\CacheUpdate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of cache:update.
 */
class InteractiveCacheUpdateCommand extends CacheUpdate
{
    public function __construct()
    {
        $this->signature = str_replace('cache:update', 'test:interactive-cache-update', $this->signature);

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
