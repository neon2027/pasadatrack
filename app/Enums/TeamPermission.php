<?php

namespace App\Enums;

enum TeamPermission: string
{
    case ManageOperations = 'operations:manage';
    case ViewFleet = 'fleet:view';
    case ManageFleet = 'fleet:manage';
    case ViewScheduling = 'scheduling:view';
    case ManageScheduling = 'scheduling:manage';
    case ViewCollections = 'collections:view';
    case ManageCollections = 'collections:manage';
    case ViewAttendance = 'attendance:view';
    case ManageAttendance = 'attendance:manage';
    case ManageDevices = 'attendance-devices:manage';
    case ViewReports = 'reports:view';
    case ExportReports = 'reports:export';
    case ViewAudit = 'audit:view';
    case ReversePayment = 'payment:reverse';

    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    /** @return array<string> */
    public static function catalog(): array
    {
        return array_values(array_map(fn (self $permission): string => $permission->value,
            array_filter(self::cases(), fn (self $permission): bool => ! in_array($permission, [self::ManageOperations, self::AddMember], true))));
    }

    /** @return array<string, string> */
    public static function dependencies(): array
    {
        return [
            self::ManageFleet->value => self::ViewFleet->value,
            self::ManageScheduling->value => self::ViewScheduling->value,
            self::ManageCollections->value => self::ViewCollections->value,
            self::ManageAttendance->value => self::ViewAttendance->value,
            self::ManageDevices->value => self::ViewAttendance->value,
            self::ReversePayment->value => self::ViewCollections->value,
            self::ExportReports->value => self::ViewReports->value,
        ];
    }
}
