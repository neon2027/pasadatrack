import AdminWorkspace from "@/components/admin-workspace";
import { Filters, Pagination, RecordTable } from "@/components/operations-workspace";
import type { PageData } from "@/components/operations-workspace";
import { index } from "@/routes/admin/audit";
type Entry = {
    id: number;
    actor: string;
    source: string;
    action: string;
    subject: string;
    reason: string;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    occurred_at: string;
};
export default function Audit({
    entries,
    filters,
}: {
    entries: PageData<Entry>;
    filters: Record<string, string>;
}) {
    return (
        <AdminWorkspace
            title="Administration audit"
            description="A permanent history of user access, administrator privileges, team roles, and ownership limit changes."
        >
            <Filters
                action={index.url()}
                initial={filters}
                fields={[{ name: "search", label: "Action" }]}
            />
            <RecordTable
                headings={["When", "Actor", "Change", "Reason", "Details"]}
                count={entries.data.length}
                empty="No administrative changes match these filters."
            >
                {entries.data.map((entry) => (
                    <tr key={entry.id}>
                        <td className="whitespace-nowrap text-xs">
                            {new Date(entry.occurred_at).toLocaleString("en-PH", {
                                timeZone: "Asia/Manila",
                            })}
                        </td>
                        <td>
                            {entry.actor}
                            <p className="text-xs text-muted-foreground">{entry.source}</p>
                        </td>
                        <td>
                            {entry.action}
                            <p className="text-xs text-muted-foreground">{entry.subject}</p>
                        </td>
                        <td className="max-w-sm break-words">{entry.reason}</td>
                        <td>
                            <details>
                                <summary className="cursor-pointer">Before / after</summary>
                                <pre className="mt-2 max-w-sm overflow-auto rounded bg-muted p-3 text-xs">
                                    {JSON.stringify(
                                        { before: entry.before, after: entry.after },
                                        null,
                                        2,
                                    )}
                                </pre>
                            </details>
                        </td>
                    </tr>
                ))}
            </RecordTable>
            <Pagination page={entries} />
        </AdminWorkspace>
    );
}
