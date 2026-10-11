<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

class DocumentationController extends Controller
{
    /**
     * Markdown files published on the site, keyed by URL slug.
     *
     * @var array<string, string>
     */
    public const PAGES = [
        'ministry-of-interior' => 'docs/v1/ministry-of-interior.md',
    ];

    /**
     * Repository-relative links inside the Markdown, rewritten to their site URLs.
     *
     * @var array<string, string>
     */
    private const LINKS = [
        '../api/openapi.json' => '/developers',
    ];

    public function show(string $page): Response
    {
        $markdown = file_get_contents(base_path(self::PAGES[$page]));

        $title = Str::of($markdown)->match('/^# (.+)$/m')->toString();
        $markdown = preg_replace('/^# .+\R/m', '', $markdown, 1);

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'insert' => 'none',
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
            ],
        ], [new HeadingPermalinkExtension]);

        $html = strtr($html, array_combine(
            array_map(fn (string $from): string => 'href="'.$from.'"', array_keys(self::LINKS)),
            array_map(fn (string $to): string => 'href="'.$to.'"', self::LINKS),
        ));

        preg_match_all('/<h2 id="([^"]+)">(.*?)<\/h2>/s', $html, $headings, PREG_SET_ORDER);

        return Inertia::render('docs/show', [
            'title' => $title,
            'html' => $html,
            'toc' => array_map(fn (array $heading): array => [
                'id' => $heading[1],
                'text' => html_entity_decode(strip_tags($heading[2])),
            ], $headings),
        ]);
    }
}
