<?php

use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config(['app.locale' => 'ar']);
});

it('defaults to Arabic and renders right-to-left', function () {
    $this->get('/developers')
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'ar'));
});

it('uses the language saved by the toggle cookie', function () {
    $this->withUnencryptedCookie('lang', 'en')
        ->get('/developers')
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
});

it('lets a ?lang= link override the cookie', function () {
    $this->withUnencryptedCookie('lang', 'ar')
        ->get('/developers?lang=en')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'en'));
});

it('ignores unsupported languages', function () {
    $this->get('/developers?lang=fr')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale', 'ar'));
});
