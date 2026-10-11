import type { Team } from '@/types';

export type OnboardingDestination =
    | 'teams'
    | 'fleet'
    | 'drivers'
    | 'scheduling'
    | 'collections'
    | 'attendance'
    | 'reports';

export type OnboardingStep = {
    id: string;
    label: string;
    title: string;
    description: string;
    instructions: string[];
    tip: string;
    destination?: OnboardingDestination;
    actionLabel?: string;
};

export function getOnboardingSteps(team: Team | null): OnboardingStep[] {
    const steps: OnboardingStep[] = [
        {
            id: 'welcome',
            label: 'Welcome',
            title: 'A clearer day starts here.',
            description:
                'Meet your PasadaTrack workspace. This quick guide shows you where to begin and how the daily pieces fit together.',
            instructions: [
                'Get familiar with your workspace.',
                'Follow the daily flow: plan, record, then reconcile.',
                'Revisit this tutorial anytime using Quick guide in the top bar.',
            ],
            tip: 'This guide explains the workflow. It does not create or change your operational records.',
        },
        {
            id: 'workspace',
            label: 'Your workspace',
            title: team
                ? 'Start with the right team.'
                : 'Create or join your workspace.',
            description: team
                ? 'Each team has its own records. Check the team name in the sidebar before working on an operating day.'
                : 'A workspace keeps your organization’s drivers, vehicles, and records together. Start by creating a team or accepting an invitation.',
            instructions: [
                'Open the team switcher below the PasadaTrack logo.',
                'Use Teams settings to view your teams or create a workspace.',
                'Switch to the team whose records you want to work with.',
            ],
            tip: 'Your available tools depend on your role in the selected team.',
            destination: 'teams',
            actionLabel: 'Open Teams settings',
        },
    ];

    if (team?.role === 'owner') {
        steps.push(
            {
                id: 'fleet',
                label: 'Fleet setup',
                title: 'Put the essentials in place.',
                description:
                    'Set up your drivers, jeepneys, and routes before you create the first shift.',
                instructions: [
                    'Add driver records in Drivers.',
                    'Add your vehicles in Jeepneys and check their availability.',
                    'Create Routes with the boundary rates your operation uses.',
                ],
                tip: 'Inactive records stay in your history. Keep the records you use for new shifts active.',
                destination: 'drivers',
                actionLabel: 'Open Drivers',
            },
            {
                id: 'scheduling',
                label: 'Scheduling',
                title: 'Give every shift a plan.',
                description:
                    'Bring a driver, jeepney, and route together for a specific operating period.',
                instructions: [
                    'Open Scheduling and add a shift.',
                    'Choose the driver, jeepney, route, and start and end times.',
                    'Confirm who actually drove after the shift.',
                ],
                tip: 'Overlapping assignments and unavailable vehicles are checked when you save.',
                destination: 'scheduling',
                actionLabel: 'Open Scheduling',
            },
            {
                id: 'attendance',
                label: 'Attendance',
                title: 'Record the day as it happens.',
                description:
                    'Keep time-in and time-out records clear, then review anything that needs a correction.',
                instructions: [
                    'Open Attendance to record a driver’s time in.',
                    'Record time out when the work period ends.',
                    'Review corrections, or use Devices to import supported attendance events.',
                ],
                tip: 'A physical biometric terminal needs a compatible integration; supported imports are available separately.',
                destination: 'attendance',
                actionLabel: 'Open Attendance',
            },
            {
                id: 'collections',
                label: 'Collections',
                title: 'Follow every boundary payment.',
                description:
                    'Once driving is confirmed, review the boundary obligation and record the collection against it.',
                instructions: [
                    'Open Boundary & collections and review outstanding obligations.',
                    'Record the payment and allocate it to the correct obligation.',
                    'Check remaining balances and add a reason for any adjustment or reversal.',
                ],
                tip: 'Recording a payment keeps the books up to date; it does not transfer money.',
                destination: 'collections',
                actionLabel: 'Open Boundary & collections',
            },
            {
                id: 'reports',
                label: 'Reports & history',
                title: 'Close the day with a clear picture.',
                description:
                    'Use Reports for reconciliation and Audit history to understand changes to your records.',
                instructions: [
                    'Filter Reports by driver and date.',
                    'Review shifts, collections, and remaining balances, then export when needed.',
                    'Open Audit history to see who changed a record and why.',
                ],
                tip: 'Review the results and your date filters before sharing an exported report.',
                destination: 'reports',
                actionLabel: 'Open Reports',
            },
        );
    } else if (team) {
        steps.push({
            id: 'map',
            label: 'Fleet map',
            title: 'Find your fleet overview.',
            description:
                'The Fleet map is your starting point for reviewing the vehicle information available in your workspace.',
            instructions: [
                'Open Fleet map from the sidebar.',
                'Check whether the page is showing demonstration data or a connected service.',
                'Use the available search and filters to find a vehicle and review its details.',
            ],
            tip: 'Sample positions are not live GPS. Owner-only operational tools will not appear for your role.',
            destination: 'fleet',
            actionLabel: 'Open Fleet map',
        });
    }

    steps.push({
        id: 'ready',
        label: 'Ready to begin',
        title: 'You’re ready for your next pasada.',
        description:
            team?.role === 'owner'
                ? 'Start with your fleet records, then work through scheduling, attendance, collections, and reports.'
                : 'Choose your workspace and explore the tools available to your role.',
        instructions: [
            'Check your selected team before editing records.',
            'Review the information you enter before saving.',
            'Use Quick guide whenever you need a refresher.',
        ],
        tip: 'Finishing or skipping saves your tutorial preference to your account, across devices.',
    });

    return steps;
}
