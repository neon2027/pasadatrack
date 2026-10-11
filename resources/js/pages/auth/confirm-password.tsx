import { Head } from "@inertiajs/react";
import GoogleSignIn from "@/components/google-sign-in";

export default function ConfirmIdentity({ googleConfigured }: { googleConfigured: boolean }) {
    return (
        <>
            <Head title="Confirm your identity" />
            <GoogleSignIn configured={googleConfigured} />
            <p className="text-center text-sm text-muted-foreground">
                Choose the Google account connected to your PasadaTrack account to continue.
            </p>
        </>
    );
}

ConfirmIdentity.layout = {
    title: "Confirm your identity",
    description: "Confirm your Google account before changing sensitive settings.",
};
