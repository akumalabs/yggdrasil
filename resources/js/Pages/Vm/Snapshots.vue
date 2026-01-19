<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    vm: Object,
    snapshots: { type: Array, default: () => [] },
});

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    description: '',
});

const createSnapshot = () => {
    form.post(route('vms.snapshots.store', props.vm.vmid), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const rollbackForm = useForm({});
const rollback = (snapname) => {
    if (!confirm(`Are you sure you want to rollback to '${snapname}'? Current state will be lost!`)) return;
    rollbackForm.post(route('vms.snapshots.rollback', [props.vm.vmid, snapname]));
};

const deleteForm = useForm({});
const remove = (snapname) => {
    if (!confirm(`Delete snapshot '${snapname}'? This cannot be undone.`)) return;
    deleteForm.delete(route('vms.snapshots.destroy', [props.vm.vmid, snapname]));
};

// Proxmox returns a recursive tree for snapshots sometimes.
// But for simplicty we assume the API Client returns a flat list (ProxmoxClient might need adjustments later for trees).
// However, the `snapshot` endpoint usually returns the config tree.
// Let's assume for now we get a list. If not, we might need a recursive component.
// Actually, `getSnapshots` usually returns a list of dictionaries if using the right recursive flat-mapping on backend, 
// or we just render the list we get. 
// A typical Proxmox snapshot list is flat but has 'parent' keys.
// We will just render a table for now.

const snapshotList = computed(() => {
    // Basic filter to remove 'current' if present, or just show all
    return props.snapshots; // .filter(s => s.name !== 'current');
});
</script>

<template>
    <Head :title="`VM ${vm.vmid} - Snapshots`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    VM {{ vm.name }} ({{ vm.vmid }}) - Snapshots
                </h2>
                <div class="space-x-4">
                    <Link :href="route('vms.show', vm.vmid)" class="text-gray-600 hover:text-gray-900">
                        &larr; Back to Details
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                         <div class="flex justify-between mb-4">
                            <h3 class="text-lg font-medium">Snapshots</h3>
                            <PrimaryButton @click="showCreateModal = true">Take Snapshot</PrimaryButton>
                        </div>

                        <div v-if="snapshotList.length === 0" class="text-center py-8 text-gray-500">
                            No snapshots found.
                        </div>

                        <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Name</th>
                                    <th class="px-4 py-2 text-left">Date</th>
                                    <th class="px-4 py-2 text-left">Description</th>
                                    <th class="px-4 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="snap in snapshotList" :key="snap.name">
                                    <td class="px-4 py-2 font-mono font-bold">
                                        {{ snap.name }} <span v-if="snap.snaptime" class="text-xs font-normal text-gray-500"></span>
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ snap.snaptime ? new Date(snap.snaptime * 1000).toLocaleString() : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">{{ snap.description }}</td>
                                    <td class="px-4 py-2 text-right space-x-2">
                                        <SecondaryButton @click="rollback(snap.name)" v-if="snap.name !== 'current'">Rollback</SecondaryButton>
                                        <DangerButton @click="remove(snap.name)" v-if="snap.name !== 'current'">Delete</DangerButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Snapshot Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Take Snapshot</h2>
                
                <form @submit.prevent="createSnapshot" class="space-y-4">
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="e.g. before-update" />
                        <InputError :message="form.errors.name" class="mt-2" />
                        <p class="text-xs text-gray-500 mt-1">Alphanumeric, dashes, underscores only.</p>
                    </div>

                    <div>
                        <InputLabel value="Description" />
                         <TextInput v-model="form.description" class="mt-1 block w-full" placeholder="Optional notes" />
                    </div>

                    <div class="bg-yellow-50 p-3 rounded text-sm text-yellow-800">
                        Warning: Taking a snapshot of a running VM includes RAM, which may take longer.
                    </div>

                    <div class="flex justify-end mt-6">
                        <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ml-3" :disabled="form.processing">Create</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
