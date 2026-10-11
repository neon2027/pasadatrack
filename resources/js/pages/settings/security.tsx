import { Head, usePage } from "@inertiajs/react";
import Heading from "@/components/heading";
import type { Props as ManageTwoFactorProps } from "@/components/manage-two-factor";
import ManageTwoFactor from "@/components/manage-two-factor";
import { edit } from "@/routes/security";

export default function Security(props: ManageTwoFactorProps) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Security settings" />
            <h1 className="sr-only">Security settings</h1>
            {props.administratorTwoFactorRequired && !props.twoFactorEnabled && (
                <p role="status" className="rounded-lg border p-4 text-sm">
                    Enable and confirm two-factor authentication below to access administration.
                </p>
            )}
            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Google sign-in"
                    description="Your Google account is your only sign-in method. Manage its password and security in your Google account."
                />
                <p className="text-sm font-medium">{auth.user.email}</p>
                <a
                    href="https://myaccount.google.com/security"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex cursor-pointer text-sm underline underline-offset-4 hover:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                >
                    Manage Google account security
                </a>
            </div>
            <ManageTwoFactor {...props} />
        </>
    );
}

Security.layout = {
    breadcrumbs: [{ title: "Security settings", href: edit() }],
};
