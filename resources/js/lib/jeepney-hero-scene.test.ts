import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as THREE from 'three';
import { createJeepneyScene, type JeepneyScene } from './jeepney-hero-scene';

const harness = vi.hoisted(() => ({
    failRenderer: false,
    createCanvas: (): EventTarget => new EventTarget(),
    rendered: (_scene: unknown, _camera: unknown): void => {},
}));

vi.mock('three', async (importOriginal) => {
    const actual = await importOriginal<typeof import('three')>();

    return {
        ...actual,
        WebGLRenderer: class {
            constructor() {
                if (harness.failRenderer) {
                    throw new Error('WebGL is unavailable');
                }
            }

            domElement = harness.createCanvas();
            shadowMap = {};
            setClearColor = vi.fn();
            setPixelRatio = vi.fn();
            setSize = vi.fn();
            dispose = vi.fn();
            render = harness.rendered;
        },
        PMREMGenerator: class {
            fromScene = () => ({
                texture: new actual.Texture(),
                dispose: vi.fn(),
            });
            dispose = vi.fn();
        },
    };
});

describe('jeepney hero runtime', () => {
    let frames: Map<number, FrameRequestCallback>;
    let canvases: EventTarget[];
    let runtimes: JeepneyScene[];
    let container: EventTarget;
    let documentTarget: EventTarget & { visibilityState: string };
    let reducedMotion: EventTarget & { matches: boolean };
    let finePointer: EventTarget & { matches: boolean };
    let changeVisibility: (visible: boolean) => void;
    let renderedScene: THREE.Scene;
    let renderedCamera: THREE.Camera;
    let now: number;

    function createRuntime() {
        const callbacks = {
            onReady: vi.fn(),
            onUnavailable: vi.fn(),
            onMotionPreference: vi.fn(),
        };
        const runtime = createJeepneyScene(
            container as HTMLDivElement,
            callbacks,
        );
        runtimes.push(runtime);
        return { runtime, callbacks };
    }

    function advanceFrames(count = 1) {
        for (let step = 0; step < count; step++) {
            const pending = [...frames.values()];
            frames.clear();
            now += 16;
            pending.forEach((callback) => callback(now));
        }
    }

    function movePointer() {
        container.dispatchEvent(
            Object.assign(new Event('pointermove'), {
                pointerType: 'mouse',
                clientX: 700,
                clientY: 500,
            }),
        );
    }

    function fleet() {
        return renderedScene.children.find(
            (object): object is THREE.Group => object instanceof THREE.Group,
        )!;
    }

    beforeEach(() => {
        frames = new Map();
        canvases = [];
        runtimes = [];
        now = 0;
        let frameId = 0;
        container = Object.assign(new EventTarget(), {
            getBoundingClientRect: () => ({
                left: 100,
                top: 50,
                width: 600,
                height: 450,
            }),
            append: (canvas: EventTarget) => canvases.push(canvas),
        });
        harness.failRenderer = false;
        harness.createCanvas = () => {
            const canvas = Object.assign(new EventTarget(), {
                style: {},
                setAttribute: vi.fn(),
                remove: () => {
                    canvases = canvases.filter((item) => item !== canvas);
                },
            });
            return canvas;
        };
        harness.rendered = (scene, camera) => {
            renderedScene = scene as THREE.Scene;
            renderedCamera = camera as THREE.Camera;
            renderedScene.updateMatrixWorld(true);
            renderedCamera.updateMatrixWorld(true);
        };
        documentTarget = Object.assign(new EventTarget(), {
            visibilityState: 'visible',
        });
        reducedMotion = Object.assign(new EventTarget(), { matches: false });
        finePointer = Object.assign(new EventTarget(), { matches: true });
        vi.stubGlobal('document', documentTarget);
        vi.stubGlobal('window', {
            devicePixelRatio: 3,
            matchMedia: (query: string) =>
                query.includes('reduced-motion') ? reducedMotion : finePointer,
            requestAnimationFrame: (callback: FrameRequestCallback) => {
                frames.set(++frameId, callback);
                return frameId;
            },
            cancelAnimationFrame: (id: number) => frames.delete(id),
        });
        vi.stubGlobal('getComputedStyle', () => ({
            getPropertyValue: (name: string) =>
                ({
                    '--pt-teal': '#087f79',
                    '--pt-yellow': '#ffd35b',
                    '--pt-ink': '#102d3a',
                    '--pt-cloud': '#eaf2f6',
                })[name],
        }));
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe = vi.fn();
                disconnect = vi.fn();
            },
        );
        vi.stubGlobal(
            'IntersectionObserver',
            class {
                constructor(callback: IntersectionObserverCallback) {
                    changeVisibility = (visible) =>
                        callback(
                            [
                                {
                                    isIntersecting: visible,
                                } as IntersectionObserverEntry,
                            ],
                            this as unknown as IntersectionObserver,
                        );
                }
                observe = vi.fn();
                disconnect = vi.fn();
            },
        );
    });

    afterEach(() => {
        runtimes.forEach((runtime) => runtime.dispose());
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('announces readiness only after rendering a scene', () => {
        const { callbacks } = createRuntime();

        expect(callbacks.onReady).toHaveBeenCalledOnce();
        expect(canvases).toHaveLength(1);
        expect(fleet().children).toHaveLength(2);
        advanceFrames(3);
        expect(callbacks.onReady).toHaveBeenCalledOnce();
    });

    it('freezes parallax and idle motion while paused, then resumes', () => {
        const { runtime } = createRuntime();
        movePointer();
        advanceFrames(30);
        const rotation = renderedScene.rotation.clone();

        runtime.setPaused(true);
        container.dispatchEvent(new Event('pointerleave'));
        movePointer();
        advanceFrames(30);
        expect(frames.size).toBe(0);
        expect(renderedScene.rotation.equals(rotation)).toBe(true);

        runtime.setPaused(false);
        expect(frames.size).toBe(1);
        advanceFrames(10);
        expect(renderedScene.rotation.equals(rotation)).toBe(false);
    });

    it('renders without animation when reduced motion is requested', () => {
        reducedMotion.matches = true;
        const { callbacks } = createRuntime();
        movePointer();
        advanceFrames(30);

        expect(callbacks.onMotionPreference).toHaveBeenCalledWith(true);
        expect(frames.size).toBe(0);
        expect(renderedScene.rotation.x).toBe(0);
        expect(renderedScene.rotation.y).toBe(0);
    });

    it('responds to reduced-motion changes while the scene is mounted', () => {
        createRuntime();
        movePointer();
        advanceFrames(30);
        reducedMotion.matches = true;
        reducedMotion.dispatchEvent(new Event('change'));

        expect(frames.size).toBe(0);
        expect(renderedScene.rotation.x).toBe(0);
        expect(renderedScene.rotation.y).toBe(0);

        reducedMotion.matches = false;
        reducedMotion.dispatchEvent(new Event('change'));
        expect(frames.size).toBe(1);
    });

    it('suspends rendering offscreen and in hidden tabs', () => {
        createRuntime();
        changeVisibility(false);
        expect(frames.size).toBe(0);
        documentTarget.visibilityState = 'hidden';
        documentTarget.dispatchEvent(new Event('visibilitychange'));
        changeVisibility(true);
        expect(frames.size).toBe(0);
        documentTarget.visibilityState = 'visible';
        documentTarget.dispatchEvent(new Event('visibilitychange'));
        expect(frames.size).toBe(1);
    });

    it('keeps touch-device composition centered', () => {
        finePointer.matches = false;
        createRuntime();
        movePointer();
        advanceFrames(30);

        expect(renderedScene.rotation.x).toBe(0);
        expect(Math.abs(renderedScene.rotation.y)).toBeLessThan(0.012);
    });

    it('cleans up before a Strict Mode remount without duplicate canvases', () => {
        const { runtime } = createRuntime();
        const geometryDisposed = vi.spyOn(
            THREE.BufferGeometry.prototype,
            'dispose',
        );
        runtime.dispose();
        runtime.dispose();
        movePointer();
        documentTarget.dispatchEvent(new Event('visibilitychange'));

        expect(frames.size).toBe(0);
        expect(canvases).toHaveLength(0);
        expect(geometryDisposed).toHaveBeenCalled();
        createRuntime();
        expect(canvases).toHaveLength(1);
        expect(frames.size).toBe(1);
    });

    it('releases the canvas and requests the fallback on context loss', () => {
        const { callbacks } = createRuntime();
        canvases[0].dispatchEvent(
            new Event('webglcontextlost', { cancelable: true }),
        );

        expect(callbacks.onUnavailable).toHaveBeenCalledOnce();
        expect(canvases).toHaveLength(0);
        expect(frames.size).toBe(0);
    });

    it('leaves no canvas or animation when WebGL initialization fails', () => {
        harness.failRenderer = true;

        expect(createRuntime).toThrow('WebGL is unavailable');
        expect(canvases).toHaveLength(0);
        expect(frames.size).toBe(0);
    });

    it('fits both jeepneys inside the camera at every parallax extreme', () => {
        createRuntime();
        for (const pitch of [-Math.PI / 60, 0, Math.PI / 60]) {
            for (const yaw of [-Math.PI / 30, 0, Math.PI / 30]) {
                renderedScene.rotation.set(pitch, yaw, 0);
                renderedScene.updateMatrixWorld(true);
                fleet().traverse((object) => {
                    if (!(object instanceof THREE.Mesh)) {
                        return;
                    }
                    object.geometry.computeBoundingBox();
                    const bounds = object.geometry.boundingBox!;
                    for (const x of [bounds.min.x, bounds.max.x]) {
                        for (const y of [bounds.min.y, bounds.max.y]) {
                            for (const z of [bounds.min.z, bounds.max.z]) {
                                const point = new THREE.Vector3(x, y, z)
                                    .applyMatrix4(object.matrixWorld)
                                    .project(renderedCamera);
                                expect(Math.abs(point.x)).toBeLessThan(0.95);
                                expect(Math.abs(point.y)).toBeLessThan(0.95);
                            }
                        }
                    }
                });
            }
        }
    });
});
