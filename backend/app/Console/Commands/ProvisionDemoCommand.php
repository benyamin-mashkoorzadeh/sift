<?php

namespace App\Console\Commands;

use App\Actions\Demo\ProvisionDemoEnvironment;
use App\Exceptions\Demo\DemoProvisioningException;
use Illuminate\Console\Command;
use Throwable;

class ProvisionDemoCommand extends Command
{
    protected $signature = 'sift:provision-demo {--dry-run : Validate without writing data, storage, or calling providers}';

    protected $description = 'Provision the dedicated Lumenfield Supply Guest Demo dataset';

    public function handle(ProvisionDemoEnvironment $provisionDemoEnvironment): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $provisionDemoEnvironment->handle($dryRun);
        } catch (DemoProvisioningException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The Guest Demo could not be provisioned safely. No unrelated data was changed.');

            return self::FAILURE;
        }

        if ($result->dryRun) {
            $this->info('Guest Demo dry-run checks passed. No changes were made.');

            return self::SUCCESS;
        }

        $this->info('Guest Demo provisioning completed successfully.');
        $this->line("DEMO_USER_ID={$result->userId}");
        $this->line("DEMO_WORKSPACE_ID={$result->workspaceId}");

        return self::SUCCESS;
    }
}
