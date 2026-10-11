import { Link, usePage } from "@inertiajs/react";
import { Fingerprint, Pencil } from "lucide-react";
import {
    choices,
    Filters,
    localTime,
    OperationsForm,
    Pagination,
    RecordTable,
    Status,
    Workspace,
} from "@/components/operations-workspace";
import type { Field, PageData } from "@/components/operations-workspace";
import { Button } from "@/components/ui/button";
import * as attendance from "@/routes/attendance";
import * as devices from "@/routes/devices";

type Session = {
    id: number;
    driver_id: number;
    driver: string;
    source: string;
    in_at: string;
    out_at: string | null;
    corrections: { reason: string; actor: string }[];
};
type Props = {
    sessions: PageData<Session>;
    drivers: { id: number; name: string }[];
    filters: { date?: string; driver_id?: number };
    canManage: boolean;
    canManageDevices: boolean;
};
export default function Attendance(props: Props) {
    const team = usePage().props.currentTeam!.slug;
    const fields: Field[] = [
        { name: "driver_id", label: "Driver", options: choices(props.drivers), required: true },
        { name: "in_at", label: "Time in (Manila)", type: "datetime-local", required: true },
        {
            name: "out_at",
            label: "Time out (Manila)",
            type: "datetime-local",
            help: "Leave empty while the driver is still checked in.",
        },
        {
            name: "reason",
            label: "Reason for manual entry or correction",
            type: "textarea",
            required: true,
        },
    ];
    return (
        <Workspace
            title="Attendance"
            description="Record check-in and check-out times. Attendance is evidence and does not confirm driving or create a boundary obligation."
            action={
                <div className="flex flex-wrap gap-2">
                    {props.canManageDevices && (
                        <Button variant="outline" asChild>
                            <Link href={devices.index(team)}>
                                <Fingerprint /> Devices & scans
                            </Link>
                        </Button>
                    )}
                    {props.canManage && props.drivers.length > 0 && (
                        <OperationsForm
                            title="Record attendance"
                            description="Use manual attendance when a biometric device is unavailable. Enter all times in Asia/Manila."
                            fields={fields}
                            action={attendance.store(team)}
                            saveLabel="Record attendance"
                        />
                    )}
                </div>
            }
        >
            <Filters
                action={attendance.index.url(team)}
                fields={[
                    { name: "driver_id", label: "Driver", options: choices(props.drivers) },
                    { name: "date", label: "Check-in date (Manila)", type: "date" },
                ]}
                initial={props.filters}
            />
            <RecordTable
                headings={[
                    "Driver",
                    "Time in",
                    "Time out",
                    "Source",
                    "Correction history",
                    "Actions",
                ]}
                count={props.sessions.data.length}
                empty="No attendance sessions found. Record manual attendance or import biometric scans."
            >
                {props.sessions.data.map((session) => (
                    <tr key={session.id}>
                        <td className="font-medium">{session.driver}</td>
                        <td className="whitespace-nowrap">{localTime(session.in_at)}</td>
                        <td className="whitespace-nowrap">{localTime(session.out_at)}</td>
                        <td>
                            <Status value={session.source} />
                        </td>
                        <td>
                            {session.corrections.length ? (
                                <details>
                                    <summary className="cursor-pointer">
                                        {session.corrections.length} correction(s)
                                    </summary>
                                    <ul className="mt-2 grid gap-2">
                                        {session.corrections.map((correction, i) => (
                                            <li key={i}>
                                                <p>{correction.reason}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {correction.actor}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            ) : (
                                "—"
                            )}
                        </td>
                        <td>
                            {props.canManage && (
                                <OperationsForm
                                    key={JSON.stringify(session)}
                                    title="Correct attendance"
                                    description={`Correct attendance for ${session.driver}. Original values and the reason remain in history. The corrected session will be marked manual.`}
                                    fields={fields}
                                    initial={{
                                        driver_id: session.driver_id,
                                        in_at: session.in_at,
                                        out_at: session.out_at,
                                    }}
                                    action={attendance.update.patch({
                                        current_team: team,
                                        attendance: session.id,
                                    })}
                                    saveLabel="Save correction"
                                    review
                                    trigger={
                                        <Button size="sm" variant="ghost">
                                            <Pencil /> Correct
                                        </Button>
                                    }
                                />
                            )}
                        </td>
                    </tr>
                ))}
            </RecordTable>
            <Pagination page={props.sessions} />
        </Workspace>
    );
}
