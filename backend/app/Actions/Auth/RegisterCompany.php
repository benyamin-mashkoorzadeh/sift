<?php

namespace App\Actions\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterCompany
{
    /**
     * @param  array{name: string, email: string, password: string, company_name: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);
            $workspace = Workspace::query()->create([
                'name' => $attributes['company_name'],
                'slug' => $this->uniqueSlug($attributes['company_name']),
            ]);

            $workspace->users()->attach($user->id, [
                'role' => WorkspaceRole::Owner->value,
            ]);

            return $user;
        });
    }

    private function uniqueSlug(string $companyName): string
    {
        $base = Str::slug($companyName);
        $base = $base === '' ? 'workspace' : Str::limit($base, 70, '');

        return $base.'-'.Str::lower((string) Str::ulid());
    }
}
