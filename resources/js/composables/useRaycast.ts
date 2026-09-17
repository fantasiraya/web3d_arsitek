import { Vector2, Raycaster } from 'three';

export async function useRaycast(event: MouseEvent, camera?: any, scene?: any) {
    const target = event.target as HTMLElement;
    const rect = target.getBoundingClientRect();
    const ndcX = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    const ndcY = -((event.clientY - rect.top) / rect.height) * 2 + 1;

    if (camera && scene) {
        const mouse = new Vector2(ndcX, ndcY);
        const raycaster = new Raycaster();
        raycaster.setFromCamera(mouse, camera);

        const intersects = raycaster.intersectObjects(scene.children, true);
        if (intersects.length > 0) {
            const hit = intersects[0];
            return {
                x: Number(hit.point.x.toFixed(4)),
                y: Number(hit.point.y.toFixed(4)),
                z: Number(hit.point.z.toFixed(4)),
                normal: {
                    x: Number((hit.face?.normal?.x ?? 0).toFixed(4)),
                    y: Number((hit.face?.normal?.y ?? 0).toFixed(4)),
                    z: Number((hit.face?.normal?.z ?? 0).toFixed(4)),
                },
            };
        }
    }

    return {
        x: Number(ndcX.toFixed(4)),
        y: Number(ndcY.toFixed(4)),
        z: 0,
        normal: { x: 0, y: 1, z: 0 },
    };
}

export default useRaycast;
