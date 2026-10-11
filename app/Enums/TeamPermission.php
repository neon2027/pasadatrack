<?php

namespace App\Enums;

enum TeamPermission: string
{
    case ManageOperations = 'operations:manage';
    case ReversePayment = 'payment:reverse';

    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}
