import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowRight,
    BusFront,
    CalendarDays,
    Check,
    ChevronDown,
    ClipboardCheck,
    FileChartColumn,
    Menu,
    Receipt,
    ShieldCheck,
    UsersRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/app-logo';
import JeepneyHero from '@/components/jeepney-hero';
import MarketingFooter from '@/components/marketing-footer';
import { useWelcomeMotion } from '@/hooks/use-welcome-motion';
import { dashboard, home, login, register } from '@/routes';
import { index as teamsIndex } from '@/routes/teams';

const features = [
    {
        icon: UsersRound,
        title: 'Your people. Your fleet.',
        description:
            'Drivers, jeepneys, routes, and boundary rates. Together in one place, with their history intact.',
    },
    {
        icon: CalendarDays,
        title: 'A plan for every shift.',
        description:
            'Assign the right driver and jeepney. Catch schedule overlaps before the day begins.',
    },
    {
        icon: Receipt,
        title: 'Every payment, accounted for.',
        description:
            'Record collections, follow partial payments, and know exactly which balances remain.',
    },
    {
        icon: ClipboardCheck,
        title: 'Attendance without the guesswork.',
        description:
            'Keep time-in and time-out records clear. Review corrections and import supported biometric records.',
    },
    {
        icon: FileChartColumn,
        title: 'See the day clearly.',
        description:
            'Review shifts, collections, and balances by driver or date. Export when it’s time to reconcile.',
    },
    {
        icon: ShieldCheck,
        title: 'A history you can trust.',
        description:
            'Know what changed, who changed it, and why. Each team keeps its own operational workspace.',
    },
];

const workflow = [
    {
        title: 'Plan the day',
        detail: 'Set up routes and assign your drivers and jeepneys.',
    },
    {
        title: 'Record the shift',
        detail: 'Log attendance and confirm the driver who took the trip.',
    },
    {
        title: 'Close the books',
        detail: 'Record collections and review remaining boundary balances.',
    },
];

const questions = [
    {
        question: 'Who is PasadaTrack built for?',
        answer: 'PasadaTrack is built around the daily work of jeepney operators and transport cooperatives: organizing drivers, assigning vehicles, recording attendance, and keeping boundary collections clear.',
    },
    {
        question: 'Does the fleet map use live GPS?',
        answer: 'The current fleet map is a demonstration using sample vehicle positions. Live GPS tracking requires a supported device connection and production integration before deployment.',
    },
    {
        question: 'Can I connect a biometric attendance device?',
        answer: 'You can register devices, map device users to drivers, and import supported attendance records. Direct synchronization with a physical terminal depends on its hardware and integration; it is not included in the current import workflow.',
    },
    {
        question: 'Can I export my operational records?',
        answer: 'Yes. Driver and date filters help you review your operations, and reports can be exported as CSV or an Excel-compatible XML file.',
    },
];

const focus =
    'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pt-teal)]';
const primaryButton = `group inline-flex min-h-14 cursor-pointer items-center justify-center gap-5 rounded-full bg-[var(--pt-ink)] px-7 py-4 text-sm font-semibold text-white shadow-[0_8px_24px_-12px_#102d3a80] hover:bg-[var(--pt-teal)] active:bg-[var(--pt-ink)] motion-safe:transition-[background-color,box-shadow] motion-safe:duration-300 [&_svg]:motion-safe:transition-transform [&_svg]:motion-safe:duration-300 [&_svg]:motion-safe:group-hover:translate-x-1 ${focus}`;

function Brand() {
    return <AppLogo className="h-9 w-44 sm:h-10 sm:w-52 dark:bg-transparent" />;
}

export default function Welcome() {
    const { auth, currentTeam } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const pageRef = useWelcomeMotion();
    const workspaceUrl = currentTeam
        ? dashboard(currentTeam.slug)
        : teamsIndex();
    const actionUrl = auth.user ? workspaceUrl : register();
    const actionLabel = auth.user ? 'Open workspace' : 'Create an account';

    return (
        <>
            <Head title="Jeepney fleet & boundary management">
                <meta
                    name="description"
                    content="Organize your jeepney fleet, schedule drivers, record attendance, and manage boundary collections with PasadaTrack. One workspace for your daily operations."
                />
            </Head>
            <div
                ref={pageRef}
                className="marketing-page welcome-page min-h-screen overflow-x-clip bg-[var(--pt-paper)] text-[var(--pt-ink)] [&_section[id]]:scroll-mt-28"
            >
                <a
                    href="#main-content"
                    className={`sr-only fixed top-3 left-3 z-50 rounded-lg bg-white p-3 focus:not-sr-only ${focus}`}
                >
                    Skip to content
                </a>
                <header className="sticky top-0 z-30 border-b border-[var(--pt-line)] bg-[var(--pt-paper)]/95 backdrop-blur-xl">
                    <div
                        className="absolute inset-x-0 top-0 h-0.5 origin-left scale-x-[var(--pt-scroll-progress,0)] bg-[var(--pt-teal)]"
                        aria-hidden="true"
                    />
                    <div className="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-4 px-6 py-5 sm:px-10 lg:px-16">
                        <Link
                            href={home()}
                            aria-label="PasadaTrack home"
                            className={`cursor-pointer rounded-md hover:opacity-80 active:opacity-70 ${focus}`}
                        >
                            <Brand />
                        </Link>
                        <button
                            type="button"
                            aria-label={
                                menuOpen
                                    ? 'Close navigation'
                                    : 'Open navigation'
                            }
                            aria-expanded={menuOpen}
                            aria-controls="marketing-navigation"
                            onClick={() => setMenuOpen(!menuOpen)}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') {
                                    setMenuOpen(false);
                                }
                            }}
                            className={`flex size-11 cursor-pointer items-center justify-center rounded-full border border-[var(--pt-line)] hover:bg-[var(--pt-cloud)] active:bg-[var(--pt-line)] md:hidden ${focus}`}
                        >
                            {menuOpen ? (
                                <X className="size-5" aria-hidden="true" />
                            ) : (
                                <Menu className="size-5" aria-hidden="true" />
                            )}
                        </button>
                        <nav
                            id="marketing-navigation"
                            aria-label="Main navigation"
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') {
                                    setMenuOpen(false);
                                    event.currentTarget.parentElement
                                        ?.querySelector<HTMLButtonElement>(
                                            'button[aria-controls]',
                                        )
                                        ?.focus();
                                }
                            }}
                            className={`${menuOpen ? 'flex' : 'hidden'} w-full flex-col gap-2 pb-3 md:flex md:w-auto md:flex-row md:items-center md:gap-7 md:pb-0 lg:gap-9`}
                        >
                            {[
                                ['Features', '#features'],
                                ['How it works', '#how-it-works'],
                                ['Pricing', '#pricing'],
                            ].map(([label, href]) => (
                                <a
                                    key={href}
                                    href={href}
                                    onClick={() => setMenuOpen(false)}
                                    className={`cursor-pointer rounded-md py-3 text-sm font-medium text-[var(--pt-muted)] hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] motion-safe:transition-colors ${focus}`}
                                >
                                    {label}
                                </a>
                            ))}
                            {!auth.user && (
                                <Link
                                    href={login()}
                                    onClick={() => setMenuOpen(false)}
                                    className={`cursor-pointer rounded-md py-3 text-sm font-semibold hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] ${focus}`}
                                >
                                    Log in
                                </Link>
                            )}
                            <Link
                                href={actionUrl}
                                onClick={() => setMenuOpen(false)}
                                className={`inline-flex min-h-12 cursor-pointer items-center justify-center gap-3 rounded-full bg-[var(--pt-ink)] px-6 py-3 text-sm font-semibold text-white hover:bg-[var(--pt-teal)] active:bg-[var(--pt-ink)] motion-safe:transition-colors ${focus}`}
                            >
                                {auth.user ? 'Dashboard' : 'Get started'}
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </nav>
                    </div>
                </header>
                <main id="main-content">
                    <section
                        className="relative mx-auto grid max-w-[1440px] items-center gap-14 px-6 pt-20 pb-12 sm:px-10 sm:pt-24 lg:min-h-[760px] lg:grid-cols-[1.08fr_1fr] lg:gap-12 lg:px-16 lg:py-28 xl:min-h-[820px]"
                        aria-labelledby="hero-title"
                    >
                        <div data-reveal>
                            <p className="mb-9 flex items-center gap-3 text-[11px] font-semibold tracking-[0.18em] text-[var(--pt-teal)] uppercase sm:text-xs">
                                <span
                                    className="size-2 rounded-full bg-[var(--pt-teal)] ring-4 ring-[var(--pt-teal)]/10"
                                    aria-hidden="true"
                                />
                                A better way to run your fleet
                            </p>
                            <h1
                                id="hero-title"
                                className="max-w-2xl text-[clamp(3.5rem,6.1vw,6rem)] leading-[1.06] font-medium tracking-[-0.065em]"
                            >
                                A clear day.
                                <br />
                                <span className="text-[var(--pt-teal)]">
                                    A better pasada.
                                </span>
                            </h1>
                            <p className="mt-9 max-w-[390px] text-lg leading-8 text-[var(--pt-muted)]">
                                Your drivers, shifts, and collections. One calm,
                                organized workspace for the road ahead.
                            </p>
                            <div className="mt-10 flex flex-wrap items-center gap-x-7 gap-y-4">
                                <Link
                                    href={actionUrl}
                                    className={primaryButton}
                                >
                                    {actionLabel}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <a
                                    href="#features"
                                    className={`inline-flex min-h-12 cursor-pointer items-center gap-3 rounded-md text-sm font-medium hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] ${focus}`}
                                >
                                    Take a closer look
                                    <ArrowDown
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                            </div>
                            <p className="mt-10 max-w-xs text-xs leading-6 text-[var(--pt-muted)]">
                                For jeepney operators and transport
                                cooperatives.
                            </p>
                        </div>
                        <div
                            data-reveal
                            data-reveal-delay="140"
                            className="relative min-w-0 lg:-mr-6"
                        >
                            <div
                                className="absolute inset-x-4 top-[10%] bottom-[18%] rounded-[50%] bg-[var(--pt-cloud)]/70"
                                aria-hidden="true"
                            />
                            <JeepneyHero />
                            <div className="relative mx-4 mt-4 flex items-center gap-4 border-t border-[var(--pt-line)] pt-6 text-[11px] font-medium tracking-wide text-[var(--pt-muted)] sm:mx-8">
                                <span>First dispatch</span>
                                <span
                                    className="relative h-px flex-1 bg-[var(--pt-line)]"
                                    aria-hidden="true"
                                >
                                    <span className="absolute -top-1 left-0 size-2 rounded-full bg-[var(--pt-teal)]" />
                                    <span className="absolute -top-1 right-0 size-2 rounded-full bg-[var(--pt-yellow)]" />
                                </span>
                                <span>Last collection</span>
                            </div>
                        </div>
                    </section>

                    <div className="border-y border-[var(--pt-line)]">
                        <div className="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-x-12 gap-y-7 px-6 py-9 sm:px-10 lg:px-16">
                            <p className="text-sm font-medium">
                                The whole day. All connected.
                            </p>
                            <div className="grid grid-cols-2 gap-x-8 gap-y-5 text-xs font-medium text-[var(--pt-muted)] sm:flex sm:flex-wrap sm:gap-x-10">
                                {[
                                    { icon: BusFront, label: 'Fleet & routes' },
                                    {
                                        icon: CalendarDays,
                                        label: 'Driver schedules',
                                    },
                                    {
                                        icon: Receipt,
                                        label: 'Boundary collections',
                                    },
                                    {
                                        icon: ClipboardCheck,
                                        label: 'Attendance',
                                    },
                                ].map(({ icon: Icon, label }) => (
                                    <span
                                        key={label}
                                        className="flex items-center gap-2"
                                    >
                                        <Icon
                                            className="size-4 text-[var(--pt-teal)]"
                                            aria-hidden="true"
                                        />
                                        {label}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>

                    <section
                        id="features"
                        aria-labelledby="features-title"
                        className="mx-auto max-w-[1440px] px-6 py-24 sm:px-10 sm:py-32 lg:px-16 lg:py-40"
                    >
                        <div
                            data-reveal
                            className="mb-16 flex flex-col justify-between gap-8 md:flex-row md:items-end lg:mb-24"
                        >
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-[var(--pt-teal)] uppercase">
                                    Room to focus on what matters
                                </p>
                                <h2
                                    id="features-title"
                                    className="mt-6 max-w-2xl text-4xl leading-[1.12] font-medium tracking-[-0.045em] sm:text-5xl lg:text-[3.5rem]"
                                >
                                    Less paperwork.
                                    <br />
                                    More perspective.
                                </h2>
                            </div>
                            <p className="max-w-xs text-base leading-8 text-[var(--pt-muted)]">
                                The everyday essentials, thoughtfully connected.
                                So you can spend less time piecing things
                                together.
                            </p>
                        </div>
                        <div className="grid gap-x-14 gap-y-12 md:grid-cols-2 lg:gap-x-24 lg:gap-y-16">
                            {features.map(
                                ({ icon: Icon, title, description }, index) => (
                                    <article
                                        key={title}
                                        data-reveal
                                        data-reveal-delay={
                                            index % 2 === 0 ? 0 : 100
                                        }
                                        className="flex flex-col gap-6 border-t border-[var(--pt-line)] pt-8 sm:flex-row sm:gap-7 sm:pt-10"
                                    >
                                        <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[var(--pt-cloud)] text-[var(--pt-teal)] sm:size-14">
                                            <Icon
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <div className="flex flex-col gap-4">
                                            <h3 className="text-xl leading-snug font-medium tracking-tight sm:text-2xl">
                                                {title}
                                            </h3>
                                            <p className="max-w-sm text-base leading-8 text-[var(--pt-muted)]">
                                                {description}
                                            </p>
                                        </div>
                                    </article>
                                ),
                            )}
                        </div>
                    </section>

                    <section
                        id="how-it-works"
                        aria-labelledby="workflow-title"
                        className="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-10"
                    >
                        <div className="relative grid gap-16 overflow-hidden rounded-[2rem] bg-[var(--pt-ink)] px-7 py-16 text-white sm:rounded-[3rem] sm:px-12 sm:py-24 lg:grid-cols-[1fr_1.05fr] lg:gap-24 lg:px-20 lg:py-28">
                            <div data-reveal>
                                <p className="text-xs font-semibold tracking-[0.16em] text-[var(--pt-yellow)] uppercase">
                                    From dispatch to collection
                                </p>
                                <h2
                                    id="workflow-title"
                                    className="mt-7 text-4xl leading-[1.15] font-medium tracking-[-0.045em] sm:text-5xl lg:text-[3.5rem]"
                                >
                                    Find your rhythm.
                                    <br />
                                    Every day.
                                </h2>
                                <p className="mt-8 max-w-xs text-base leading-8 text-slate-300">
                                    From the first trip to the final collection,
                                    a simple routine that follows the way you
                                    work.
                                </p>
                                <a
                                    href="#pricing"
                                    className={`mt-10 inline-flex min-h-12 cursor-pointer items-center gap-4 rounded-md text-sm font-medium text-[var(--pt-yellow)] hover:text-white focus-visible:outline-white active:text-[var(--pt-yellow)] ${focus}`}
                                >
                                    Explore the workspace
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                            </div>
                            <ol className="flex flex-col">
                                {workflow.map((step, index) => (
                                    <li
                                        key={step.title}
                                        data-reveal
                                        data-reveal-delay={index * 80}
                                        className="relative flex gap-6 pb-12 last:pb-0 sm:gap-8 sm:pb-14"
                                    >
                                        {index < workflow.length - 1 && (
                                            <span
                                                className="absolute top-14 bottom-0 left-6 w-px bg-white/15 sm:left-7"
                                                aria-hidden="true"
                                            />
                                        )}
                                        <span className="relative flex size-12 shrink-0 items-center justify-center rounded-full border border-white/20 bg-[var(--pt-ink)] font-mono text-xs text-[var(--pt-yellow)] sm:size-14">
                                            0{index + 1}
                                        </span>
                                        <div className="pt-1">
                                            <h3 className="text-2xl font-medium tracking-tight">
                                                {step.title}
                                            </h3>
                                            <p className="mt-4 max-w-xs text-base leading-8 text-slate-300">
                                                {step.detail}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section
                        id="pricing"
                        aria-labelledby="pricing-title"
                        className="mx-auto grid max-w-[1440px] gap-14 px-6 py-24 sm:px-10 sm:py-32 lg:grid-cols-2 lg:items-center lg:gap-24 lg:px-16 lg:py-40"
                    >
                        <div data-reveal>
                            <p className="text-xs font-semibold tracking-[0.16em] text-[var(--pt-teal)] uppercase">
                                Pricing
                            </p>
                            <h2
                                id="pricing-title"
                                className="mt-6 text-4xl leading-[1.15] font-medium tracking-[-0.045em] sm:text-5xl lg:text-[3.5rem]"
                            >
                                Your fleet.
                                <br />
                                Your next chapter.
                            </h2>
                            <p className="mt-8 max-w-sm text-base leading-8 text-[var(--pt-muted)]">
                                A few jeepneys or a growing cooperative. Make
                                space for a more organized operating day.
                            </p>
                            <div className="mt-10 max-w-sm border-l-2 border-[var(--pt-teal)]/30 pl-5">
                                <p className="text-sm leading-7 text-[var(--pt-muted)]">
                                    Every operation is different. Let’s discuss
                                    your fleet, your requirements, and a price
                                    that works for you.
                                </p>
                            </div>
                        </div>
                        <div
                            data-reveal
                            data-reveal-delay="100"
                            className="overflow-hidden rounded-[2rem] border border-[var(--pt-line)] bg-white p-7 shadow-[0_24px_80px_-40px_#102d3a35] sm:p-12"
                        >
                            <div className="flex items-center justify-between gap-4 border-b border-[var(--pt-line)] pb-8">
                                <span className="text-sm font-medium">
                                    One connected workspace
                                </span>
                                <BusFront
                                    className="size-6 text-[var(--pt-teal)]"
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="pt-9">
                                <p className="text-3xl font-medium tracking-[-0.035em] sm:text-4xl">
                                    Let’s talk about your fleet.
                                </p>
                                <p className="mt-4 text-base leading-8 text-[var(--pt-muted)]">
                                    Contact me by email to discuss your needs
                                    and negotiate pricing for your operation.
                                </p>
                                <ul className="mt-9 flex flex-col gap-6 text-sm leading-6">
                                    {[
                                        'Driver, jeepney, and route records',
                                        'Shift scheduling and driving confirmation',
                                        'Boundary payments and balance tracking',
                                        'Attendance records and supported imports',
                                        'Operational reports and audit history',
                                    ].map((item) => (
                                        <li key={item} className="flex gap-3">
                                            <Check
                                                className="mt-0.5 size-4 shrink-0 text-[var(--pt-teal)]"
                                                aria-hidden="true"
                                            />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                                <a
                                    href="mailto:lustanexequiel@gmail.com?subject=PasadaTrack%20pricing%20inquiry"
                                    className={`mt-10 w-full ${primaryButton}`}
                                >
                                    Discuss pricing
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                                <a
                                    href="mailto:lustanexequiel@gmail.com?subject=PasadaTrack%20pricing%20inquiry"
                                    className={`mt-5 flex min-h-11 cursor-pointer items-center justify-center rounded-md text-center text-sm text-[var(--pt-teal)] underline-offset-4 hover:underline active:text-[var(--pt-ink)] ${focus}`}
                                >
                                    lustanexequiel@gmail.com
                                </a>
                                <p className="mt-6 text-center text-xs leading-6 text-[var(--pt-muted)]">
                                    Live GPS and terminal connectivity require
                                    compatible hardware and setup.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        aria-labelledby="faq-title"
                        className="border-t border-[var(--pt-line)]"
                    >
                        <div className="mx-auto grid max-w-[1440px] gap-14 px-6 py-24 sm:px-10 sm:py-32 lg:grid-cols-[0.85fr_1.15fr] lg:gap-24 lg:px-16">
                            <div data-reveal>
                                <p className="text-xs font-semibold tracking-[0.16em] text-[var(--pt-teal)] uppercase">
                                    Good to know
                                </p>
                                <h2
                                    id="faq-title"
                                    className="mt-6 max-w-sm text-4xl leading-[1.15] font-medium tracking-[-0.045em] sm:text-5xl"
                                >
                                    A little more clarity.
                                </h2>
                            </div>
                            <div className="divide-y divide-[var(--pt-line)] border-y border-[var(--pt-line)]">
                                {questions.map(({ question, answer }) => (
                                    <details
                                        key={question}
                                        data-reveal
                                        className="group py-6 sm:py-7"
                                    >
                                        <summary
                                            className={`flex min-h-11 cursor-pointer list-none items-center justify-between gap-6 rounded-md text-base leading-7 font-medium hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] [&::-webkit-details-marker]:hidden ${focus}`}
                                        >
                                            {question}
                                            <ChevronDown
                                                className="size-5 shrink-0 text-[var(--pt-teal)] group-open:rotate-180 motion-safe:transition-transform motion-safe:duration-300"
                                                aria-hidden="true"
                                            />
                                        </summary>
                                        <p className="max-w-lg pt-5 pr-8 text-base leading-8 text-[var(--pt-muted)]">
                                            {answer}
                                        </p>
                                    </details>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        aria-labelledby="cta-title"
                        className="px-4 pb-6 sm:px-6 lg:px-10"
                    >
                        <div className="mx-auto flex max-w-[1360px] flex-col items-center gap-10 rounded-[2rem] bg-[var(--pt-cloud)] px-6 py-20 text-center sm:rounded-[3rem] sm:px-12 sm:py-28 lg:py-32">
                            <div data-reveal>
                                <p className="mb-8 text-xs font-semibold tracking-[0.16em] text-[var(--pt-teal)] uppercase">
                                    For the road ahead
                                </p>
                                <h2
                                    id="cta-title"
                                    className="max-w-3xl text-[clamp(2.5rem,5.5vw,5rem)] leading-[1.1] font-medium tracking-[-0.055em]"
                                >
                                    Your next pasada.
                                    <br />
                                    <span className="text-[var(--pt-teal)]">
                                        A little more effortless.
                                    </span>
                                </h2>
                                <p className="mx-auto mt-7 max-w-sm text-base leading-8 text-[var(--pt-muted)]">
                                    Bring your drivers, shifts, and collections
                                    into one place.
                                </p>
                            </div>
                            <Link
                                href={actionUrl}
                                data-reveal
                                data-reveal-delay="100"
                                className={`shrink-0 ${primaryButton}`}
                            >
                                {actionLabel}
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                    </section>
                </main>
                <MarketingFooter>
                    <a
                        href="#pricing"
                        className={`inline-flex min-h-11 w-fit cursor-pointer items-center rounded-md py-2 text-xs font-medium hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] ${focus}`}
                    >
                        Features & pricing
                        <ArrowRight
                            className="ml-2 inline size-3"
                            aria-hidden="true"
                        />
                    </a>
                </MarketingFooter>
            </div>
        </>
    );
}
