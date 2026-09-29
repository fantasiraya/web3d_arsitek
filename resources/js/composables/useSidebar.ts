import { ref, computed } from 'vue'

const isSidebarOpen = ref(true)
const isMobile = ref(false)

export function useSidebar() {
    const toggleSidebar = () => {
        isSidebarOpen.value = !isSidebarOpen.value
    }

    const openSidebar = () => {
        isSidebarOpen.value = true
    }

    const closeSidebar = () => {
        isSidebarOpen.value = false
    }

    const setMobile = (value: boolean) => {
        isMobile.value = value
    }

    return {
        isSidebarOpen: computed(() => isSidebarOpen.value),
        isMobile: computed(() => isMobile.value),
        toggleSidebar,
        openSidebar,
        closeSidebar,
        setMobile
    }
}
