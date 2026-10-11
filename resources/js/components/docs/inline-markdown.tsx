import { Fragment, type ReactNode } from 'react';

/**
 * Renders the tiny Markdown subset used in OpenAPI descriptions: `code`, **bold**, and
 * blank-line paragraphs. Everything is rendered as text, never as HTML.
 */
export function InlineMarkdown({ text, className }: { text: string; className?: string }) {
    const paragraphs = text.split(/\n\s*\n/).map((paragraph) => paragraph.replace(/\s*\n\s*/g, ' ').trim());

    return (
        <>
            {paragraphs.map((paragraph, index) => (
                <p key={index} className={className}>
                    {formatInline(paragraph)}
                </p>
            ))}
        </>
    );
}

function formatInline(text: string): ReactNode[] {
    return text.split(/(`[^`]+`|\*\*[^*]+\*\*)/g).map((part, index) => {
        if (part.startsWith('`') && part.endsWith('`')) {
            return (
                <code key={index} className="bg-surface px-1.5 py-0.5 font-mono text-[0.88em] text-accent-800">
                    {part.slice(1, -1)}
                </code>
            );
        }

        if (part.startsWith('**') && part.endsWith('**')) {
            return <strong key={index}>{part.slice(2, -2)}</strong>;
        }

        return <Fragment key={index}>{part}</Fragment>;
    });
}
