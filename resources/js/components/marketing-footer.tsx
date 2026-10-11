import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { home, privacy, terms } from '@/routes';

const footerLink =
    'inline-flex min-h-11 cursor-pointer items-center rounded-md text-xs font-medium hover:text-[var(--pt-teal)] active:text-[var(--pt-ink)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pt-teal)]';

export default function MarketingFooter({
    children,
}: {
    children?: ReactNode;
}) {
    return (
        <footer>
            <div className="mx-auto flex max-w-[1440px] flex-col justify-between gap-6 px-6 py-12 sm:px-10 lg:flex-row lg:items-center lg:gap-10 lg:px-16">
                <div className="flex flex-col gap-5">
                    <Link
                        href={home()}
                        className={`w-fit hover:opacity-80 ${footerLink}`}
                        aria-label="PasadaTrack home"
                    >
                        <AppLogo className="h-9 w-44 sm:h-10 sm:w-52 dark:bg-transparent" />
                    </Link>
                    <p className="max-w-xs text-xs leading-6 text-[var(--pt-muted)]">
                        Built for the people who keep the pasada moving.
                    </p>
                </div>
                <nav
                    aria-label="Footer navigation"
                    className="flex flex-wrap items-center gap-x-7 gap-y-2"
                >
                    {children}
                    <Link href={terms()} className={footerLink}>
                        Terms & conditions
                    </Link>
                    <Link href={privacy()} className={footerLink}>
                        Privacy policy
                    </Link>
                </nav>
            </div>
        </footer>
    );
}
