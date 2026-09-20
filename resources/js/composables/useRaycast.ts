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
        
        // Find first hit that is not a marker helper
        const hit = intersects.find((i: any) => {
            let obj = i.object;
            while (obj) {
                if (obj.userData?.isMarker) return false;
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
