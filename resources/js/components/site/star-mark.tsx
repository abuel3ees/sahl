/**
 * The Sahl mark: an eight-point star (two overlapping squares) inside a solid square.
 */
export function StarMark({ size = 40, inverted = false }: { size?: number; inverted?: boolean }) {
    const inset = Math.round(size * 0.275);
    const square = inverted ? 'border-accent' : 'border-paper';

    return (
        <span
            aria-hidden="true"
            className={`relative block flex-none ${inverted ? 'bg-paper' : 'bg-accent'}`}
            style={{ width: size, height: size }}
        >
            <span className={`absolute border-2 ${square}`} style={{ inset }} />
            <span className={`absolute rotate-45 border-2 ${square}`} style={{ inset }} />
        </span>
    );
}

/** A solid eight-point star, used as a separator. */
export function SolidStar({ size = 28, className = 'bg-accent' }: { size?: number; className?: string }) {
    return (
        <span aria-hidden="true" className="relative block flex-none" style={{ width: size, height: size }}>
            <span className={`absolute inset-0 ${className}`} />
            <span className={`absolute inset-0 rotate-45 ${className}`} />
        </span>
    );
}
