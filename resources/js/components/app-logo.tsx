import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

export default function AppLogo({ className }: { className?: string }) {
    return (
        <span
            role="img"
            aria-label="PasadaTrack"
            className={cn(
                'inline-flex h-8 w-44 max-w-full shrink-0 items-center rounded-md group-data-[collapsible=icon]:size-8 dark:bg-white',
                className,
            )}
        >
            <svg
                viewBox="139 205 1960 388"
                aria-hidden="true"
                className="h-full w-full group-data-[collapsible=icon]:hidden"
            >
                <image
                    href="/pasadatrack-logo-v1.png"
                    width="2172"
                    height="724"
                />
            </svg>
            <AppLogoIcon
                aria-hidden="true"
                className="hidden size-8 group-data-[collapsible=icon]:block"
            />
        </span>
    );
}
