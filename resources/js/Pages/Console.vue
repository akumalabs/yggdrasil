<script setup>
import { onMounted, ref, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RFB from '@novnc/novnc/lib/rfb';

const props = defineProps({
    vmid: Number,
    node: String,
    host: String,
    ticket: String,
    port: Number,
    cert: String,
    user: String,
});

const screen = ref(null);
const status = ref('Connecting...');
let rfb = null;

const connect = () => {
    if (!props.ticket || !props.host) {
        status.value = 'Missing connection details.';
        return;
    }

    // Encrypting traffic (wss) requires valid certs on Proxmox or browser exception
    // The URL format for Proxmox VNC
    const url = `wss://${props.host}:8006/api2/json/nodes/${props.node}/qemu/${props.vmid}/vncwebsocket?port=${props.port}&vncticket=${encodeURIComponent(props.ticket)}`;

    try {
        rfb = new RFB(screen.value, url, {
            credentials: { password: props.ticket } // Proxmox usually doesn't need password here if ticket is in URL, but sometimes requires it or just the ticket
        });

        rfb.addEventListener("connect",  () => { status.value = 'Connected'; });
        rfb.addEventListener("disconnect", () => { status.value = 'Disconnected'; });
        rfb.addEventListener("securityfailure", () => { status.value = 'Security Negotiation Failed'; });

        // Proxmox noVNC implementation expects the ticket as the password sometimes?
        // Actually, Proxmox uses the 'vncticket' query param.
        
    } catch (e) {
        status.value = 'Connection Error: ' + e.message;
    }
};

onMounted(() => {
    setTimeout(connect, 500); // Give DOM a moment
});

onUnmounted(() => {
    if (rfb) {
        rfb.disconnect();
    }
});
</script>

<template>
    <Head title="Console" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Console: VM {{ vmid }} ({{ node }})
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4 bg-gray-100 border-b border-gray-200 flex justify-between items-center">
                        <span class="font-bold">Status: {{ status }}</span>
                        <div class="text-sm text-gray-500">
                            Note: If connection fails, ensure you have accepted the certificate at <a :href="`https://${host}:8006`" target="_blank" class="text-blue-600 underline">https://{{ host }}:8006</a>
                        </div>
                    </div>
                    
                    <div class="p-6 bg-black flex justify-center">
                        <div ref="screen" class="w-full h-[600px] bg-black">
                            <!-- VNC Canvas will be injected here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
