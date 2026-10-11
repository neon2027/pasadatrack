<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_guests_can_read_terms_without_joining_a_team(): void
    {
        $response = $this->get(route('terms'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('terms-and-conditions')
                ->where('auth.user', null)
                ->where('currentTeam', null)
            );
    }

    public function test_guests_can_read_privacy_policy_without_joining_a_team(): void
    {
        $response = $this->get(route('privacy'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('privacy-policy')
                ->where('auth.user', null)
                ->where('currentTeam', null)
            );
    }
}
