import { Link, router, useHttp, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    BusFront,
    CalendarDays,
    Check,
    CheckCheck,
    Fingerprint,
    Lightbulb,
    Map,
    Route,
    UsersRound,
    Wallet,
} from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { getOnboardingSteps } from '@/lib/onboarding';
import type { OnboardingDestination } from '@/lib/onboarding';
import { dashboard } from '@/routes';
import { index as schedulingIndex } from '@/routes/assignments';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as collectionsIndex } from '@/routes/collections';
import { index as driversIndex } from '@/routes/drivers';
import { dismiss } from '@/routes/onboarding';
import { index as reportsIndex } from '@/routes/reports';
import { index as teamsIndex } from '@/routes/teams';

type OnboardingContextValue = {
    openGuide: () => void;
    startAutomatically: () => void;
};

const OnboardingContext = createContext<OnboardingContextValue | null>(null);
const icons = {
    welcome: Route,
    workspace: UsersRound,
    fleet: BusFront,
    scheduling: CalendarDays,
    attendance: Fingerprint,
    collections: Wallet,
    reports: BookOpen,
    map: Map,
    ready: CheckCheck,
};

export function useOnboarding() {
    const context = useContext(OnboardingContext);

    if (!context) {
        throw new Error(
            'useOnboarding must be used inside OnboardingProvider.',
        );
    }

    return context;
}

export function OnboardingProvider({ children }: { children: ReactNode }) {
    const { props } = usePage();
    const userId = props.auth.user?.id;
    const team = props.currentTeam;
    const steps = getOnboardingSteps(team);
    const [open, setOpen] = useState(false);
    const [stepIndex, setStepIndex] = useState(0);
    const [dismissedUserId, setDismissedUserId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const automaticUserId = useRef<number | null>(null);
    const saving = useRef(false);
    const titleRef = useRef<HTMLHeadingElement>(null);
    const http = useHttp({});
    const needsTutorial =
        props.onboarding.needsTutorial && dismissedUserId !== userId;
    const hasInvitations =
        Array.isArray(props.pendingInvitations) &&
        props.pendingInvitations.length > 0;
    const currentIndex = Math.min(stepIndex, steps.length - 1);
    const step = steps[currentIndex];
    const Icon = icons[step.id as keyof typeof icons];
    const isLastStep = currentIndex === steps.length - 1;

    const openGuide = useCallback(() => {
        setStepIndex(0);
        setError(null);
        setOpen(true);
    }, []);

    const startAutomatically = useCallback(() => {
        if (userId && needsTutorial && automaticUserId.current !== userId) {
            automaticUserId.current = userId;
            openGuide();
        }
    }, [userId, needsTutorial, openGuide]);

    useEffect(() => {
        if (!hasInvitations) {
            startAutomatically();
        }
    }, [hasInvitations, startAutomatically]);

    useEffect(() => {
        if (open) {
            titleRef.current?.focus();
        }
    }, [open, currentIndex]);

    async function closeGuide(): Promise<boolean> {
        if (saving.current) {
            return false;
        }

        if (!needsTutorial) {
            setOpen(false);
            return true;
        }

        saving.current = true;
        setError(null);

        try {
            await http.post(dismiss.url());
            setDismissedUserId(userId ?? null);
            setOpen(false);
            return true;
        } catch {
            setError(
                'Your tutorial preference could not be saved. Try again, or close for now and revisit the guide later.',
            );
            return false;
        } finally {
            saving.current = false;
        }
    }

    function destinationRoute(destination: OnboardingDestination) {
        if (destination === 'teams' || !team) {
            return teamsIndex();
        }

        switch (destination) {
            case 'fleet':
                return dashboard(team.slug);
            case 'drivers':
                return driversIndex(team.slug);
            case 'scheduling':
                return schedulingIndex(team.slug);
            case 'collections':
                return collectionsIndex(team.slug);
            case 'attendance':
                return attendanceIndex(team.slug);
            case 'reports':
                return reportsIndex(team.slug);
        }
    }

    const destination = step.destination
        ? destinationRoute(step.destination)
        : null;

    return (
        <OnboardingContext.Provider value={{ openGuide, startAutomatically }}>
            {children}
            <Dialog
                open={open}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        void closeGuide();
                    }
                }}
            >
                <DialogContent
                    data-test="onboarding-tutorial"
                    className="flex max-h-[calc(100dvh-2rem)] max-w-[calc(100%-2rem)] flex-col gap-0 overflow-hidden rounded-2xl p-0 motion-reduce:animate-none sm:max-w-3xl [&>button]:flex [&>button]:size-11 [&>button]:cursor-pointer [&>button]:items-center [&>button]:justify-center [&>button]:rounded-full"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        titleRef.current?.focus();
                    }}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        document.getElementById('onboarding-trigger')?.focus();
                    }}
                >
                    <div className="flex items-center gap-3 border-b px-6 py-5 pr-16 sm:px-8 sm:pr-16">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-teal-800 text-white dark:bg-teal-200 dark:text-teal-950">
                            <Route className="size-4" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold">
                                Your workspace guide
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {team?.name ?? 'PasadaTrack'}
                            </p>
                        </div>
                    </div>
                    <div className="grid min-h-0 flex-1 overflow-y-auto sm:grid-cols-[190px_minmax(0,1fr)]">
                        <nav
                            aria-label="Tutorial steps"
                            className="hidden border-r bg-muted/35 p-4 sm:block"
                        >
                            <ol className="flex flex-col gap-1">
                                {steps.map((item, index) => (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            disabled={http.processing}
                                            onClick={() => setStepIndex(index)}
                                            aria-current={
                                                index === currentIndex
                                                    ? 'step'
                                                    : undefined
                                            }
                                            className={`flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-3 text-left text-xs font-medium transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:bg-muted disabled:cursor-wait disabled:opacity-50 motion-reduce:transition-none ${index === currentIndex ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground'}`}
                                        >
                                            <span
                                                className={`flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] ${index === currentIndex ? 'bg-teal-800 text-white dark:bg-teal-200 dark:text-teal-950' : 'border'}`}
                                                aria-hidden="true"
                                            >
                                                {index < currentIndex ? (
                                                    <Check className="size-3" />
                                                ) : (
                                                    index + 1
                                                )}
                                            </span>
                                            {item.label}
                                        </button>
                                    </li>
                                ))}
                            </ol>
                        </nav>
                        <div className="flex min-w-0 flex-col gap-7 px-6 py-7 sm:px-8 sm:py-8">
                            <div className="flex items-center justify-between gap-4">
                                <span className="flex size-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200">
                                    <Icon
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <p
                                    role="status"
                                    className="text-xs text-muted-foreground"
                                >
                                    Step {currentIndex + 1} of {steps.length} ·{' '}
                                    {step.label}
                                </p>
                            </div>
                            <div className="flex flex-col gap-4">
                                <DialogTitle
                                    ref={titleRef}
                                    tabIndex={-1}
                                    className="text-2xl leading-tight font-medium tracking-tight outline-none sm:text-3xl"
                                >
                                    {step.title}
                                </DialogTitle>
                                <DialogDescription className="text-sm leading-7">
                                    {step.description}
                                </DialogDescription>
                            </div>
                            <ol className="flex flex-col gap-4">
                                {step.instructions.map((instruction, index) => (
                                    <li
                                        key={instruction}
                                        className="flex gap-3 text-sm leading-6"
                                    >
                                        <span
                                            className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border text-[10px] text-muted-foreground"
                                            aria-hidden="true"
                                        >
                                            {index + 1}
                                        </span>
                                        <span>{instruction}</span>
                                    </li>
                                ))}
                            </ol>
                            <div className="flex gap-3 rounded-xl bg-muted/60 p-4 text-xs leading-6 text-muted-foreground">
                                <Lightbulb
                                    className="mt-1 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <p>{step.tip}</p>
                            </div>
                            {destination && (
                                <Link
                                    href={destination}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        if (!saving.current) {
                                            void closeGuide().then((closed) => {
                                                if (closed) {
                                                    router.visit(destination);
                                                }
                                            });
                                        }
                                    }}
                                    aria-disabled={http.processing}
                                    tabIndex={http.processing ? -1 : undefined}
                                    className="inline-flex min-h-11 w-fit cursor-pointer items-center gap-2 rounded-md text-xs font-medium text-teal-800 hover:text-teal-600 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-disabled:pointer-events-none aria-disabled:opacity-50 dark:text-teal-200 dark:hover:text-teal-300"
                                >
                                    {step.actionLabel}
                                    <ArrowRight
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            )}
                            {error && (
                                <div
                                    role="alert"
                                    className="flex flex-col gap-2 text-sm leading-6 text-destructive"
                                >
                                    <p>{error}</p>
                                    <Button
                                        variant="outline"
                                        className="min-h-11 w-fit cursor-pointer"
                                        onClick={() => setOpen(false)}
                                    >
                                        Close for now
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                    <div
                        className="h-1 shrink-0 bg-muted"
                        role="progressbar"
                        aria-label="Tutorial progress"
                        aria-valuemin={1}
                        aria-valuemax={steps.length}
                        aria-valuenow={currentIndex + 1}
                    >
                        <div
                            className="h-full bg-teal-700 transition-[width] duration-300 motion-reduce:transition-none dark:bg-teal-300"
                            style={{
                                width: `${((currentIndex + 1) / steps.length) * 100}%`,
                            }}
                        />
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-6 py-5 sm:px-8">
                        <Button
                            variant="ghost"
                            disabled={http.processing}
                            className="min-h-11 cursor-pointer px-2 text-muted-foreground"
                            onClick={() => void closeGuide()}
                        >
                            Skip for now
                        </Button>
                        <div className="flex items-center gap-2">
                            {currentIndex > 0 && (
                                <Button
                                    variant="outline"
                                    disabled={http.processing}
                                    className="min-h-11 cursor-pointer"
                                    onClick={() =>
                                        setStepIndex(currentIndex - 1)
                                    }
                                >
                                    <ArrowLeft aria-hidden="true" />
                                    Back
                                </Button>
                            )}
                            <Button
                                disabled={http.processing}
                                aria-busy={http.processing}
                                className="min-h-11 min-w-28 cursor-pointer"
                                onClick={() => {
                                    if (isLastStep) {
                                        void closeGuide();
                                    } else {
                                        setStepIndex(currentIndex + 1);
                                    }
                                }}
                            >
                                {http.processing
                                    ? 'Saving…'
                                    : isLastStep
                                      ? 'Finish guide'
                                      : currentIndex === 0
                                        ? 'Get started'
                                        : 'Next'}
                                {!http.processing && (
                                    <ArrowRight aria-hidden="true" />
                                )}
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </OnboardingContext.Provider>
    );
}
