<?php

namespace Tests\Fixtures;

use App\Commands\DatabaseClusterCreate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of database-cluster:create.
 */
class InteractiveDatabaseClusterCreateCommand extends DatabaseClusterCreate
{
    public function __construct()
    {
        $this->signature = str_replace('database-cluster:create', 'test:interactive-database-cluster-create', $this->signature);

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
