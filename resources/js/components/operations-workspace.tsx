import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Plus, Search } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type PageData<T> = {
    data: T[]; total: number; from: number | null; to: number | null;
    current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null;
};
export type Choice = { value: string; label: string };
export type Field = {
    name: string; label: string; type?: string; required?: boolean; help?: string; readOnly?: boolean;
    options?: Choice[]; defaultValue?: string | boolean; accessibleLabel?: string;
};
export type FieldGroup = { title: string; fields: string[] };
type Mutation = { url: string; method: 'post' | 'put' | 'patch' | 'delete' };
function createSubmissionId(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
export const pesos = (value: string) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value));
export const localTime = (value: string | null) => value ? value.replace('T', ' ') : 'Open session';
export const choices = (items: { id: number; name: string }[]): Choice[] => items.map((item) => ({ value: String(item.id), label: item.name }));

export function Workspace({ title, description, action, children, area = 'Operations' }: { title: string; description: string; action?: ReactNode; children: ReactNode; area?: string }) {
    return <>
        <Head title={title} />
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <header className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                <div><p className="mb-1 text-xs font-semibold tracking-widest text-muted-foreground uppercase">PasadaTrack · {area}</p>
                    <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{description}</p></div>
                {action}
            </header>
            {children}
        </div>
    </>;
}

export function Status({ value }: { value: string }) {
    return <Badge variant={value === 'cancelled' || value === 'reversed' || value === 'inactive' ? 'outline' : 'secondary'} className="capitalize">{value.replaceAll('-', ' ')}</Badge>;
}

export function RecordTable({ headings, children, empty, count }: { headings: string[]; children: ReactNode; empty: string; count: number }) {
    return <div className="overflow-x-auto rounded-lg border">
        <table className="w-full text-left text-sm">
            <thead className="bg-muted/50"><tr>{headings.map((heading) => <th key={heading} scope="col" className="px-4 py-3 font-medium whitespace-nowrap">{heading}</th>)}</tr></thead>
            <tbody className="[&_td]:px-4 [&_td]:py-3 [&_tr]:border-t">{count ? children : <tr><td colSpan={headings.length} className="h-36 text-center text-muted-foreground">{empty}</td></tr>}</tbody>
        </table>
    </div>;
}

export function Pagination({ page }: { page: PageData<unknown> }) {
    return <nav aria-label="Pagination" className="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p aria-live="polite" className="text-muted-foreground">{page.total ? `Showing ${page.from}–${page.to} of ${page.total}` : 'No records'}</p>
        <div className="flex items-center gap-3">
            {page.prev_page_url ? <Button variant="outline" size="sm" asChild><Link href={page.prev_page_url} preserveScroll><ArrowLeft /> Previous</Link></Button> : <Button variant="outline" size="sm" disabled><ArrowLeft /> Previous</Button>}
            <span>{page.current_page} / {page.last_page}</span>
            {page.next_page_url ? <Button variant="outline" size="sm" asChild><Link href={page.next_page_url} preserveScroll>Next <ArrowRight /></Link></Button> : <Button variant="outline" size="sm" disabled>Next <ArrowRight /></Button>}
        </div>
    </nav>;
}

function Control({ field, value, error, onChange, id }: { field: Field; value: string | boolean; error?: string; onChange: (value: string | boolean) => void; id: string }) {
    const describedBy = [field.help ? `${id}-help` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined;
    const common = { id, 'aria-invalid': !!error, 'aria-describedby': describedBy, required: field.required, readOnly: field.readOnly, name: field.name };
    if (field.type === 'checkbox') {
        return <div>
            <Label htmlFor={id} className="flex min-h-11 cursor-pointer items-start gap-3 rounded-md px-2 py-2 font-normal leading-5 transition-colors hover:bg-muted/50">
                <Checkbox id={id} name={field.name} checked={value === true} onCheckedChange={(checked) => onChange(checked === true)} disabled={field.readOnly} aria-label={field.accessibleLabel ?? field.label} aria-invalid={!!error} aria-describedby={describedBy} className="mt-0.5 size-4 cursor-pointer border-muted-foreground/70" />
                <span className="min-w-0 flex-1">
                    <span className="font-medium">{field.label}</span>
                    {field.help && <span id={`${id}-help`} className="mt-1 block text-xs leading-relaxed text-muted-foreground">{field.help}</span>}
                </span>
            </Label>
            <InputError id={`${id}-error`} message={error} />
        </div>;
    }
    return <div className="grid gap-2">
        <Label htmlFor={id}>{field.label}{field.required && <span aria-hidden="true"> *</span>}</Label>
        {field.options ? <Select value={String(value) || undefined} onValueChange={onChange} name={field.name}>
            <SelectTrigger {...common} className="w-full cursor-pointer"><SelectValue placeholder="Choose an option" /></SelectTrigger>
            <SelectContent align="start" avoidCollisions className="w-(--radix-select-trigger-width)">{field.options.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent>
        </Select>
        : field.type === 'textarea' ? <Textarea {...common} value={String(value)} onChange={(event) => onChange(event.target.value)} className="min-h-24 resize-none" />
        : <Input {...common} type={field.type === 'money' ? 'text' : field.type ?? 'text'} inputMode={field.type === 'money' ? 'decimal' : undefined} value={String(value)} onChange={(event) => onChange(event.target.value)} />}
        {field.help && <p id={`${id}-help`} className="text-xs text-muted-foreground">{field.help}</p>}
        <InputError id={`${id}-error`} message={error} />
    </div>;
}

export function OperationsForm({ title, description, fields, initial = {}, action, trigger, saveLabel = 'Save changes', review = false, transform, fieldGroups }: {
    title: string; description: string; fields: Field[]; initial?: Record<string, string | boolean | number | null>;
    action: Mutation; trigger?: ReactNode; saveLabel?: string; review?: boolean;
    transform?: (data: Record<string, string | boolean>) => Record<string, unknown>;
    fieldGroups?: FieldGroup[];
}) {
    const id = useId();
    const initialValues = Object.fromEntries(fields.map((field) => [field.name, initial[field.name] === null ? '' : initial[field.name] !== undefined ? String(initial[field.name]) : field.defaultValue ?? (field.type === 'checkbox' ? true : '')]));
    for (const field of fields.filter((item) => item.type === 'checkbox')) {
        initialValues[field.name] = typeof initial[field.name] === 'boolean' ? Boolean(initial[field.name]) : field.defaultValue ?? true;
    }
    const form = useForm<Record<string, string | boolean>>(initialValues);
    const [open, setOpen] = useState(false);
    const [discard, setDiscard] = useState(false);
    const [reviewing, setReviewing] = useState(false);
    const [submissionId, setSubmissionId] = useState(createSubmissionId);
    const summary = useRef<HTMLDivElement>(null);
    const grouped = !!fieldGroups?.length;
    const groupedNames = new Set(fieldGroups?.flatMap((group) => group.fields) ?? []);
    const firstGroupedIndex = fields.findIndex((field) => groupedNames.has(field.name));
    const leadingFields = fields.filter((field, index) => !groupedNames.has(field.name) && index < firstGroupedIndex);
    const trailingFields = fields.filter((field, index) => !groupedNames.has(field.name) && index >= firstGroupedIndex);
    const renderControl = (field: Field) => <Control key={field.name} id={`${id}-${field.name}`} field={field} value={form.data[field.name]} error={form.errors[field.name]} onChange={(value) => form.setData(field.name, value)} />;
    const reviewValue = (field: Field) => field.type === 'checkbox' ? (form.data[field.name] ? 'Enabled' : 'Disabled') : field.options?.find((option) => option.value === form.data[field.name])?.label ?? String(form.data[field.name] || '—');
    const renderReview = (field: Field) => <div key={field.name} className="grid grid-cols-2 gap-3 text-sm"><dt className="text-muted-foreground">{field.label}</dt><dd className="break-words font-medium">{reviewValue(field)}</dd></div>;
    const footerClass = grouped ? 'shrink-0 flex-row flex-wrap justify-end gap-2 border-t bg-background px-5 py-4 sm:px-6' : undefined;
    const send = () => {
        if (form.processing) return;
        try {
            const payload = transform ? transform(form.data) : { ...form.data, submission_id: submissionId };
            form.transform(() => payload as Record<string, string | boolean>);
            form.submit(action.method, action.url, {
                preserveScroll: true,
                onSuccess: () => { setOpen(false); setReviewing(false); setSubmissionId(createSubmissionId()); form.reset(); form.clearErrors(); },
                onError: () => { setReviewing(false); requestAnimationFrame(() => summary.current?.focus()); },
            });
        } catch (error) {
            form.setError('events', error instanceof Error ? error.message : 'Check the entered data.');
            setReviewing(false);
        }
    };
    const close = () => { setOpen(false); setDiscard(false); setReviewing(false); form.reset(); form.clearErrors(); };
    return <Dialog open={open} onOpenChange={(next) => {
        if (form.processing) return;
        if (!next && form.isDirty) setDiscard(true);
        else { setOpen(next); setDiscard(false); setReviewing(false); }
    }}>
        <DialogTrigger asChild>{trigger ?? <Button className="cursor-pointer"><Plus /> {title}</Button>}</DialogTrigger>
        <DialogContent className={grouped ? "flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl" : "max-h-[90dvh] overflow-y-auto sm:max-w-lg"}>
            <DialogHeader className={grouped ? "shrink-0 border-b px-5 py-5 pr-12 text-left sm:px-6" : undefined}><DialogTitle>{discard ? 'Discard unsaved changes?' : reviewing ? `Review: ${title}` : title}</DialogTitle>
                <DialogDescription>{discard ? 'Your changes have not been saved.' : description}</DialogDescription></DialogHeader>
            {discard ? <DialogFooter className={footerClass}><Button autoFocus variant="outline" onClick={() => setDiscard(false)}>Keep editing</Button><Button variant="destructive" onClick={close}>Discard changes</Button></DialogFooter>
            : reviewing ? <>
                <div className={grouped ? "min-h-0 overflow-y-auto px-5 py-5 sm:px-6" : "contents"}>
                    {grouped ? <div className="grid gap-5">
                        <dl className="grid gap-3">{leadingFields.map(renderReview)}</dl>
                        <div className="grid gap-3 sm:grid-cols-2">{fieldGroups?.map((group) => <section key={group.title} className="rounded-lg border p-4">
                            <h3 className="mb-3 text-sm font-semibold">{group.title}</h3>
                            <dl className="grid gap-2">{fields.filter((field) => group.fields.includes(field.name)).map(renderReview)}</dl>
                        </section>)}</div>
                        <dl className="grid gap-3">{trailingFields.map(renderReview)}</dl>
                    </div> : <dl className="grid gap-3 rounded-md border p-4 text-sm">{fields.map(renderReview)}</dl>}
                </div>
                <DialogFooter className={footerClass}><Button variant="outline" onClick={() => setReviewing(false)} disabled={form.processing}>Back</Button><Button onClick={send} disabled={form.processing} aria-busy={form.processing}>{saveLabel}</Button></DialogFooter>
            </> : <form noValidate className={grouped ? "flex min-h-0 flex-1 flex-col overflow-hidden" : "grid gap-5"} onSubmit={(event) => {
                event.preventDefault();
                if (review || fields.some((field) => field.type === 'checkbox' && initial[field.name] === true && form.data[field.name] === false)) setReviewing(true);
                else send();
            }}>
                <div className={grouped ? "min-h-0 overflow-y-auto px-5 py-5 sm:px-6" : "contents"}>
                    <div className={grouped ? "grid gap-5" : "contents"}>
                        {Object.keys(form.errors).length > 0 && <div ref={summary} tabIndex={-1} role="alert" className="rounded-md border border-destructive p-3 text-sm"><p className="font-medium">Check these details:</p><ul className="mt-2 list-disc pl-5">{Object.entries(form.errors).map(([key, message]) => <li key={key}>{message}</li>)}</ul></div>}
                        {grouped ? <>
                            {leadingFields.map(renderControl)}
                            <div className="grid gap-3 sm:grid-cols-2">{fieldGroups?.map((group) => <fieldset key={group.title} className="min-w-0 rounded-lg border px-3 pb-2">
                                <legend className="px-1 text-sm font-semibold">{group.title}</legend>
                                <div className="pt-1">{fields.filter((field) => group.fields.includes(field.name)).map(renderControl)}</div>
                            </fieldset>)}</div>
                            {trailingFields.map(renderControl)}
                        </> : fields.map(renderControl)}
                    </div>
                </div>
                <DialogFooter className={footerClass}><Button type="button" variant="outline" disabled={form.processing} onClick={() => form.isDirty ? setDiscard(true) : close()}>Cancel</Button><Button type="submit" disabled={form.processing} aria-busy={form.processing}>{review ? 'Review details' : saveLabel}</Button></DialogFooter>

            </form>}
        </DialogContent>
    </Dialog>;
}

export function Filters({ action, fields, initial }: { action: string; fields: Field[]; initial: Record<string, string | number | null | undefined> }) {
    const id = useId();
    const form = useForm<Record<string, string>>(Object.fromEntries(fields.map((field) => [field.name, String(initial[field.name] ?? '')])));
    return <form noValidate onSubmit={(event) => { event.preventDefault(); form.get(action, { preserveState: true, preserveScroll: true }); }} className="flex flex-wrap items-end gap-4 rounded-lg bg-muted/30 p-4">
        {fields.map((field) => <div key={field.name} className="w-full sm:w-52"><Control id={`${id}-${field.name}`} field={field} value={form.data[field.name]} error={form.errors[field.name]} onChange={(value) => form.setData(field.name, String(value))} /></div>)}
        <Button type="submit" variant="outline" disabled={form.processing}><Search /> Apply filters</Button>
        <Button type="button" variant="ghost" onClick={() => { form.setData(Object.fromEntries(fields.map((field) => [field.name, '']))); router.get(action, {}, { preserveScroll: true }); }}>Clear filters</Button>
    </form>;
}
