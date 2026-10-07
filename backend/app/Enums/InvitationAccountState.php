<?php

namespace App\Enums;

enum InvitationAccountState: string
{
    case NewAccount = 'new_account';
    case ExistingAccount = 'existing_account';
}
