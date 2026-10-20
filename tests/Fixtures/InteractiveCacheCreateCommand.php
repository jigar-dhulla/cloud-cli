<?php

namespace Tests\Fixtures;

use App\Commands\CacheCreate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of cache:create.
 */
class InteractiveCacheCreateCommand extends CacheCreate
{
    public function __construct()
    {
        $this->signature = str_replace('cache:create', 'test:interactive-cache-create', $this->signature);

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
