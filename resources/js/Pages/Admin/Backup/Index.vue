<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    vm: Object,
    backups: Array,
});

const creating = ref(false);
const restoring = ref(null);

const createBackup = () => {
    creating.value = true;
    router.post(`/admin/vms/${props.vm.vmid}/backups`, {}, {
        onFinish: () => creating.value = false,
    });
};

const restoreBackup = (backup) => {
    if (!confirm(`Restore from backup ${backup.volid}? This will create a new VM.`)) return;
    
    restoring.value = backup.volid;
    router.post('/admin/backups/restore', {
        archive: backup.volid,
        node: props.vm.node,
    }, {
        onFinish: () => restoring.value = null,
    });
};

const deleteBackup = (backup) => {
    if (!confirm(`Delete backup ${backup.volid}? This action cannot be undone.`)) return;
    
    router.delete('/admin/backups', {
        data: {
            node: props.vm.node,
            storage: 'local',
            volid: backup.volid,
        },
    });
};

const formatBytes = (bytes) => {
    if (!bytes) return 'N/A';
    const gb = bytes / 1024 / 1024 / 1024;
    return gb.toFixed(2) + ' GB';
};

const formatDate = (timestamp) => {
    if (!timestamp) return 'N/A';
    return new Date(timestamp * 1000).toLocaleString();
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Backups for VM {{ vm.vmid }} - {{ vm.name }}
                </h2>
                <PrimaryButton @click="createBackup" :disabled="creating">
                    {{ creating ? 'Creating...' : 'Create Backup' }}
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="backups.length === 0" class="text-center py-12">
                            <p class="text-gray-500">No backups found for this VM.</p>
                            <p class="text-sm text-gray-400 mt-2">Click "Create Backup" to create your first backup.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Backup ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="backup in backups" :key="backup.volid" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                                            {{ backup.volid }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(backup.ctime) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatBytes(backup.size) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <SecondaryButton 
                                                @click="restoreBackup(backup)"
                                                :disabled="restoring === backup.volid"
                                                class="inline-flex"
                                            >
                                                {{ restoring === backup.volid ? 'Restoring...' : 'Restore' }}
                                            </SecondaryButton>
                                            <button
                                                @click="deleteBackup(backup)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
