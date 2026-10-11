import { Head } from "@inertiajs/react";
import GoogleSignIn from "@/components/google-sign-in";
import TeamInvitationAlert from "@/components/team-invitation-alert";
import type { TeamInvitationContext } from "@/types";

type Props = {
    googleConfigured: boolean;
    teamInvitation?: TeamInvitationContext | null;
};

export default function Login({ googleConfigured, teamInvitation }: Props) {
    return (
        <>
            <Head title="Sign in" />
            {teamInvitation && <TeamInvitationAlert invitation={teamInvitation} action="Sign in" />}
            <GoogleSignIn configured={googleConfigured} />
            <p className="text-center text-sm text-muted-foreground">
                An administrator must create your account before you can sign in.
            </p>
        </>
    );
}

Login.layout = {
    title: "Sign in to PasadaTrack",
    description: "Continue securely with your Google account.",
};
