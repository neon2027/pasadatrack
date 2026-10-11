import { usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { choices, Filters, Pagination, RecordTable, Workspace, pesos } from '@/components/operations-workspace';
import type { PageData } from '@/components/operations-workspace';
import { Button } from '@/components/ui/button';
import * as reports from '@/routes/reports';

type Row = { id: number; driver: string; shifts: number; adjusted: string; paid: string; outstanding: string; collected: string; unallocated: string };
type Props = { rows: PageData<Row>; totals: Record<string, string>; filters: { from: string; to: string; driver_id?: number }; drivers: { id: number; name: string }[] };
export default function Reports(props: Props) {
    const team = usePage().props.currentTeam!.slug;
    return <Workspace title="Operations report" description="Boundary balances for driving in the selected period, alongside receipts collected during that period. Balances reflect all recorded allocations and reversals." action={<div className="flex flex-wrap gap-2">
        <Button variant="outline" asChild><a href={reports.exportMethod.url(team, { query: { ...props.filters, format: 'csv' } })}><Download /> CSV</a></Button>
        <Button variant="outline" asChild><a href={reports.exportMethod.url(team, { query: { ...props.filters, format: 'excel' } })}><Download /> Excel XML</a></Button>
    </div>}>
        <Filters action={reports.index.url(team)} fields={[{ name: 'from', label: 'From (Manila)', type: 'date' }, { name: 'to', label: 'To (Manila)', type: 'date' }, { name: 'driver_id', label: 'Driver', options: choices(props.drivers) }]} initial={props.filters} />
        <div className="grid gap-4 sm:grid-cols-3">{[{ field: 'adjusted', label: 'Boundary for selected driving' }, { field: 'outstanding', label: 'Still outstanding' }, { field: 'collected', label: 'Receipts in selected period' }].map((stat) => <div key={stat.field} className="rounded-lg border p-5"><p className="text-sm text-muted-foreground">{stat.label}</p><p className="mt-2 text-2xl font-semibold tracking-tight tabular-nums">{pesos(props.totals[stat.field])}</p></div>)}</div>
        <RecordTable headings={['Driver', 'Confirmed shifts', 'Boundary', 'Allocated', 'Outstanding', 'Collections', 'Unallocated receipts']} count={props.rows.data.length} empty="No confirmed driving or collections found for this period.">
            {props.rows.data.map((row) => <tr key={row.id}><td className="font-medium">{row.driver}</td><td>{row.shifts}</td>{['adjusted', 'paid', 'outstanding', 'collected', 'unallocated'].map((field) => <td key={field} className="tabular-nums whitespace-nowrap">{pesos(row[field as keyof Row] as string)}</td>)}</tr>)}
        </RecordTable><Pagination page={props.rows} />
        <p className="text-xs text-muted-foreground">Excel XML opens as a spreadsheet in Microsoft Excel. The report includes every matching driver in the download.</p>
    </Workspace>;
}
