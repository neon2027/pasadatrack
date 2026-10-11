export type MotionState = {
    paused: boolean;
    reducedMotion: boolean;
    visible: boolean;
    documentVisible: boolean;
};

export function shouldAnimateScene(state: MotionState): boolean {
    return (
        !state.paused &&
        !state.reducedMotion &&
        state.visible &&
        state.documentVisible
    );
}

export function pointerTilt(
    clientX: number,
    clientY: number,
    bounds: { left: number; top: number; width: number; height: number },
): { pitch: number; yaw: number } {
    if (bounds.width <= 0 || bounds.height <= 0) {
        return { pitch: 0, yaw: 0 };
    }

    const horizontal = Math.max(
        -1,
        Math.min(1, ((clientX - bounds.left) / bounds.width) * 2 - 1),
    );
    const vertical = Math.max(
        -1,
        Math.min(1, ((clientY - bounds.top) / bounds.height) * 2 - 1),
    );

    return {
        pitch: vertical * (Math.PI / 60),
        yaw: horizontal * (Math.PI / 30),
    };
}
