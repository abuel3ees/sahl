<?php

use Inertia\Testing\AssertableInertia;

it('renders the sign-in page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/login'));
});
