import { usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { Filters, OperationsForm, Pagination, RecordTable, Status, Workspace, pesos } from '@/components/operations-workspace';
import type { Field, PageData } from '@/components/operations-workspace';
import { Button } from '@/components/ui/button';
import * as drivers from '@/routes/drivers';
import * as jeepneys from '@/routes/jeepneys';
import * as routes from '@/routes/routes';

type MasterRecord = { id: number; is_active: boolean; [key: string]: string | number | boolean | null };
type Props = { kind: 'drivers' | 'jeepneys' | 'routes'; title: string; description: string; records: PageData<MasterRecord>; filters: { search?: string; status?: string }; canManage: boolean };
const definitions: Record<Props['kind'], { singular: string; fields: Field[]; columns: { key: string; label: string }[] }> = {
    drivers: { singular: 'driver', fields: [
        { name: 'code', label: 'Driver code', required: true }, { name: 'name', label: 'Full name', required: true },
        { name: 'license_number', label: 'License number' }, { name: 'phone', label: 'Phone number', type: 'tel' },
    ], columns: [{ key: 'code', label: 'Code' }, { key: 'name', label: 'Driver' }, { key: 'license_number', label: 'License' }, { key: 'phone', label: 'Phone' }] },
    jeepneys: { singular: 'jeepney', fields: [
        { name: 'plate_number', label: 'Plate number', required: true }, { name: 'name', label: 'Fleet name' }, { name: 'capacity', label: 'Passenger capacity', type: 'number' },
    ], columns: [{ key: 'plate_number', label: 'Plate number' }, { key: 'name', label: 'Fleet name' }, { key: 'capacity', label: 'Capacity' }] },
    routes: { singular: 'route', fields: [
        { name: 'code', label: 'Route code', required: true }, { name: 'name', label: 'Route name', required: true },
        { name: 'origin', label: 'Origin', required: true }, { name: 'destination', label: 'Destination', required: true },
        { name: 'default_boundary_amount', label: 'Default boundary (PHP)', type: 'money', required: true, help: 'Applied when driving is confirmed. Existing obligations retain their recorded amounts.' },
    ], columns: [{ key: 'code', label: 'Code' }, { key: 'name', label: 'Route' }, { key: 'origin', label: 'Origin' }, { key: 'destination', label: 'Destination' }, { key: 'default_boundary_amount', label: 'Default boundary' }] },
};

export default function Masters({ kind, title, description, records, filters, canManage }: Props) {
    const team = usePage().props.currentTeam!.slug;
    const definition = definitions[kind];
    const fields = [...definition.fields, { name: 'is_active', label: 'Active', type: 'checkbox', help: 'Inactive records remain in history and cannot be used for new shifts.' }];
    const endpoints = { drivers, jeepneys, routes }[kind];
    const update = (id: number) => kind === 'drivers' ? drivers.update.patch({ current_team: team, driver: id }) : kind === 'jeepneys' ? jeepneys.update.patch({ current_team: team, jeepney: id }) : routes.update.patch({ current_team: team, route: id });
    return <Workspace title={title} description={description} action={canManage && <OperationsForm title={`Add ${definition.singular}`} description={description} fields={fields} action={endpoints.store(team)} saveLabel={`Create ${definition.singular}`} />}>
        <Filters action={endpoints.index.url(team)} fields={[{ name: 'search', label: 'Search code or name' }, { name: 'status', label: 'Status', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }]} initial={filters} />
        <RecordTable headings={[...definition.columns.map((column) => column.label), 'Status', ...(canManage ? ['Actions'] : [])]} count={records.data.length} empty={filters.search || filters.status ? 'No records match these filters. Clear the filters to see all records.' : `Add your first ${definition.singular} to start organizing operations.`}>
            {records.data.map((record) => <tr key={record.id}>
                {definition.columns.map((column) => <td key={column.key} className={column.key === 'code' || column.key === 'plate_number' ? 'font-mono font-medium' : ''}>{column.key === 'default_boundary_amount' ? pesos(String(record[column.key])) : String(record[column.key] ?? '—')}</td>)}
                <td><Status value={record.is_active ? 'active' : 'inactive'} /></td>
                {canManage && <td><OperationsForm key={JSON.stringify(record)} title={`Edit ${definition.singular}`} description={description} fields={fields} initial={record} action={update(record.id)} trigger={<Button variant="ghost" size="sm"><Pencil /> Edit<span className="sr-only"> {String(record.name ?? record.plate_number)}</span></Button>} /></td>}
            </tr>)}
        </RecordTable>
        <Pagination page={records} />
    </Workspace>;
}
