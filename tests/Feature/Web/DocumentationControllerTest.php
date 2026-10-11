<?php

use Inertia\Testing\AssertableInertia;

it('renders the ministry documentation from Markdown with a table of contents', function () {
    $this->get('/docs/ministry-of-interior')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('docs/show')
            ->where('title', 'Ministry of Interior: Good Conduct Certificate (V1)')
            ->where('toc.0', ['id' => '1-the-story-in-30-seconds', 'text' => '1. The story in 30 seconds'])
            ->where('html', fn (string $html) => str_contains($html, '<h2 id="2-application-lifecycle">')
                && str_contains($html, 'class="language-mermaid"')
                && str_contains($html, 'href="/developers"')));
});

it('returns 404 for a page that is not published', function () {
    $this->get('/docs/secrets')->assertNotFound();
});
