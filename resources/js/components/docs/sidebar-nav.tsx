export type SidebarSection = {
    heading?: string;
    items: { id: string; label: string; badge?: string }[];
};

/** In-page navigation for the docs sidebar. Labels are English, like the content. */
export function SidebarNav({ sections }: { sections: SidebarSection[] }) {
    return (
        <nav aria-label="On this page" dir="ltr" lang="en" className="flex flex-col gap-8 text-start">
            {sections.map((section, index) => (
                <div key={section.heading ?? index} className="flex flex-col gap-1">
                    {section.heading && (
                        <span className="mb-2 text-[11px] font-semibold tracking-[.12em] text-neutral-700 uppercase">{section.heading}</span>
                    )}
                    {section.items.map((item) => (
                        <a
                            key={item.id}
                            href={`#${item.id}`}
                            className="flex items-baseline gap-2 border-s-2 border-transparent py-1 ps-3 text-[14px] text-ink no-underline hover:border-accent hover:text-accent-700"
                        >
                            {item.badge && <span className="w-12 flex-none font-mono text-[11px] font-semibold text-accent-700">{item.badge}</span>}
                            <span>{item.label}</span>
                        </a>
                    ))}
                </div>
            ))}
        </nav>
    );
}
