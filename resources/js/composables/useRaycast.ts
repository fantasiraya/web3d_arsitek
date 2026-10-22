import { Vector2, Raycaster } from 'three';

export async function useRaycast(
    event: MouseEvent | TouchEvent | { clientX: number; clientY: number; target?: any; currentTarget?: any },
    camera?: any,
    scene?: any,
    container?: HTMLElement | null
) {
    const clientX = 'clientX' in event
        ? event.clientX
        : ((event as any).touches?.[0]?.clientX ?? (event as any).changedTouches?.[0]?.clientX ?? 0);
    const clientY = 'clientY' in event
        ? event.clientY
        : ((event as any).touches?.[0]?.clientY ?? (event as any).changedTouches?.[0]?.clientY ?? 0);

    const targetEl = container || (event.currentTarget as HTMLElement) || (event.target as HTMLElement);
    const rect = targetEl?.getBoundingClientRect
        ? targetEl.getBoundingClientRect()
        : { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight };

    if (!rect.width || !rect.height) return null;

    const ndcX = ((clientX - rect.left) / rect.width) * 2 - 1;
    const ndcY = -((clientY - rect.top) / rect.height) * 2 + 1;

    if (camera && scene) {
        const mouse = new Vector2(ndcX, ndcY);
        const raycaster = new Raycaster();
        raycaster.setFromCamera(mouse, camera);

        const intersects = raycaster.intersectObjects(scene.children, true);

        // Find first hit that is a visible mesh and not a marker helper
        const hit = intersects.find((i: any) => {
            let obj = i.object;
            if (!obj) return false;
            if (obj.type !== 'Mesh' && obj.type !== 'SkinnedMesh') return false;
            while (obj) {
                if (obj.userData?.isMarker) return false;
                if (obj.name === 'LampLightsGroup' || obj.name === 'SunAmbientLight') return false;
                obj = obj.parent;
            }
            return true;
        });

        if (hit) {
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

        return null;
    }

    return null;
}

export default useRaycast;
