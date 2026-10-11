import { useEffect, useRef } from 'react';

export function useWelcomeMotion() {
    const pageRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const page = pageRef.current;

        if (!page) {
            return;
        }

        const motionPreference = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const animations = new Map<Element, Animation>();
        let observer: IntersectionObserver | undefined;
        let frame: number | undefined;

        function updateProgress() {
            frame = undefined;
            const scrollableHeight =
                document.documentElement.scrollHeight - window.innerHeight;
            const progress =
                scrollableHeight > 0 ? window.scrollY / scrollableHeight : 0;
            page?.style.setProperty(
                '--pt-scroll-progress',
                String(Math.min(1, Math.max(0, progress))),
            );
        }

        function scheduleProgress() {
            if (frame === undefined) {
                frame = window.requestAnimationFrame(updateProgress);
            }
        }

        function configureReveals() {
            observer?.disconnect();
            animations.forEach((animation) => animation.cancel());
            animations.clear();

            if (
                motionPreference.matches ||
                !('IntersectionObserver' in window)
            ) {
                return;
            }

            observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (
                            !entry.isIntersecting ||
                            entry.intersectionRatio < 0.12 ||
                            entry.target.contains(document.activeElement)
                        ) {
                            return;
                        }

                        animations.get(entry.target)?.cancel();
                        const delay = Number(
                            (entry.target as HTMLElement).dataset.revealDelay ??
                                0,
                        );
                        const animation = entry.target.animate(
                            [
                                {
                                    opacity: 0.15,
                                    transform: 'translateY(28px)',
                                },
                                { opacity: 1, transform: 'translateY(0)' },
                            ],
                            {
                                duration: 800,
                                delay,
                                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                                fill: 'backwards',
                            },
                        );

                        animations.set(entry.target, animation);
                        animation.onfinish = () =>
                            animations.delete(entry.target);
                    });
                },
                { threshold: 0.12, rootMargin: '0px 0px -24px 0px' },
            );

            page?.querySelectorAll('[data-reveal]').forEach((element) =>
                observer?.observe(element),
            );
        }

        function revealFocusedContent(event: FocusEvent) {
            if (event.target instanceof Element) {
                const section = event.target.closest('[data-reveal]');

                if (section) {
                    animations.get(section)?.cancel();
                    animations.delete(section);
                }
            }
        }

        configureReveals();
        updateProgress();
        const resizeObserver = new ResizeObserver(scheduleProgress);
        resizeObserver.observe(page);
        motionPreference.addEventListener('change', configureReveals);
        window.addEventListener('scroll', scheduleProgress, { passive: true });
        window.addEventListener('resize', scheduleProgress);
        page.addEventListener('focusin', revealFocusedContent);

        return () => {
            observer?.disconnect();
            resizeObserver.disconnect();
            animations.forEach((animation) => animation.cancel());

            if (frame !== undefined) {
                window.cancelAnimationFrame(frame);
            }

            motionPreference.removeEventListener('change', configureReveals);
            window.removeEventListener('scroll', scheduleProgress);
            window.removeEventListener('resize', scheduleProgress);
            page.removeEventListener('focusin', revealFocusedContent);
        };
    }, []);

    return pageRef;
}
