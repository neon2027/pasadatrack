import { usePage } from '@inertiajs/react';
import { ArrowRightLeft, Pencil, Undo2 } from 'lucide-react';
import { choices, Filters, localTime, OperationsForm, Pagination, RecordTable, Status, Workspace, pesos } from '@/components/operations-workspace';
import type { PageData } from '@/components/operations-workspace';
import { Button } from '@/components/ui/button';
import * as collections from '@/routes/collections';

type Obligation = { id: number; driver_id: number; driver: string; date: string; plate: string; original: string; adjusted: string; paid: string; outstanding: string };
type Payment = { id: number; driver_id: number; driver: string; receipt_number: string; amount: string; available: string; collected_at: string; reversed: boolean; reversal_reason: string | null };
type Props = { obligations: PageData<Obligation>; payments: PageData<Payment>; drivers: { id: number; name: string }[]; filters: { date?: string; driver_id?: number }; canManage: boolean; canReverse: boolean };
const manilaNow = () => new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Manila' }).replace(' ', 'T').slice(0, 16);

export default function Collections(props: Props) {
    const team = usePage().props.currentTeam!.slug;
    return <Workspace title="Boundary & collections" description="Review obligations from confirmed driving, record receipts, and allocate collections to the same driver." action={props.canManage && props.drivers.length > 0 && <OperationsForm title="Record payment" description="Recording a receipt creates an unallocated balance. Allocate it to boundary obligations after saving." fields={[
        { name: 'driver_id', label: 'Driver', options: choices(props.drivers), required: true }, { name: 'receipt_number', label: 'Receipt number', required: true },
        { name: 'amount', label: 'Amount (PHP)', type: 'money', required: true }, { name: 'collected_at', label: 'Collected at (Manila)', type: 'datetime-local', required: true },
    ]} initial={{ collected_at: manilaNow() }} action={collections.store(team)} saveLabel="Record payment" review />}>
        <Filters action={collections.index.url(team)} fields={[{ name: 'driver_id', label: 'Driver', options: choices(props.drivers) }, { name: 'date', label: 'Driving date', type: 'date' }]} initial={props.filters} />
        <section className="grid gap-4"><h2 className="font-semibold">Boundary obligations</h2>
            <RecordTable headings={['Obligation', 'Actual driver', 'Original', 'Adjusted', 'Allocated', 'Outstanding', 'Actions']} count={props.obligations.data.length} empty="No boundary obligations found. Confirm actual driving from Scheduling to create an obligation.">
                {props.obligations.data.map((obligation) => <tr key={obligation.id}>
                    <td><p className="font-mono font-medium">#{obligation.id} · {obligation.plate}</p><p className="text-xs text-muted-foreground">{obligation.date}</p></td><td>{obligation.driver}</td>
                    <td className="tabular-nums whitespace-nowrap">{pesos(obligation.original)}</td><td className="tabular-nums whitespace-nowrap">{pesos(obligation.adjusted)}</td><td className="tabular-nums whitespace-nowrap">{pesos(obligation.paid)}</td>
                    <td className="font-semibold tabular-nums whitespace-nowrap">{pesos(obligation.outstanding)}<p className="mt-1"><Status value={Number(obligation.outstanding) === 0 ? 'paid' : Number(obligation.paid) > 0 ? 'partial' : 'unpaid'} /></p></td>
                    <td>{props.canManage && <OperationsForm title="Adjust boundary" description={`Approve an adjustment for obligation #${obligation.id}, ${obligation.driver}. The original amount stays in history. Adjusted boundary must cover allocated payments.`} fields={[{ name: 'amount', label: 'Adjustment (PHP)', type: 'money', required: true, help: 'A positive amount increases the boundary; a negative amount reduces it.' }, { name: 'reason', label: 'Reason for approval', type: 'textarea', required: true }]} action={collections.adjust({ current_team: team, obligation: obligation.id })} saveLabel="Approve adjustment" review trigger={<Button variant="ghost" size="sm"><Pencil /> Adjust</Button>} />}</td>
                </tr>)}
            </RecordTable><Pagination page={props.obligations} />
        </section>
        <section className="grid gap-4"><div><h2 className="font-semibold">Receipts</h2><p className="text-sm text-muted-foreground">Unallocated amounts remain on the receipt until assigned. The driving date filter applies to obligations.</p></div>
            <RecordTable headings={['Receipt', 'Driver', 'Collected', 'Amount', 'Unallocated', 'Actions']} count={props.payments.data.length} empty="No receipts found. Record the first payment when a collection is received.">
                {props.payments.data.map((payment) => <tr key={payment.id}>
                    <td><p className="font-mono font-medium">{payment.receipt_number}</p>{payment.reversed && <><Status value="reversed" /><p className="mt-1 max-w-52 text-xs text-muted-foreground">{payment.reversal_reason}</p></>}</td>
                    <td>{payment.driver}</td><td className="whitespace-nowrap">{localTime(payment.collected_at)}</td><td className="tabular-nums whitespace-nowrap">{pesos(payment.amount)}</td><td className="tabular-nums whitespace-nowrap">{pesos(payment.available)}</td>
                    <td><div className="flex flex-wrap gap-1">{props.canManage && !payment.reversed && Number(payment.available) > 0 && <OperationsForm title="Allocate payment" description={`Allocate receipt ${payment.receipt_number} to ${payment.driver}’s boundary. Available: ${pesos(payment.available)}.`} fields={[{ name: 'boundary_obligation_id', label: 'Boundary obligation number', type: 'number', required: true, help: 'Use the # number in the boundary table. The obligation must belong to this driver.' }, { name: 'amount', label: 'Amount to allocate (PHP)', type: 'money', required: true }]} action={collections.allocate({ current_team: team, payment: payment.id })} saveLabel="Allocate payment" review trigger={<Button variant="outline" size="sm"><ArrowRightLeft /> Allocate</Button>} />}
                    {props.canReverse && !payment.reversed && <OperationsForm title="Reverse payment" description={`Reverse receipt ${payment.receipt_number} (${pesos(payment.amount)}). The receipt remains in history and its allocations stop reducing outstanding balances.`} fields={[{ name: 'reason', label: 'Reason', type: 'textarea', required: true }]} action={collections.reverse({ current_team: team, payment: payment.id })} saveLabel="Reverse payment" review trigger={<Button variant="ghost" size="sm"><Undo2 /> Reverse</Button>} />}</div></td>
                </tr>)}
            </RecordTable><Pagination page={props.payments} />
        </section>
    </Workspace>;
}
