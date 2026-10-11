import { describe, expect, it } from 'vitest';
import { pointerTilt, shouldAnimateScene } from './jeepney-hero-motion';

describe('hero pointer parallax', () => {
    const bounds = { left: 100, top: 50, width: 600, height: 450 };

    it('keeps the centered composition neutral', () => {
        expect(pointerTilt(400, 275, bounds)).toEqual({ pitch: 0, yaw: 0 });
    });

    it('limits rotation even when a pointer leaves the visual bounds', () => {
        const upperLeft = pointerTilt(-1000, -1000, bounds);
        const lowerRight = pointerTilt(2000, 2000, bounds);

        expect(upperLeft.pitch).toBeCloseTo(-Math.PI / 60);
        expect(upperLeft.yaw).toBeCloseTo(-Math.PI / 30);
        expect(lowerRight.pitch).toBeCloseTo(Math.PI / 60);
        expect(lowerRight.yaw).toBeCloseTo(Math.PI / 30);
    });

    it('supports resized and offset containers', () => {
        expect(
            pointerTilt(200, 100, { left: 0, top: 0, width: 400, height: 200 }),
        ).toEqual({ pitch: 0, yaw: 0 });
    });

    it('keeps zero-sized containers neutral', () => {
        expect(pointerTilt(400, 275, { ...bounds, width: 0 })).toEqual({
            pitch: 0,
            yaw: 0,
        });
    });
});

describe('hero motion preferences', () => {
    const active = {
        paused: false,
        reducedMotion: false,
        visible: true,
        documentVisible: true,
    };

    it('animates only when the hero is visible and motion is allowed', () => {
        expect(shouldAnimateScene(active)).toBe(true);
    });

    it.each([
        { paused: true },
        { reducedMotion: true },
        { visible: false },
        { documentVisible: false },
    ])('stops motion for %j', (override) => {
        expect(shouldAnimateScene({ ...active, ...override })).toBe(false);
    });
});
