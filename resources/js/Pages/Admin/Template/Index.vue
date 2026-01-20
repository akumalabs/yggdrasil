<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    templates: Array,
});

const convertingVmid = ref(null);

const convertToTemplate = (vmid) => {
    if (!confirm(`Convert VM ${vmid} to template? This action is irreversible!`)) return;
    
    convertingVmid.value = vmid;
    router.post('/admin/templates', { vmid }, {
        onFinish: () => convertingVmid.value = null,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Template Management
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="templates.length === 0" class="text-center py-12">
                            <p class="text-gray-500">No templates found. Convert a VM to create a template.</p>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="template in templates"
                                :key="template.vmid"
                                class="border rounded-lg p-4 hover:shadow-md transition"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="font-semibold text-lg">{{ template.name }}</h3>
                                        <p class="text-sm text-gray-600">VMID: {{ template.vmid }}</p>
                                        <p class="text-sm text-gray-600">Node: {{ template.node }}</p>
                                    </div>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded">
                                        TEMPLATE
                                    </span>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span class="text-gray-600">CPU:</span>
                                        <span class="font-semibold ml-1">{{ template.maxcpu || 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">RAM:</span>
                                        <span class="font-semibold ml-1">
                                            {{ template.maxmem ? (template.maxmem / 1024 / 1024 / 1024).toFixed(0) + ' GB' : 'N/A' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Disk:</span>
                                        <span class="font-semibold ml-1">
                                            {{ template.maxdisk ? (template.maxdisk / 1024 / 1024 / 1024).toFixed(0) + ' GB' : 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
