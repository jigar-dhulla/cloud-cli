<?php

namespace App\Commands;

use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ApplicationAvatarDelete extends BaseCommand
{
    protected $signature = 'application:delete-avatar
                            {application? : The application ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Remove the avatar from an application';

    protected $aliases = ['app:delete-avatar'];

    public function handle()
    {
        $this->ensureClient();

        intro('Removing Application Avatar');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        $this->confirmDestructive("Remove the avatar from '{$application->name}'?");

        try {
            spin(
                fn () => $this->client->applications()->deleteAvatar($application->id),
                'Removing avatar...',
            );

            $this->outputJsonIfWanted('Application avatar removed.');

            success('Application avatar removed.');
        } catch (RequestException $e) {
            error('Failed to remove application avatar: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
