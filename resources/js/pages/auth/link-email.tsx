import { Form, Head, Link } from "@inertiajs/react";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { store } from "@/routes/google/link";
import { login } from "@/routes";
export default function LinkEmail({
    mode,
    challengeId,
    token,
}: {
    mode: "sent" | "confirm";
    challengeId?: number;
    token?: string;
}) {
    return (
        <>
            <Head title="Verify Google account email" />
            <div className="space-y-5">
                <h1 className="text-xl font-semibold">Verify your email address</h1>
                <p className="text-sm text-muted-foreground">
                    {mode === "sent"
                        ? "We sent a verification link to your provisioned email address. Open it in this browser within 15 minutes to connect your Google account. You are not signed in yet."
                        : "Confirm ownership of your provisioned email to connect the Google account you selected. Your two-factor authentication will still apply."}
                </p>
                {mode === "confirm" && challengeId && token && (
                    <Form {...store.form({ challenge: challengeId, token })}>
                        {({ errors, processing }) => (
                            <>
                                <InputError message={Object.values(errors)[0]} />
                                <Button type="submit" disabled={processing} className="w-full">
                                    {processing ? "Confirming…" : "Confirm Google account"}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                <p className="text-xs text-muted-foreground">
                    If the link expires, start Google sign-in again to request another.
                </p>
                <Button variant="outline" asChild>
                    <Link href={login()}>Back to sign in</Link>
                </Button>
            </div>
        </>
    );
}
