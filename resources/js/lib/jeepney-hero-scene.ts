import * as THREE from 'three';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import {
    pointerTilt,
    shouldAnimateScene,
    type MotionState,
} from './jeepney-hero-motion';

export type JeepneyScene = {
    setPaused: (paused: boolean) => void;
    dispose: () => void;
};

type SceneCallbacks = {
    onReady: () => void;
    onUnavailable: () => void;
    onMotionPreference: (reducedMotion: boolean) => void;
};

type Palette = { teal: string; yellow: string; ink: string; cloud: string };

export function createJeepneyScene(
    container: HTMLDivElement,
    callbacks: SceneCallbacks,
): JeepneyScene {
    const scene = new THREE.Scene();
    const fleet = new THREE.Group();
    const geometries = new Map<string, THREE.BufferGeometry>();
    const materials = new Set<THREE.Material>();
    let renderer: THREE.WebGLRenderer | null = null;
    let environment: THREE.WebGLRenderTarget | null = null;
    let frame: number | null = null;
    let disposed = false;
    let ready = false;
    let previousTime = 0;
    let elapsed = 0;
    let targetTilt = { pitch: 0, yaw: 0 };
    const motionPreference = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    );
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    const motion: MotionState = {
        paused: false,
        reducedMotion: motionPreference.matches,
        visible: true,
        documentVisible: document.visibilityState === 'visible',
    };
    const camera = new THREE.OrthographicCamera(-6, 6, 4.5, -4.5, 0.1, 70);
    camera.position.set(10, 7.5, 12);
    camera.lookAt(0, 1, -0.5);
    const cameraTarget = new THREE.Vector3(0, 1, -0.5);
    const resizeObserver = new ResizeObserver(resize);
    const visibilityObserver = new IntersectionObserver(([entry]) => {
        motion.visible = entry.isIntersecting;
        synchronizeAnimation();
    });

    function material(options: THREE.MeshStandardMaterialParameters) {
        const result = new THREE.MeshStandardMaterial(options);
        materials.add(result);
        return result;
    }

    function geometry(key: string, create: () => THREE.BufferGeometry) {
        let result = geometries.get(key);
        if (!result) {
            result = create();
            geometries.set(key, result);
        }
        return result;
    }

    function mesh(
        parent: THREE.Object3D,
        shape: THREE.BufferGeometry,
        surface: THREE.Material,
        x: number,
        y: number,
        z: number,
    ) {
        const result = new THREE.Mesh(shape, surface);
        result.position.set(x, y, z);
        result.castShadow = true;
        result.receiveShadow = true;
        parent.add(result);
        return result;
    }

    function box(
        parent: THREE.Object3D,
        surface: THREE.Material,
        size: [number, number, number],
        position: [number, number, number],
        radius = 0.035,
    ) {
        return mesh(
            parent,
            geometry(`box:${size.join(':')}:${radius}`, () =>
                radius > 0
                    ? new RoundedBoxGeometry(...size, 2, radius)
                    : new THREE.BoxGeometry(...size),
            ),
            surface,
            ...position,
        );
    }

    function cylinder(
        parent: THREE.Object3D,
        surface: THREE.Material,
        radius: number,
        depth: number,
        position: [number, number, number],
        axis: 'x' | 'z',
    ) {
        const result = mesh(
            parent,
            geometry(
                `cylinder:${radius}:${depth}`,
                () => new THREE.CylinderGeometry(radius, radius, depth, 24),
            ),
            surface,
            ...position,
        );
        if (axis === 'x') {
            result.rotation.z = Math.PI / 2;
        } else {
            result.rotation.x = Math.PI / 2;
        }
        return result;
    }

    function rail(
        parent: THREE.Object3D,
        surface: THREE.Material,
        start: [number, number, number],
        end: [number, number, number],
        radius = 0.025,
    ) {
        const from = new THREE.Vector3(...start);
        const to = new THREE.Vector3(...end);
        const direction = to.clone().sub(from);
        const length = direction.length();
        const center = from.add(to).multiplyScalar(0.5);
        const result = mesh(
            parent,
            geometry(
                `rail:${radius}:${length.toFixed(4)}`,
                () => new THREE.CylinderGeometry(radius, radius, length, 8),
            ),
            surface,
            center.x,
            center.y,
            center.z,
        );
        result.quaternion.setFromUnitVectors(
            new THREE.Vector3(0, 1, 0),
            direction.normalize(),
        );
        return result;
    }

    function createJeepney(palette: Palette, color: string, accent: string) {
        const jeepney = new THREE.Group();
        fleet.add(jeepney);
        const paint = material({ color, metalness: 0.32, roughness: 0.27 });
        const trim = material({
            color: accent,
            metalness: 0.2,
            roughness: 0.3,
        });
        const chrome = material({
            color: '#e4edef',
            metalness: 0.92,
            roughness: 0.19,
        });
        const rubber = material({ color: '#182a31', roughness: 0.85 });
        const glass = material({
            color: palette.ink,
            metalness: 0.35,
            roughness: 0.14,
        });
        const headlight = material({
            color: '#fff3ca',
            emissive: '#ffd35b',
            emissiveIntensity: 0.12,
            roughness: 0.25,
        });
        const red = material({ color: '#c95340', roughness: 0.3 });

        box(jeepney, rubber, [1.45, 0.2, 4.5], [0, 0.54, 0]);
        box(jeepney, paint, [1.56, 1.64, 3.46], [0, 1.39, -0.58], 0.08);
        box(jeepney, chrome, [1.6, 0.14, 3.55], [0, 0.64, -0.58]);
        box(jeepney, paint, [1.4, 0.53, 1.45], [0, 1.08, 1.8], 0.09);
        box(jeepney, chrome, [1.48, 0.075, 1.5], [0, 1.35, 1.8]);
        box(jeepney, chrome, [1.78, 0.16, 3.75], [0, 2.24, -0.58], 0.07);
        box(jeepney, paint, [1.73, 0.045, 3.68], [0, 2.34, -0.58]);

        box(jeepney, chrome, [1.45, 0.78, 0.035], [0, 1.77, 1.166]);
        for (const side of [-1, 1]) {
            box(
                jeepney,
                glass,
                [0.64, 0.64, 0.035],
                [side * 0.355, 1.78, 1.19],
            );
            rail(
                jeepney,
                rubber,
                [side * 0.1, 1.5, 1.22],
                [side * 0.52, 1.61, 1.22],
                0.013,
            );
            for (const z of [0.71, 0.03, -0.65, -1.33, -1.99]) {
                box(
                    jeepney,
                    chrome,
                    [0.035, 0.73, 0.6],
                    [side * 0.795, 1.77, z],
                );
                box(
                    jeepney,
                    glass,
                    [0.025, 0.62, 0.49],
                    [side * 0.819, 1.79, z],
                );
            }
            box(
                jeepney,
                trim,
                [0.028, 0.08, 3.47],
                [side * 0.792, 1.29, -0.58],
            );
            box(
                jeepney,
                chrome,
                [0.035, 0.025, 3.48],
                [side * 0.811, 1.2, -0.58],
            );
            box(jeepney, chrome, [0.23, 0.1, 2.8], [side * 0.84, 0.56, -0.62]);
            rail(
                jeepney,
                chrome,
                [side * 0.68, 1.65, 1.07],
                [side * 0.99, 1.88, 1.32],
            );
            box(jeepney, chrome, [0.21, 0.28, 0.1], [side * 1.01, 1.92, 1.34]);
            box(
                jeepney,
                glass,
                [0.17, 0.24, 0.018],
                [side * 1.01, 1.92, 1.399],
            );

            for (const z of [1.61, -1.6]) {
                cylinder(
                    jeepney,
                    rubber,
                    0.44,
                    0.27,
                    [side * 0.82, 0.45, z],
                    'x',
                );
                cylinder(
                    jeepney,
                    chrome,
                    0.285,
                    0.04,
                    [side * 0.978, 0.45, z],
                    'x',
                );
                cylinder(
                    jeepney,
                    rubber,
                    0.185,
                    0.045,
                    [side * 1.005, 0.45, z],
                    'x',
                );
                cylinder(
                    jeepney,
                    chrome,
                    0.105,
                    0.055,
                    [side * 1.025, 0.45, z],
                    'x',
                );
                const fender = mesh(
                    jeepney,
                    geometry(
                        'fender',
                        () =>
                            new THREE.TorusGeometry(
                                0.49,
                                0.075,
                                6,
                                20,
                                Math.PI,
                            ),
                    ),
                    chrome,
                    side * 0.86,
                    0.45,
                    z,
                );
                fender.rotation.y = Math.PI / 2;
            }

            rail(
                jeepney,
                chrome,
                [side * 0.65, 2.56, -2.12],
                [side * 0.65, 2.56, 0.94],
            );
            for (const z of [-2.08, -0.62, 0.9]) {
                rail(
                    jeepney,
                    chrome,
                    [side * 0.65, 2.33, z],
                    [side * 0.65, 2.56, z],
                );
            }
            cylinder(
                jeepney,
                chrome,
                0.2,
                0.09,
                [side * 0.57, 1.02, 2.55],
                'z',
            );
            cylinder(
                jeepney,
                headlight,
                0.15,
                0.025,
                [side * 0.57, 1.02, 2.611],
                'z',
            );
            box(jeepney, trim, [0.16, 0.1, 0.035], [side * 0.59, 0.77, 2.57]);
            box(jeepney, red, [0.12, 0.21, 0.04], [side * 0.63, 0.95, -2.337]);
        }

        for (const z of [-2.08, -1.3, -0.52, 0.26, 0.9]) {
            rail(jeepney, chrome, [-0.65, 2.46, z], [0.65, 2.46, z]);
        }
        box(jeepney, chrome, [0.82, 0.57, 0.1], [0, 1.02, 2.56], 0.045);
        for (let x = -0.3; x <= 0.3; x += 0.1) {
            box(jeepney, rubber, [0.039, 0.4, 0.016], [x, 1.02, 2.617], 0.014);
        }
        box(jeepney, chrome, [1.87, 0.17, 0.19], [0, 0.58, 2.68]);
        rail(jeepney, chrome, [-0.79, 0.75, 2.65], [0.79, 0.75, 2.65], 0.045);
        box(jeepney, trim, [0.37, 0.14, 0.025], [0, 0.57, 2.787]);
        box(jeepney, chrome, [0.91, 0.28, 0.1], [0, 2.31, 1.32]);
        box(jeepney, trim, [0.77, 0.16, 0.02], [0, 2.31, 1.38]);
        box(jeepney, glass, [0.78, 1.18, 0.025], [0, 1.4, -2.326]);
        box(jeepney, chrome, [0.85, 0.1, 0.32], [0, 0.57, -2.47]);
        cylinder(jeepney, rubber, 0.33, 0.18, [0.49, 1.39, -2.43], 'z');
        cylinder(jeepney, chrome, 0.19, 0.03, [0.49, 1.39, -2.54], 'z');
        return jeepney;
    }

    function stopAnimation() {
        if (frame !== null) {
            window.cancelAnimationFrame(frame);
            frame = null;
        }
        previousTime = 0;
    }

    function render() {
        if (
            disposed ||
            !renderer ||
            !motion.visible ||
            !motion.documentVisible
        ) {
            return;
        }
        renderer.render(scene, camera);
        if (!ready) {
            ready = true;
            callbacks.onReady();
        }
    }

    function animate(time: number) {
        frame = null;
        if (disposed || !shouldAnimateScene(motion)) {
            return;
        }
        const delta =
            previousTime === 0
                ? 0
                : Math.min((time - previousTime) / 1000, 0.05);
        previousTime = time;
        elapsed += delta;
        const easing = 1 - Math.exp(-5 * delta);
        scene.rotation.x += (targetTilt.pitch - scene.rotation.x) * easing;
        const yaw = THREE.MathUtils.clamp(
            targetTilt.yaw + Math.sin(elapsed * 0.65) * 0.012,
            -Math.PI / 30,
            Math.PI / 30,
        );
        scene.rotation.y += (yaw - scene.rotation.y) * easing;
        render();
        frame = window.requestAnimationFrame(animate);
    }

    function synchronizeAnimation() {
        stopAnimation();
        if (disposed) {
            return;
        }
        if (shouldAnimateScene(motion)) {
            frame = window.requestAnimationFrame(animate);
        } else {
            render();
        }
    }

    function resize() {
        if (disposed || !renderer) {
            return;
        }
        const { width, height } = container.getBoundingClientRect();
        if (width === 0 || height === 0) {
            return;
        }
        const aspect = width / height;
        const halfHeight = 4.4;
        camera.left = -halfHeight * aspect;
        camera.right = halfHeight * aspect;
        camera.top = halfHeight;
        camera.bottom = -halfHeight;
        camera.updateProjectionMatrix();
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(width, height, false);
        render();
    }

    function pointerMove(event: PointerEvent) {
        if (
            event.pointerType !== 'mouse' ||
            !finePointer.matches ||
            !shouldAnimateScene(motion)
        ) {
            return;
        }
        targetTilt = pointerTilt(
            event.clientX,
            event.clientY,
            container.getBoundingClientRect(),
        );
    }

    function pointerLeave() {
        targetTilt = { pitch: 0, yaw: 0 };
    }

    function preferenceChanged() {
        motion.reducedMotion = motionPreference.matches;
        callbacks.onMotionPreference(motion.reducedMotion);
        if (motion.reducedMotion) {
            targetTilt = { pitch: 0, yaw: 0 };
            scene.rotation.set(0, 0, 0);
        }
        synchronizeAnimation();
    }

    function documentVisibilityChanged() {
        motion.documentVisible = document.visibilityState === 'visible';
        synchronizeAnimation();
    }

    function contextLost(event: Event) {
        event.preventDefault();
        dispose();
        callbacks.onUnavailable();
    }

    function dispose() {
        if (disposed) {
            return;
        }
        disposed = true;
        stopAnimation();
        resizeObserver.disconnect();
        visibilityObserver.disconnect();
        motionPreference.removeEventListener('change', preferenceChanged);
        document.removeEventListener(
            'visibilitychange',
            documentVisibilityChanged,
        );
        container.removeEventListener('pointermove', pointerMove);
        container.removeEventListener('pointerleave', pointerLeave);
        renderer?.domElement.removeEventListener(
            'webglcontextlost',
            contextLost,
        );
        geometries.forEach((resource) => resource.dispose());
        materials.forEach((resource) => resource.dispose());
        scene.traverse((object) => {
            if (object instanceof THREE.DirectionalLight) {
                object.shadow.dispose();
            }
        });
        environment?.dispose();
        renderer?.dispose();
        renderer?.domElement.remove();
    }

    try {
        const styles = getComputedStyle(container);
        const palette: Palette = {
            teal: styles.getPropertyValue('--pt-teal').trim(),
            yellow: styles.getPropertyValue('--pt-yellow').trim(),
            ink: styles.getPropertyValue('--pt-ink').trim(),
            cloud: styles.getPropertyValue('--pt-cloud').trim(),
        };
        renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        renderer.setClearColor(0x000000, 0);
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.15;
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFShadowMap;
        renderer.domElement.style.width = '100%';
        renderer.domElement.style.height = '100%';
        renderer.domElement.setAttribute('aria-hidden', 'true');
        container.append(renderer.domElement);

        const room = new RoomEnvironment();
        const generator = new THREE.PMREMGenerator(renderer);
        try {
            environment = generator.fromScene(room, 0.04);
            scene.environment = environment.texture;
        } finally {
            room.dispose();
            generator.dispose();
        }

        scene.add(fleet);
        const front = createJeepney(palette, palette.teal, palette.yellow);
        front.position.set(-0.8, 0.06, 0.95);
        front.rotation.y = -0.18;
        const rear = createJeepney(palette, palette.yellow, palette.teal);
        rear.position.set(1.17, 0.06, -2.25);
        rear.rotation.y = -0.28;
        rear.scale.setScalar(0.83);

        const stage = box(
            scene,
            material({ color: palette.cloud, roughness: 0.95 }),
            [6.4, 0.13, 7.5],
            [0.05, -0.1, -0.45],
            0.065,
        );
        stage.castShadow = false;
        const shadow = new THREE.ShadowMaterial({ opacity: 0.13 });
        materials.add(shadow);
        const ground = mesh(
            scene,
            geometry('ground', () => new THREE.PlaneGeometry(200, 200)),
            shadow,
            0,
            -0.18,
            0,
        );
        ground.rotation.x = -Math.PI / 2;
        ground.castShadow = false;

        scene.add(new THREE.HemisphereLight('#ffffff', '#c5d7dc', 2.5));
        const light = new THREE.DirectionalLight('#fff9ea', 3.4);
        light.position.set(-3, 9, 6);
        light.castShadow = true;
        light.shadow.mapSize.set(1024, 1024);
        light.shadow.radius = 3;
        light.shadow.camera.left = -8;
        light.shadow.camera.right = 8;
        light.shadow.camera.top = 8;
        light.shadow.camera.bottom = -8;
        light.shadow.normalBias = 0.035;
        light.shadow.bias = -0.0001;
        scene.add(light);
        const fill = new THREE.DirectionalLight('#d6f2f5', 1.7);
        fill.position.set(5, 5, -4);
        scene.add(fill);

        const bounds = new THREE.Box3().setFromObject(fleet);
        const center = bounds.getCenter(new THREE.Vector3());
        camera.position.add(center.clone().sub(cameraTarget));
        camera.lookAt(center);
        resizeObserver.observe(container);
        visibilityObserver.observe(container);
        motionPreference.addEventListener('change', preferenceChanged);
        document.addEventListener(
            'visibilitychange',
            documentVisibilityChanged,
        );
        container.addEventListener('pointermove', pointerMove);
        container.addEventListener('pointerleave', pointerLeave);
        renderer.domElement.addEventListener('webglcontextlost', contextLost);
        callbacks.onMotionPreference(motion.reducedMotion);
        resize();
        synchronizeAnimation();
    } catch (error) {
        dispose();
        throw error;
    }

    return {
        setPaused(paused) {
            motion.paused = paused;
            synchronizeAnimation();
        },
        dispose,
    };
}
