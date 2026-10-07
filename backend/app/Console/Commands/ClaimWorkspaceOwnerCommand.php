<?php

namespace App\Console\Commands;

use App\Actions\Auth\ClaimExistingWorkspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use LogicException;
use Throwable;

class ClaimWorkspaceOwnerCommand extends Command
{
    protected $signature = 'sift:claim-workspace {workspace : The existing workspace ID}';

    protected $description = 'Create the first owner for an existing workspace with no memberships';

    public function handle(ClaimExistingWorkspace $claimExistingWorkspace): int
    {
        $workspaceId = filter_var($this->argument('workspace'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($workspaceId === false) {
            $this->error('The workspace ID must be a positive integer.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Owner name'));
        $email = mb_strtolower(trim((string) $this->ask('Owner email')));
        $password = (string) $this->secret('Owner password');
        $passwordConfirmation = (string) $this->secret('Confirm owner password');
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->confirm("Create this owner for workspace {$workspaceId}?", false)) {
            $this->warn('No changes were made.');

            return self::SUCCESS;
        }

        try {
            $claimExistingWorkspace->handle($workspaceId, $name, $email, $password);
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('The workspace owner could not be created. No partial changes were kept.');

            return self::FAILURE;
        }

        $this->info("Workspace {$workspaceId} now has an owner.");

        return self::SUCCESS;
    }
}
