import { Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { JeepneyScene } from '@/lib/jeepney-hero-scene';

export default function JeepneyHero() {
    const containerRef = useRef<HTMLDivElement>(null);
    const sceneRef = useRef<JeepneyScene | null>(null);
    const pausedRef = useRef(false);
    const [paused, setPaused] = useState(false);
    const [ready, setReady] = useState(false);
    const [reducedMotion, setReducedMotion] = useState(false);

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        let cancelled = false;
        let scene: JeepneyScene | null = null;

        void import('@/lib/jeepney-hero-scene')
            .then(({ createJeepneyScene }) => {
                if (cancelled) {
                    return;
                }

                scene = createJeepneyScene(container, {
                    onReady: () => setReady(true),
                    onUnavailable: () => setReady(false),
                    onMotionPreference: setReducedMotion,
                });
                sceneRef.current = scene;
                scene.setPaused(pausedRef.current);
            })
            .catch(() => {
                if (!cancelled) {
                    setReady(false);
                }
            });

        return () => {
            cancelled = true;
            scene?.dispose();
            sceneRef.current = null;
        };
    }, []);

    function toggleMotion() {
        const nextPaused = !pausedRef.current;
        pausedRef.current = nextPaused;
        sceneRef.current?.setPaused(nextPaused);
        setPaused(nextPaused);
    }

    return (
        <figure className="relative min-w-0">
            <div
                role="img"
                aria-label="Two classic Philippine jeepneys in teal and yellow, with chrome details and soft shadows."
                className="relative aspect-[4/3] w-full overflow-hidden rounded-3xl"
            >
                <img
                    src="/jeepney-hero-fallback.svg"
                    alt=""
                    width="800"
                    height="600"
                    fetchPriority="high"
                    className={`absolute inset-0 h-full w-full object-contain ${ready ? 'invisible' : ''}`}
                />
                <div
                    ref={containerRef}
                    aria-hidden="true"
                    className={`absolute inset-0 ${ready ? '' : 'invisible'}`}
                />
            </div>
            <div className="mt-3 flex min-h-11 justify-end">
                {ready && (
                    <button
                        type="button"
                        onClick={toggleMotion}
                        aria-pressed={paused || reducedMotion}
                        disabled={reducedMotion}
                        className="inline-flex min-h-11 min-w-40 cursor-pointer items-center justify-center gap-2 rounded-full px-4 text-xs font-medium text-[var(--pt-muted)] hover:bg-[var(--pt-cloud)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--pt-teal)] active:bg-[var(--pt-line)] disabled:cursor-default disabled:hover:bg-transparent"
                    >
                        {paused || reducedMotion ? (
                            <Play className="size-3.5" aria-hidden="true" />
                        ) : (
                            <Pause className="size-3.5" aria-hidden="true" />
                        )}
                        {reducedMotion
                            ? 'Reduced motion enabled'
                            : paused
                              ? 'Resume animation'
                              : 'Pause animation'}
                    </button>
                )}
            </div>
        </figure>
    );
}
