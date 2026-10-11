import { Link, usePage } from "@inertiajs/react";
import {
    Map,
    Users,
    Bus,
    Route,
    CalendarDays,
    Wallet,
    Fingerprint,
    ChartNoAxesCombined,
    History,
} from "lucide-react";
import AppLogo from "@/components/app-logo";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import { TeamSwitcher } from "@/components/team-switcher";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { dashboard } from "@/routes";
import { index as driversIndex } from "@/routes/drivers";
import { index as jeepneysIndex } from "@/routes/jeepneys";
import { index as routesIndex } from "@/routes/routes";
import { index as assignmentsIndex } from "@/routes/assignments";
import { index as collectionsIndex } from "@/routes/collections";
import { index as attendanceIndex } from "@/routes/attendance";
import { index as reportsIndex } from "@/routes/reports";
import { index as auditIndex } from "@/routes/audit";
import { index as devicesIndex } from "@/routes/devices";
import { index as usersIndex } from "@/routes/admin/users";
import type { NavItem } from "@/types";

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam ? dashboard(page.props.currentTeam.slug) : "/";

    const mainNavItems: NavItem[] = [
        {
            title: "Fleet map",
            href: dashboardUrl,
            icon: Map,
        },
    ];

    if (page.props.currentTeam) {
        const team = page.props.currentTeam.slug;
        const permissions = page.props.teamPermissions;
        const modules = [
            { permission: "fleet:view", title: "Drivers", href: driversIndex(team), icon: Users },
            { permission: "fleet:view", title: "Jeepneys", href: jeepneysIndex(team), icon: Bus },
            { permission: "fleet:view", title: "Routes", href: routesIndex(team), icon: Route },
            {
                permission: "scheduling:view",
                title: "Scheduling",
                href: assignmentsIndex(team),
                icon: CalendarDays,
            },
            {
                permission: "collections:view",
                title: "Boundary & collections",
                href: collectionsIndex(team),
                icon: Wallet,
            },
            {
                permission: "attendance:view",
                title: "Attendance",
                href: attendanceIndex(team),
                icon: Fingerprint,
            },
            {
                permission: "attendance-devices:manage",
                title: "Attendance devices",
                href: devicesIndex(team),
                icon: Fingerprint,
            },
            {
                permission: "reports:view",
                title: "Reports",
                href: reportsIndex(team),
                icon: ChartNoAxesCombined,
            },
            {
                permission: "audit:view",
                title: "Audit history",
                href: auditIndex(team),
                icon: History,
            },
        ];
        mainNavItems.push(...modules.filter((item) => permissions.includes(item.permission)));
    }
    if (page.props.auth.canAdminister) {
        mainNavItems.push({ title: "Administration", href: usersIndex(), icon: Users });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
