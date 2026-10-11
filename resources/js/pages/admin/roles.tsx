import { usePage } from "@inertiajs/react";
import { Pencil } from "lucide-react";
import AdminWorkspace from "@/components/admin-workspace";
import { OperationsForm, RecordTable, Status } from "@/components/operations-workspace";
import type { Field, FieldGroup } from "@/components/operations-workspace";
import { Button } from "@/components/ui/button";
import * as roles from "@/routes/admin/roles";
type Role = {
    id: number;
    name: string;
    permissions: string[];
    is_owner: boolean;
    is_archived: boolean;
};
type Props = { roles: Role[]; catalog: string[]; dependencies: Record<string, string> };
const label = (permission: string) =>
    permission
        .replaceAll("-", " ")
        .replace(":", " · ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const permissionLabel = (permission: string) =>
    ({
        "attendance-devices:manage": "Manage devices",
        "payment:reverse": "Reverse payments",
        "team:update": "Edit team settings",
        "team:delete": "Delete team",
        "member:update": "Change member roles",
        "member:remove": "Remove members",
        "invitation:create": "Invite members",
        "invitation:cancel": "Cancel invitations",
    })[permission] ??
    (permission.endsWith(":view")
        ? "View"
        : permission.endsWith(":manage")
          ? "Manage"
          : permission.endsWith(":export")
            ? "Export"
            : label(permission));
export default function Roles(props: Props) {
    const canChange = !usePage().props.auth.requiresGoogleConfirmation;
    const fields: Field[] = [
        { name: "name", label: "Role name", required: true },
        ...props.catalog.map((permission) => ({
            name: permission,
            label: permissionLabel(permission),
            accessibleLabel: label(permission),
            type: "checkbox",
            defaultValue: false,
            help: props.dependencies[permission]
                ? `Requires ${label(props.dependencies[permission])}.`
                : undefined,
        })),
        { name: "reason", label: "Reason for this change", type: "textarea", required: true },
    ];
    const fieldGroups: FieldGroup[] = [
        { title: "Fleet", prefixes: ["fleet"] },
        { title: "Scheduling", prefixes: ["scheduling"] },
        { title: "Collections & payments", prefixes: ["collections", "payment"] },
        { title: "Attendance", prefixes: ["attendance", "attendance-devices"] },
        { title: "Reports", prefixes: ["reports"] },
        { title: "Audit history", prefixes: ["audit"] },
        { title: "Team settings", prefixes: ["team"] },
        { title: "Members", prefixes: ["member"] },
        { title: "Invitations", prefixes: ["invitation"] },
    ]
        .map((group) => ({
            title: group.title,
            fields: props.catalog.filter((permission) =>
                group.prefixes.includes(permission.split(":")[0]),
            ),
        }))
        .filter((group) => group.fields.length > 0);
    const transform = (data: Record<string, string | boolean>) => ({
        name: data.name,
        reason: data.reason,
        is_archived: data.is_archived ?? false,
        permissions: props.catalog.filter((permission) => data[permission] === true),
    });
    return (
        <AdminWorkspace
            title="Team role permissions"
            description="Define shared roles for team Owners to assign. Platform administrator privileges remain separate from team access."
            action={
                canChange && (
                    <OperationsForm
                        title="Create team role"
                        description="Choose only the permissions this role needs. Manage permissions require their corresponding View permission."
                        fields={fields}
                        fieldGroups={fieldGroups}
                        action={roles.store()}
                        transform={transform}
                        saveLabel="Create role"
                        review
                    />
                )
            }
        >
            <p className="text-sm text-muted-foreground">
                Archiving blocks new assignments; current members keep the role and its permissions.
                Changes to permissions apply on the next request.
            </p>
            <RecordTable
                headings={["Role", "Permissions", "Status", "Actions"]}
                count={props.roles.length}
                empty="No roles available."
            >
                {props.roles.map((role) => (
                    <tr key={role.id}>
                        <td className="font-medium">{role.name}</td>
                        <td className="max-w-lg">
                            <p className="text-xs leading-relaxed">
                                {role.is_owner
                                    ? "All team permissions · protected ownership"
                                    : role.permissions.map(label).join(", ") || "Dashboard only"}
                            </p>
                        </td>
                        <td>
                            <Status value={role.is_archived ? "archived" : "active"} />
                        </td>
                        <td>
                            {!role.is_owner && canChange && (
                                <OperationsForm
                                    key={JSON.stringify(role)}
                                    title="Edit team role"
                                    description="These changes affect every team using this shared role. Owner permissions cannot be edited."
                                    initial={{
                                        name: role.name,
                                        is_archived: role.is_archived,
                                        ...Object.fromEntries(
                                            props.catalog.map((permission) => [
                                                permission,
                                                role.permissions.includes(permission),
                                            ]),
                                        ),
                                    }}
                                    fields={[
                                        ...fields,
                                        {
                                            name: "is_archived",
                                            label: "Archive role",
                                            help: "Stop new assignments. Existing members keep their access.",
                                            type: "checkbox",
                                            defaultValue: false,
                                        },
                                    ]}
                                    fieldGroups={fieldGroups}
                                    action={roles.update(role.id)}
                                    transform={transform}
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            <Pencil /> Edit
                                            <span className="sr-only"> {role.name}</span>
                                        </Button>
                                    }
                                    review
                                />
                            )}
                        </td>
                    </tr>
                ))}
            </RecordTable>
        </AdminWorkspace>
    );
}
