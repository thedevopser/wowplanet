import { onMounted, onUpdated, nextTick } from 'vue';

export function useWowheadTooltips() {
    const refresh = () => {
        if (window.$WowheadPower?.refreshLinks) {
            window.$WowheadPower.refreshLinks();
        }
    };

    onMounted(() => nextTick(refresh));
    onUpdated(() => nextTick(refresh));

    return { refreshWowheadLinks: refresh };
}
