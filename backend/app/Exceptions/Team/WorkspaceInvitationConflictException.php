<?php

namespace App\Exceptions\Team;

use RuntimeException;

class WorkspaceInvitationConflictException extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function alreadyMember(): self
    {
        return new self('already_workspace_member', 'This person is already a member of the workspace.');
    }

    public static function incompatibleAccount(): self
    {
        return new self('invitation_account_unavailable', 'This account cannot be invited to this workspace.');
    }

    public static function duplicate(): self
    {
        return new self('active_invitation_exists', 'An active invitation already exists for this email address.');
    }

    public static function inactive(): self
    {
        return new self('invitation_inactive', 'This invitation is no longer active.');
    }

    public static function expired(): self
    {
        return new self('invitation_expired', 'This invitation has expired.');
    }

    public static function concurrency(): self
    {
        return new self('invitation_acceptance_conflict', 'This invitation could not be accepted. Please try again.');
    }

    public static function ownerProtected(): self
    {
        return new self('workspace_owner_protected', 'The workspace owner cannot be changed or removed.');
    }
}
