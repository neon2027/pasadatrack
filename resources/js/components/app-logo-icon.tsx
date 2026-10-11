import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function AppLogoIcon({
    className,
    ...props
}: SVGAttributes<SVGElement>) {
    return (
        <svg
            role="img"
            aria-label="PasadaTrack"
            {...props}
            viewBox="139 205 403 388"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('shrink-0 rounded-md dark:bg-white', className)}
        >
            <image href="/pasadatrack-logo-v1.png" width="2172" height="724" />
        </svg>
    );
}
