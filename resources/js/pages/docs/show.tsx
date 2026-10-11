import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { SidebarNav } from '@/components/docs/sidebar-nav';
import { DocsLayout } from '@/layouts/docs-layout';

type Props = {
    title: string;
    html: string;
    toc: { id: string; text: string }[];
};

export default function DocsShow({ title, html, toc }: Props) {
    const articleRef = useRef<HTMLElement>(null);

    useMermaidDiagrams(articleRef, html);

    return (
        <>
            <Head title={title} />
            <DocsLayout
                currentPath="/docs/ministry-of-interior"
                kicker={{ en: 'Documentation · V1', ar: 'التوثيق · الإصدار الأول' }}
                title={title}
                sidebar={<SidebarNav sections={[{ heading: 'On this page', items: toc.map((entry) => ({ id: entry.id, label: entry.text })) }]} />}
            >
                {/* Server-rendered from a Markdown file in this repository, with raw HTML stripped. */}
                <article ref={articleRef} className="prose-sahl max-w-[960px]" dangerouslySetInnerHTML={{ __html: html }} />
            </DocsLayout>
        </>
    );
}

/**
 * Replace ```mermaid code blocks with rendered diagrams in Sahl's palette. Mermaid is large,
 * so it is only downloaded on pages that actually contain a diagram.
 */
function useMermaidDiagrams(articleRef: React.RefObject<HTMLElement | null>, html: string) {
    useEffect(() => {
        const blocks = Array.from(articleRef.current?.querySelectorAll<HTMLElement>('pre > code.language-mermaid') ?? []);

        if (blocks.length === 0) {
            return;
        }

        let cancelled = false;

        import('mermaid').then(async ({ default: mermaid }) => {
            mermaid.initialize({
                startOnLoad: false,
                securityLevel: 'strict',
                theme: 'base',
                fontFamily: 'Archivo, system-ui, sans-serif',
                themeVariables: {
                    primaryColor: '#fff2ef',
                    primaryBorderColor: '#ec3013',
                    primaryTextColor: '#201e1d',
                    secondaryColor: '#eae9e9',
                    tertiaryColor: '#f3f2f2',
                    lineColor: '#201e1d',
                    fontSize: '15px',
                },
            });

            for (const [index, code] of blocks.entries()) {
                const { svg } = await mermaid.render(`diagram-${index}`, code.textContent ?? '');

                if (cancelled) {
                    return;
                }

                const figure = document.createElement('div');
                figure.className = 'mermaid-diagram';
                figure.innerHTML = svg;
                code.parentElement?.replaceWith(figure);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [articleRef, html]);
}
