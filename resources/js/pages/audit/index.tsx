import { usePage } from '@inertiajs/react';
import { Filters, localTime, Pagination, RecordTable, Workspace } from '@/components/operations-workspace';
import type { PageData } from '@/components/operations-workspace';
import * as audit from '@/routes/audit';
type Entry = { id: number; action: string; subject: string; actor: string; occurred_at: string; reason: string | null; before: Record<string, unknown> | null; after: Record<string, unknown> | null };
export default function Audit({ entries, filters }: { entries: PageData<Entry>; filters: { search?: string } }) {
    const team = usePage().props.currentTeam!.slug;
    return <Workspace title="Audit history" description="Review who recorded operational changes, when they happened, and the preserved values. All displayed times use Asia/Manila.">
        <Filters action={audit.index.url(team)} fields={[{ name: 'search', label: 'Search action', help: 'For example: payment, attendance, assignment' }]} initial={filters} />
        <RecordTable headings={['When', 'Action', 'Record', 'Recorded by', 'Details']} count={entries.data.length} empty="No operational changes found. Changes appear here when records are saved.">
            {entries.data.map((entry) => <tr key={entry.id}><td className="whitespace-nowrap">{localTime(entry.occurred_at)}</td><td className="font-mono text-xs">{entry.action}</td><td>{entry.subject}</td><td>{entry.actor}</td><td><details><summary className="cursor-pointer">View changes</summary>{entry.reason && <p className="my-2">{entry.reason}</p>}<pre className="mt-2 max-h-72 max-w-md overflow-auto rounded bg-muted p-3 text-xs">{JSON.stringify({ before: entry.before, after: entry.after }, null, 2)}</pre></details></td></tr>)}
        </RecordTable><Pagination page={entries} />
    </Workspace>;
}
