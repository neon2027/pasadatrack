import { Breadcrumbs } from '@/components/breadcrumbs';
import { BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useOnboarding } from '@/components/workspace-onboarding';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { openGuide } = useOnboarding();
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1 shrink-0" />
                <div className="min-w-0 [&_[data-slot=breadcrumb-page]]:truncate [&_ol]:flex-nowrap">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <Button
                id="onboarding-trigger"
                variant="ghost"
                onClick={openGuide}
                className="ml-auto min-h-11 shrink-0 cursor-pointer"
                aria-label="Open quick guide"
            >
                <BookOpen aria-hidden="true" />
                <span className="hidden sm:inline">Quick guide</span>
            </Button>
        </header>
    );
}
