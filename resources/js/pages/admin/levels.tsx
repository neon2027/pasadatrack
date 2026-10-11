import { usePage } from "@inertiajs/react";
import { Pencil } from "lucide-react";
import AdminWorkspace from "@/components/admin-workspace";
import { OperationsForm, RecordTable } from "@/components/operations-workspace";
import { Button } from "@/components/ui/button";
import * as levels from "@/routes/admin/levels";
export default function Levels({
    levels: records,
}: {
    levels: { id: number; name: string; team_limit: number }[];
}) {
    const canChange = !usePage().props.auth.requiresGoogleConfirmation;
    return (
        <AdminWorkspace
            title="Privilege levels"
            description="Set how many teams users may own. The personal team counts; memberships in other users’ teams do not."
        >
            <p className="rounded-lg border p-4 text-sm">
                Reducing a limit preserves existing teams. Users above the limit cannot create
                another team until upgraded or below the limit. Levels do not grant module
                permissions.
            </p>
            <RecordTable
                headings={["Level", "Maximum owned teams", "Actions"]}
                count={records.length}
                empty="No privilege levels available."
            >
                {records.map((level) => (
                    <tr key={level.id}>
                        <td className="font-medium">{level.name}</td>
                        <td className="tabular-nums">{level.team_limit}</td>
                        <td>
                            {canChange && (
                                <OperationsForm
                                    title="Edit privilege level"
                                    description="The new limit applies to everyone assigned this level. No existing teams will be removed."
                                    initial={{ name: level.name, team_limit: level.team_limit }}
                                    fields={[
                                        { name: "name", label: "Level name", required: true },
                                        {
                                            name: "team_limit",
                                            label: "Maximum owned teams",
                                            type: "number",
                                            required: true,
                                        },
                                        {
                                            name: "reason",
                                            label: "Reason for this change",
                                            type: "textarea",
                                            required: true,
                                        },
                                    ]}
                                    action={levels.update(level.id)}
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            <Pencil /> Edit
                                            <span className="sr-only"> {level.name}</span>
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
