/** A square-capped arrow that points "forward": right in English, left in Arabic. */
export function ArrowIcon({ size = 20, strokeWidth = 2.2 }: { size?: number; strokeWidth?: number }) {
    return (
        <svg
            aria-hidden="true"
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth}
            strokeLinecap="square"
            className="flex-none rtl:-scale-x-100"
        >
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
        </svg>
    );
}
