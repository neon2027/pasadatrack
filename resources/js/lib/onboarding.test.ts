import { describe, expect, it } from 'vitest';
import type { Team } from '@/types';
import { getOnboardingSteps } from './onboarding';

function team(role: Team['role']): Team {
    return {
        id: 1,
        name: 'Transport cooperative',
        slug: 'cooperative',
        isPersonal: false,
        role,
    };
}

describe('workspace onboarding', () => {
    it('guides owners through setup before daily operations and reconciliation', () => {
        const steps = getOnboardingSteps(team('owner'));

        expect(steps.map((step) => step.id)).toEqual([
            'welcome',
            'workspace',
            'fleet',
            'scheduling',
            'attendance',
            'collections',
            'reports',
            'ready',
        ]);
    });

    it.each(['member', 'admin', undefined] as const)(
        'keeps owner-only tools out of the guide for %s',
        (role) => {
            const steps = getOnboardingSteps(team(role));

            expect(
                steps.map((step) => step.destination).filter(Boolean),
            ).toEqual(['teams', 'fleet']);
        },
    );

    it('starts teamless users with workspace setup instead of unavailable fleet tools', () => {
        const steps = getOnboardingSteps(null);

        expect(steps.map((step) => step.destination).filter(Boolean)).toEqual([
            'teams',
        ]);
        expect(steps.at(-1)?.id).toBe('ready');
    });
});
