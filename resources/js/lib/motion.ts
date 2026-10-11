import { useEffect, useState } from 'react';

/** True when the visitor asked their OS for reduced motion. Animated demos should hold still. */
export function usePrefersReducedMotion(): boolean {
    const [reduced, setReduced] = useState(false);

    useEffect(() => {
        const query = window.matchMedia('(prefers-reduced-motion: reduce)');
        setReduced(query.matches);

        const onChange = (event: MediaQueryListEvent) => setReduced(event.matches);
        query.addEventListener('change', onChange);

        return () => query.removeEventListener('change', onChange);
    }, []);

    return reduced;
}
