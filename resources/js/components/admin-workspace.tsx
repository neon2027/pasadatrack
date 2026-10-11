import { Link, usePage } from "@inertiajs/react";
import type { ReactNode } from "react";
import { Workspace } from "@/components/operations-workspace";
import { Button } from "@/components/ui/button";
import { index as usersIndex } from "@/routes/admin/users";
import { index as rolesIndex } from "@/routes/admin/roles";
import { index as levelsIndex } from "@/routes/admin/levels";
import { index as auditIndex } from "@/routes/admin/audit";
import { confirm } from "@/routes/password";
export default function AdminWorkspace({
    title,
    description,
    children,
    action,
}: {
    title: string;
    description: string;
    children: ReactNode;
    action?: ReactNode;
}) {
    const { auth } = usePage().props;
    const url = usePage().url.split("?")[0];
    const tabs = [
        { title: "Users", url: usersIndex.url() },
        ...(auth.canManageCatalog
            ? [
                  { title: "Team roles", url: rolesIndex.url() },
                  { title: "Privilege levels", url: levelsIndex.url() },
                  { title: "Admin audit", url: auditIndex.url() },
              ]
            : []),
    ];
    return (
        <Workspace title={title} description={description} action={action} area="Administration">
            <nav aria-label="Administration" className="flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <Button key={tab.url} variant={url === tab.url ? "secondary" : "ghost"} asChild>
                        <Link href={tab.url} aria-current={url === tab.url ? "page" : undefined}>
                            {tab.title}
                        </Link>
                    </Button>
                ))}
            </nav>
            {auth.requiresGoogleConfirmation && (
                <div
                    role="status"
                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4 text-sm"
                >
                    <p>Confirm your Google identity before changing access, roles, or limits.</p>
                    <Button variant="outline" asChild>
                        <Link href={confirm({ query: { return_to: url } })}>Confirm identity</Link>
                    </Button>
                </div>
            )}
            {children}
        </Workspace>
    );
}
