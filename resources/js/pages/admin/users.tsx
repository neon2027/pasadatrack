import { usePage } from "@inertiajs/react";
import { Pencil } from "lucide-react";
import AdminWorkspace from "@/components/admin-workspace";
import {
    Filters,
    OperationsForm,
    Pagination,
    RecordTable,
    Status,
} from "@/components/operations-workspace";
import type { Field, PageData } from "@/components/operations-workspace";
import { Button } from "@/components/ui/button";
import * as users from "@/routes/admin/users";
type Level = { id: number; name: string; team_limit: number };
type ManagedUser = {
    id: number;
    name: string;
    email: string;
    platform_role: string;
    account_status: string;
    privilege_level_id: number;
    level: string;
    team_limit: number;
    owned_teams_count: number;
    google_connected: boolean;
    two_factor_enabled: boolean;
    memberships: { team: string; role: string }[];
};
type Props = { users: PageData<ManagedUser>; levels: Level[]; filters: Record<string, string> };
export default function Users(props: Props) {
    const { auth } = usePage().props;
    const levels = props.levels.map((level) => ({
        value: String(level.id),
        label: `${level.name} · ${level.team_limit} teams`,
    }));
    const roles = [
        { value: "user", label: "User" },
        { value: "admin", label: "Admin" },
        { value: "super-admin", label: "Super Admin" },
    ];
    const common: Field[] = [
        { name: "name", label: "Name", required: true },
        {
            name: "privilege_level_id",
            label: "Privilege level",
            options: levels,
            required: true,
            defaultValue: String(props.levels[0]?.id ?? 1),
        },
        ...(auth.canManageCatalog
            ? [
                  {
                      name: "platform_role",
                      label: "Platform role",
                      options: roles,
                      required: true,
                      defaultValue: "user",
                  },
              ]
            : []),
        { name: "reason", label: "Reason for this change", type: "textarea", required: true },
    ];
    const canChange = !auth.requiresGoogleConfirmation;
    return (
        <AdminWorkspace
            title="User access"
            description="Provision Google accounts, manage access, and assign team ownership limits. Team memberships remain managed by each team."
            action={
                canChange && (
                    <OperationsForm
                        title="Add user"
                        description="The user must sign in with this Google email. Their personal team counts toward their level’s limit. Email ownership and two-factor checks still apply."
                        fields={[
                            { name: "email", label: "Google email", type: "email", required: true },
                            ...common,
                        ]}
                        action={users.store()}
                        saveLabel="Add user"
                        review
                    />
                )
            }
        >
            <Filters
                action={users.index.url()}
                initial={props.filters}
                fields={[
                    { name: "search", label: "Name or email" },
                    {
                        name: "status",
                        label: "Access status",
                        options: [
                            { value: "active", label: "Active" },
                            { value: "suspended", label: "Suspended" },
                        ],
                    },
                    ...(auth.canManageCatalog
                        ? [{ name: "role", label: "Platform role", options: roles }]
                        : []),
                    { name: "level", label: "Privilege level", options: levels },
                ]}
            />
            <RecordTable
                headings={["User", "Access", "Level & ownership", "Memberships", "Actions"]}
                count={props.users.data.length}
                empty="No users match these filters. Clear the filters or add a user."
            >
                {props.users.data.map((user) => (
                    <tr key={user.id}>
                        <td>
                            <p className="font-medium">{user.name}</p>
                            <p className="break-all text-xs text-muted-foreground">{user.email}</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {user.google_connected
                                    ? "Google connected"
                                    : "Awaiting first Google sign-in"}
                                {user.two_factor_enabled ? " · 2FA enabled" : ""}
                            </p>
                        </td>
                        <td>
                            <Status value={user.account_status} />
                            <p className="mt-1 text-xs capitalize">
                                {user.platform_role.replaceAll("-", " ")}
                            </p>
                        </td>
                        <td>
                            {user.level}
                            <p className="text-xs text-muted-foreground">
                                {user.owned_teams_count} / {user.team_limit} owned teams
                            </p>
                        </td>
                        <td>
                            <details>
                                <summary className="cursor-pointer">
                                    {user.memberships.length} memberships
                                </summary>
                                <ul className="mt-2 space-y-1">
                                    {user.memberships.map((membership, index) => (
                                        <li key={index} className="text-xs">
                                            {membership.team} · {membership.role}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        </td>
                        <td>
                            {canChange && (
                                <OperationsForm
                                    title="Edit user access"
                                    description="Suspension or a platform role change revokes current sessions. Existing teams and records are preserved."
                                    initial={{
                                        name: user.name,
                                        privilege_level_id: user.privilege_level_id,
                                        platform_role: user.platform_role,
                                        account_status: user.account_status,
                                    }}
                                    fields={[
                                        ...common,
                                        {
                                            name: "account_status",
                                            label: "Access status",
                                            options: [
                                                { value: "active", label: "Active" },
                                                { value: "suspended", label: "Suspended" },
                                            ],
                                            required: true,
                                        },
                                    ]}
                                    action={users.update(user.id)}
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            <Pencil /> Edit
                                            <span className="sr-only"> {user.name}</span>
                                        </Button>
                                    }
                                    review
                                />
                            )}
                        </td>
                    </tr>
                ))}
            </RecordTable>
            <Pagination page={props.users} />
        </AdminWorkspace>
    );
}
