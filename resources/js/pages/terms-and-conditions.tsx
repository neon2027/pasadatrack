import { Link } from '@inertiajs/react';
import LegalPage from '@/components/legal-page';
import type { LegalSection } from '@/components/legal-page';
import { privacy } from '@/routes';

const sections: LegalSection[] = [
    {
        id: 'about-the-service',
        title: 'About the service',
        content: (
            <>
                <p>
                    PasadaTrack helps jeepney operators and transport
                    cooperatives organize drivers, vehicles, routes, schedules,
                    attendance, boundary collections, and operational reports.
                </p>
                <p>
                    These draft terms outline proposed responsibilities for
                    using that workspace. The contracting operator, final
                    service commitments, and effective date remain to be
                    confirmed.
                </p>
            </>
        ),
    },
    {
        id: 'accounts-and-access',
        title: 'Accounts and access',
        content: (
            <>
                <p>
                    Use accurate account information and keep your sign-in
                    credentials private. Use the available security settings to
                    protect your account, and tell your workspace owner if you
                    suspect unauthorized access.
                </p>
                <p>
                    Team access depends on membership and assigned permissions.
                    Use only the workspaces and records you are authorized to
                    access. Workspace owners should review invitations and
                    remove access when it is no longer needed.
                </p>
            </>
        ),
    },
    {
        id: 'operational-records',
        title: 'Your operational records',
        content: (
            <>
                <p>
                    Enter records carefully and review schedules, attendance,
                    collections, and adjustments before relying on them.
                    PasadaTrack supports record keeping; your organization
                    remains responsible for its operating decisions and the
                    accuracy of the information it enters.
                </p>
                <p>
                    Only upload personal information that your organization is
                    authorized to process. Inform affected drivers and staff
                    about how their records will be used. Read the{' '}
                    <Link href={privacy()}>Privacy Policy</Link> for the
                    categories of information handled by the app.
                </p>
            </>
        ),
    },
    {
        id: 'acceptable-use',
        title: 'Acceptable use',
        content: (
            <>
                <p>
                    The proposed use rules require users to respect other
                    people’s records and the security of each workspace.
                </p>
                <ul>
                    <li>
                        Do not access another team’s records without
                        authorization or share credentials to bypass
                        permissions.
                    </li>
                    <li>
                        Do not upload malicious files, interfere with the
                        service, or attempt to defeat its security controls.
                    </li>
                    <li>
                        Do not knowingly enter false records, impersonate
                        another person, or use the app for unlawful activity.
                    </li>
                </ul>
            </>
        ),
    },
    {
        id: 'collections-and-pricing',
        title: 'Collections and pricing',
        content: (
            <>
                <p>
                    Boundary collection tools record payments, allocations,
                    adjustments, and balances. Recording a payment in
                    PasadaTrack does not itself transfer money or settle a
                    transaction with a bank or payment provider.
                </p>
                <p>
                    Pricing is discussed directly based on your operation’s
                    requirements. Contact{' '}
                    <a href="mailto:lustanexequiel@gmail.com?subject=PasadaTrack%20pricing%20inquiry">
                        lustanexequiel@gmail.com
                    </a>{' '}
                    to negotiate pricing. Any agreed rates, scope, billing
                    terms, and hardware costs should be confirmed separately;
                    this draft does not establish a paid service commitment.
                </p>
            </>
        ),
    },
    {
        id: 'devices-and-integrations',
        title: 'Devices and integrations',
        content: (
            <>
                <p>
                    The fleet map may show demonstration positions.
                    Demonstration data should not be used to locate a vehicle,
                    make a safety decision, or verify a real trip.
                </p>
                <p>
                    Live GPS tracking requires supported hardware and a
                    production integration. Attendance tools support device-user
                    mapping and supported record imports; direct synchronization
                    with a physical biometric terminal requires a separate
                    compatible integration.
                </p>
            </>
        ),
    },
    {
        id: 'availability-and-records',
        title: 'Availability and record access',
        content: (
            <>
                <p>
                    Keep appropriate copies of the records your organization
                    needs. Authorized users can export supported operational
                    reports. Review any exported file before using it for
                    reconciliation or sharing it with others.
                </p>
                <p>
                    Account access, team membership, archived records, and
                    historical audit records are separate parts of the app.
                    Losing access to a workspace does not necessarily erase its
                    records. Availability commitments, support arrangements, and
                    record-retention terms remain subject to operator review.
                </p>
            </>
        ),
    },
    {
        id: 'updates-and-contact',
        title: 'Updates and contact',
        content: (
            <>
                <p>
                    The date at the top identifies the latest revision of this
                    draft. Final terms should identify the legal operator, how
                    users can contact it, and when those terms take effect.
                </p>
                <p>
                    The operator name and support contact are not yet finalized.
                    For questions about records or permissions in an existing
                    workspace, contact your team owner. This document requires
                    operator and legal review before it is used as a finalized
                    agreement.
                </p>
            </>
        ),
    },
];

export default function TermsAndConditions() {
    return (
        <LegalPage
            kind="terms"
            title="Terms and Conditions"
            description="A clear guide to using PasadaTrack and looking after the workspace your team relies on."
            sections={sections}
        />
    );
}
