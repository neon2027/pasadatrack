import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight, ChevronDown, FileText } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import MarketingFooter from '@/components/marketing-footer';
import { home, privacy, terms } from '@/routes';

export type LegalSection = {
    id: string;
    title: string;
    content: ReactNode;
};

type LegalPageProps = {
    kind: 'terms' | 'privacy';
    title: string;
    description: string;
    sections: LegalSection[];
};

const focus =
    'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pt-teal)]';

function Contents({ sections }: { sections: LegalSection[] }) {
    return (
        <nav aria-label="On this page" className="flex flex-col gap-1">
            {sections.map((section) => (
                <a
                    key={section.id}
                    href={`#${section.id}`}
                    className={`flex min-h-11 cursor-pointer items-center rounded-lg px-3 py-2 text-sm leading-6 text-[var(--pt-muted)] hover:bg-[var(--pt-cloud)] hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] motion-safe:transition-colors ${focus}`}
                >
                    {section.title}
                </a>
            ))}
        </nav>
    );
}

export default function LegalPage({
    kind,
    title,
    description,
    sections,
}: LegalPageProps) {
    return (
        <div className="marketing-page legal-page min-h-screen bg-[var(--pt-paper)] text-[var(--pt-ink)] [&_section[id]]:scroll-mt-28">
            <Head title={title}>
                <meta name="description" content={description} />
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <a
                href="#legal-content"
                className={`sr-only fixed top-3 left-3 z-50 rounded-lg bg-white p-3 focus:not-sr-only ${focus}`}
            >
                Skip to content
            </a>
            <header className="sticky top-0 z-30 border-b border-[var(--pt-line)] bg-[var(--pt-paper)]/95 backdrop-blur-xl">
                <div className="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-x-4 gap-y-3 px-6 py-5 sm:gap-x-6 sm:px-10 lg:px-16">
                    <Link
                        href={home()}
                        aria-label="PasadaTrack home"
                        className={`cursor-pointer rounded-md hover:opacity-80 active:opacity-70 ${focus}`}
                    >
                        <AppLogo className="h-9 w-44 sm:h-10 sm:w-52 dark:bg-transparent" />
                    </Link>
                    <Link
                        href={home()}
                        title="Back to home"
                        className={`inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center gap-2 rounded-full border border-[var(--pt-line)] px-3 text-sm font-medium hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] sm:rounded-md sm:border-0 sm:px-0 ${focus}`}
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        <span className="sr-only sm:not-sr-only">
                            Back to home
                        </span>
                    </Link>
                </div>
            </header>
            <main id="legal-content">
                <div className="mx-auto max-w-[1280px] px-6 pt-16 pb-14 sm:px-10 sm:pt-24 lg:px-16 lg:pt-28 lg:pb-20">
                    <p className="mb-7 text-xs font-semibold tracking-[0.16em] text-[var(--pt-teal)] uppercase">
                        Clarity for the road ahead
                    </p>
                    <h1 className="max-w-4xl text-[clamp(2.75rem,6vw,5.5rem)] leading-[1.08] font-medium tracking-[-0.055em]">
                        {title}
                    </h1>
                    <p className="mt-7 max-w-xl text-lg leading-8 text-[var(--pt-muted)]">
                        {description}
                    </p>
                    <div className="mt-8 flex flex-wrap items-center gap-x-4 gap-y-3 text-xs leading-6 text-[var(--pt-muted)]">
                        <span className="rounded-full bg-[var(--pt-cloud)] px-3 py-1 font-medium text-[var(--pt-teal)]">
                            Draft for review
                        </span>
                        <span>
                            Draft updated{' '}
                            <time dateTime="2026-10-11">October 11, 2026</time>
                        </span>
                    </div>
                    <nav
                        aria-label="Legal pages"
                        className="mt-12 flex flex-wrap gap-x-7 gap-y-3 border-b border-[var(--pt-line)] pb-5"
                    >
                        {[
                            {
                                kind: 'terms',
                                label: 'Terms & conditions',
                                href: terms(),
                            },
                            {
                                kind: 'privacy',
                                label: 'Privacy policy',
                                href: privacy(),
                            },
                        ].map((page) => (
                            <Link
                                key={page.kind}
                                href={page.href}
                                aria-current={
                                    kind === page.kind ? 'page' : undefined
                                }
                                className={`inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md text-sm font-medium hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] ${kind === page.kind ? 'text-[var(--pt-teal)] underline decoration-2 underline-offset-8' : 'text-[var(--pt-muted)]'} ${focus}`}
                            >
                                {page.label}
                                {kind !== page.kind && (
                                    <ArrowUpRight
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                )}
                            </Link>
                        ))}
                    </nav>
                </div>
                <div className="mx-auto grid max-w-[1280px] gap-12 px-6 pb-24 sm:px-10 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-20 lg:px-16 lg:pb-32">
                    <aside>
                        <div className="sticky top-32 hidden lg:block">
                            <p className="mb-5 px-3 text-xs font-semibold tracking-[0.12em] text-[var(--pt-muted)] uppercase">
                                On this page
                            </p>
                            <Contents sections={sections} />
                        </div>
                        <details className="group rounded-2xl border border-[var(--pt-line)] p-5 lg:hidden">
                            <summary
                                className={`flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 rounded-md text-sm font-medium hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] [&::-webkit-details-marker]:hidden ${focus}`}
                            >
                                On this page
                                <ChevronDown
                                    className="size-4 group-open:rotate-180 motion-safe:transition-transform"
                                    aria-hidden="true"
                                />
                            </summary>
                            <div className="pt-4">
                                <Contents sections={sections} />
                            </div>
                        </details>
                    </aside>
                    <div className="max-w-[720px] min-w-0">
                        <div
                            role="note"
                            className="mb-14 flex gap-4 rounded-2xl bg-[var(--pt-cloud)] p-6 sm:gap-5 sm:p-8"
                        >
                            <FileText
                                className="mt-1 size-5 shrink-0 text-[var(--pt-teal)]"
                                aria-hidden="true"
                            />
                            <div className="flex flex-col gap-3">
                                <p className="font-medium">
                                    This document is a draft.
                                </p>
                                <p className="text-sm leading-7 text-[var(--pt-muted)]">
                                    The legal operator and contact details are
                                    not yet finalized. The content below is
                                    prepared for review and is not a finalized
                                    legal agreement or privacy notice.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-col gap-12 sm:gap-16">
                            {sections.map((section) => (
                                <section
                                    key={section.id}
                                    id={section.id}
                                    aria-labelledby={`${section.id}-title`}
                                    className="border-t border-[var(--pt-line)] pt-8 sm:pt-10"
                                >
                                    <h2
                                        id={`${section.id}-title`}
                                        className="text-2xl leading-snug font-medium tracking-tight sm:text-3xl"
                                    >
                                        {section.title}
                                    </h2>
                                    <div className="mt-6 flex flex-col gap-5 text-base leading-8 text-[var(--pt-muted)] [&_a]:rounded-sm [&_a]:font-medium [&_a]:text-[var(--pt-teal)] [&_a]:underline [&_a]:underline-offset-4 [&_a]:hover:text-[var(--pt-ink)] [&_a]:focus-visible:outline-2 [&_a]:focus-visible:outline-offset-4 [&_a]:focus-visible:outline-[var(--pt-teal)] [&_li]:pl-1 [&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-3 [&_ul]:pl-5">
                                        {section.content}
                                    </div>
                                </section>
                            ))}
                        </div>
                    </div>
                </div>
            </main>
            <div className="border-t border-[var(--pt-line)]">
                <MarketingFooter />
            </div>
        </div>
    );
}
