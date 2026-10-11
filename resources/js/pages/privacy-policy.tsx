import { Link } from '@inertiajs/react';
import LegalPage from '@/components/legal-page';
import type { LegalSection } from '@/components/legal-page';
import { terms } from '@/routes';

const sections: LegalSection[] = [
    {
        id: 'scope-and-responsibility',
        title: 'Scope and responsibility',
        content: (
            <>
                <p>
                    This draft describes the information handled by the current
                    PasadaTrack application. It covers account access and the
                    operational records entered by jeepney operators and
                    transport cooperatives.
                </p>
                <p>
                    The legal operator and privacy contact are not yet
                    finalized. The respective responsibilities of the service
                    operator and each organization using a workspace must be
                    confirmed before this document becomes a final privacy
                    notice.
                </p>
            </>
        ),
    },
    {
        id: 'information-handled',
        title: 'Information the app handles',
        content: (
            <>
                <p>
                    The information available in a workspace depends on which
                    features its team uses and what its authorized users enter.
                </p>
                <ul>
                    <li>
                        <strong className="font-medium text-[var(--pt-ink)]">
                            Account information:
                        </strong>{' '}
                        names, email addresses, authentication records, and team
                        memberships or invitations.
                    </li>
                    <li>
                        <strong className="font-medium text-[var(--pt-ink)]">
                            Driver and fleet records:
                        </strong>{' '}
                        driver names, codes, phone and license numbers when
                        entered, vehicle details, routes, and assignments.
                    </li>
                    <li>
                        <strong className="font-medium text-[var(--pt-ink)]">
                            Attendance records:
                        </strong>{' '}
                        time-in and time-out entries, corrections, device
                        identifiers, device-user mappings, and supported
                        imported scan events.
                    </li>
                    <li>
                        <strong className="font-medium text-[var(--pt-ink)]">
                            Collection and audit records:
                        </strong>{' '}
                        boundary obligations, payment entries, allocations,
                        balances, adjustment reasons, and the history of changes
                        to records.
                    </li>
                    <li>
                        <strong className="font-medium text-[var(--pt-ink)]">
                            Technical information:
                        </strong>{' '}
                        session information, browser preferences, and diagnostic
                        information recorded by the application or its hosting
                        environment.
                    </li>
                </ul>
                <p>
                    The current attendance import workflow accepts event
                    identifiers, device-user identifiers, timestamps, and scan
                    direction. It does not provide fields for fingerprint images
                    or biometric templates. Any future integration involving
                    biometric templates needs its own assessment and notice.
                </p>
            </>
        ),
    },
    {
        id: 'purposes-and-basis',
        title: 'Why information is used',
        content: (
            <>
                <p>
                    The app uses these records to provide account access,
                    organize fleet operations, record attendance, track boundary
                    collections, produce reports, and show who changed an
                    operational record.
                </p>
                <p>
                    Session and diagnostic information supports sign-in,
                    security, and troubleshooting. Information entered by your
                    organization is processed through the features its
                    authorized users choose to use.
                </p>
                <p>
                    The responsible organization must confirm the applicable
                    lawful basis for each processing activity and provide any
                    required notice or consent process. This draft does not
                    assume that consent alone covers every use of driver or
                    employee information.
                </p>
            </>
        ),
    },
    {
        id: 'access-and-third-parties',
        title: 'Access and third parties',
        content: (
            <>
                <p>
                    Operational records are associated with a team workspace.
                    Access is controlled by membership and role permissions.
                    Authorized users who export reports are responsible for how
                    they store or share those copies.
                </p>
                <p>
                    When you open the fleet map, the browser requests map tiles
                    from OpenStreetMap. These requests disclose normal
                    connection information, such as an IP address and the
                    requested map area, to that external service. See the{' '}
                    <a
                        href="https://osmfoundation.org/wiki/Privacy_Policy"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        OpenStreetMap Foundation privacy policy
                    </a>
                    .
                </p>
                <p>
                    The production hosting, email, backup, and other service
                    providers, their processing locations, and any international
                    transfers must be identified before publication. No
                    finalized provider list or data-sharing arrangement is
                    asserted by this draft.
                </p>
            </>
        ),
    },
    {
        id: 'cookies-and-preferences',
        title: 'Cookies and browser preferences',
        content: (
            <>
                <p>
                    PasadaTrack uses session and security cookies to support
                    sign-in and protect requests. It also stores appearance
                    preferences in browser storage and a cookie, and may use a
                    cookie to remember the sidebar state.
                </p>
                <p>
                    You can manage cookies and browser storage through your
                    browser settings. Blocking required cookies may prevent
                    sign-in or other features from working. Any production
                    analytics, advertising, or additional tracking tools need to
                    be confirmed and described before they are introduced.
                </p>
            </>
        ),
    },
    {
        id: 'retention-and-security',
        title: 'Retention and security',
        content: (
            <>
                <p>
                    The app includes account authentication, team access
                    controls, password hashing, and operational audit history.
                    These controls do not remove the need for secure hosting,
                    careful access management, and appropriate handling of
                    exported records.
                </p>
                <p>
                    Operational history can remain when a driver or vehicle is
                    archived. Removing access or deleting an account should not
                    be treated as confirmation that every associated
                    operational, audit, or backup record has been erased.
                </p>
                <p>
                    Retention periods, deletion procedures, backup retention,
                    and production security arrangements are not yet finalized.
                    The operator must confirm them for each category of
                    information before this notice is published as final.
                </p>
            </>
        ),
    },
    {
        id: 'rights-and-requests',
        title: 'Your rights and requests',
        content: (
            <>
                <p>
                    Depending on the applicable law and circumstances, your
                    rights may include being informed, accessing or correcting
                    personal information, objecting to processing, requesting
                    erasure or blocking, and data portability. These rights may
                    be subject to legal conditions and exceptions.
                </p>
                <p>
                    For records entered by your cooperative or operator, begin
                    by contacting your workspace owner. A dedicated privacy
                    contact, request-verification process, and response
                    arrangements need to be finalized by the responsible
                    organization.
                </p>
                <p>
                    For information about rights under Philippine privacy law
                    and raising a concern with the regulator, visit the{' '}
                    <a
                        href="https://privacy.gov.ph/data-privacy-act-/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        National Privacy Commission’s Data Privacy Act guidance
                    </a>
                    .
                </p>
            </>
        ),
    },
    {
        id: 'changes-and-contact',
        title: 'Changes and privacy contact',
        content: (
            <>
                <p>
                    The revision date at the top tracks updates to this draft.
                    Changes to data collection, device integrations, or service
                    providers should be reflected in the final notice and
                    communicated as required.
                </p>
                <p>
                    The legal operator’s name, address, and privacy contact have
                    not yet been finalized. This draft needs those details,
                    confirmed processing arrangements, and legal review before
                    publication. For general use responsibilities, see the{' '}
                    <Link href={terms()}>Terms and Conditions</Link>.
                </p>
            </>
        ),
    },
];

export default function PrivacyPolicy() {
    return (
        <LegalPage
            kind="privacy"
            title="Privacy Policy"
            description="Understand the information behind your workspace, how it is used, and the details still being finalized."
            sections={sections}
        />
    );
}
