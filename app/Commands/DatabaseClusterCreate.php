<?php

namespace App\Commands;

use App\Concerns\CreatesDatabaseCluster;
use App\Concerns\DeterminesDefaultRegion;
use App\Dto\DatabaseCluster;

use function Laravel\Prompts\intro;

class DatabaseClusterCreate extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseCluster::class;

    use CreatesDatabaseCluster;
    use DeterminesDefaultRegion;

    protected $signature = 'database-cluster:create
                            {--name= : Database cluster name}
                            {--type= : Database type (run `cloud database:types` for options)}
                            {--engine-version= : Database engine version (e.g. 18, 8.4). Default: newest available}
                            {--region= : Database region}
                            {--cluster= : Dedicated cluster ID to create the database cluster on}
                            {--create-default-database= : Whether to create a default database (true or false; Neon clusters cannot skip it). Default: true}
                            {--preset= : Preset to start from, as listed by the API for the type and region (e.g. dev, prod; case-insensitive). Default: the first preset offered}
                            {--size= : Instance size (Laravel MySQL, AWS RDS)}
                            {--storage= : Storage in GB (Laravel MySQL, AWS RDS)}
                            {--storage-autoscale-max-gb= : Largest storage in GB the cluster may grow to on its own, or "null" to turn it off (Laravel MySQL)}
                            {--is-public= : Whether publicly accessible (Laravel MySQL, AWS RDS)}
                            {--uses-scheduled-snapshots= : Whether scheduled backups are enabled (Laravel MySQL)}
                            {--retention-days= : Days to retain backups}
                            {--maintenance-window= : UTC maintenance window, e.g. sun:06:00-sun:06:30 (Laravel MySQL, AWS RDS)}
                            {--suspend-seconds= : Seconds of inactivity before scale to zero, 0 for never (Laravel MySQL, Neon)}
                            {--cu-min= : Minimum compute units (Neon)}
                            {--cu-max= : Maximum compute units (Neon)}
                            {--uses-pitr= : Whether point-in-time recovery is enabled (AWS RDS)}
                            {--deployment-option= : single-az, single-az-with-read-replicas, or multi-az (AWS RDS)}
                            {--read-replicas= : Number of read replicas (AWS RDS)}';

    protected $description = 'Create a new database cluster';

    protected $aliases = ['db-cluster:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Create Database Cluster');

        $database = $this->loopUntilValid(
            fn () => $this->createDatabaseCluster(),
        );

        $this->outputJsonIfWanted($database);

        success("Database cluster created: {$database->name}");
    }
}
