<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const props = defineProps({
    vms: Array,
    nodes: Array,
    error: { type: String, default: null },
});

const page = usePage();
const showCreateModal = ref(false);

// Real-time polling
const pollInterval = ref(null);

onMounted(() => {
    pollInterval.value = setInterval(() => {
        router.reload({ only: ['vms'], preserveScroll: true, preserveState: true });
    }, 5000);
});

onUnmounted(() => {
    if (pollInterval.value) clearInterval(pollInterval.value);
});

const createForm = useForm({
    node: props.nodes[0] || '',
    vmid: '',
    name: '',
    memory: 2048,
    cores: 2,
    password: '',
    sshkeys: '',
});

const migrateForm = useForm({
    vmid: null,
    source_node: '',
    target_node: '',
});

const showMigrateModal = ref(false);

const openMigrateModal = (vm) => {
    migrateForm.vmid = vm.vmid;
    migrateForm.source_node = vm.node;
    migrateForm.target_node = props.nodes.find(n => n !== vm.node) || '';
    showMigrateModal.value = true;
};

const submitMigrate = () => {
    migrateForm.post(route('vms.migrate', { vmid: migrateForm.vmid }), {
        onSuccess: () => {
             showMigrateModal.value = false;
             migrateForm.reset();
        }
    });
};

const confirmReinstall = (vmid) => {
    if (confirm('Are you sure you want to reinstall this VM? This will DELETE all data and recreate it.')) {
        form.post(route('vms.reinstall', { vmid }), {
            preserveScroll: true,
        });
    }
};

const submit = () => {
    form.post(route('vms.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const flashSuccess = computed(() => page.props.flash.success);
const flashError = computed(() => page.props.flash.error);

const powerAction = (node, vmid, action) => {
    form.post(route('vms.power', { vmid }), {
        data: { node, action },
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Alerts -->
                <div v-if="error || flashError" class="mb-4 rounded-md bg-red-50 p-4 text-red-700">
                    {{ error || flashError }}
                </div>
                <div v-if="flashSuccess" class="mb-4 rounded-md bg-green-50 p-4 text-green-700">
                    {{ flashSuccess }}
                </div>

                <!-- VM List -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium">Virtual Machines</h3>
                            <PrimaryButton @click="showCreateModal = true">
                                Create VM
                            </PrimaryButton>
                        </div>

                        <div v-if="vms.length === 0" class="text-center py-8 text-gray-500">
                            No VMs found or connection failed.
                        </div>

                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Node</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Memory</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Memory</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPU</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="vm in vms" :key="vm.vmid">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ vm.vmid }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium">{{ vm.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ vm.node }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                            :class="{
                                                'bg-green-100 text-green-800': vm.status === 'running',
                                                'bg-gray-100 text-gray-800': vm.status === 'stopped',
                                            }">
                                            {{ vm.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ (vm.maxmem / 1024 / 1024 / 1024).toFixed(2) }} GB</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ (vm.maxmem / 1024 / 1024 / 1024).toFixed(2) }} GB</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ vm.maxcpu }} vCPUs</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                                    Manage
                                                    <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </template>

                                            <template #content>
                                                <DropdownLink :href="route('vms.show', vm.vmid)">
                                                    Details & Config
                                                </DropdownLink>
                                                <DropdownLink :href="route('vms.console', vm.vmid)">
                                                    Console
                                                </DropdownLink>
                                                <DropdownLink :href="route('vms.firewall', vm.vmid)">
                                                    Firewall
                                                </DropdownLink>
                                                <DropdownLink :href="route('vms.snapshots', vm.vmid)">
                                                    Snapshots
                                                </DropdownLink>
                                                <DropdownLink as="button" @click="powerAction(vm.node, vm.vmid, 'start')">
                                                    Start
                                                </DropdownLink>
                                                <DropdownLink as="button" @click="powerAction(vm.node, vm.vmid, 'stop')">
                                                    Stop (Force)
                                                </DropdownLink>
                                                <DropdownLink as="button" @click="powerAction(vm.node, vm.vmid, 'shutdown')">
                                                    Shutdown
                                                </DropdownLink>
                                                <DropdownLink as="button" @click="powerAction(vm.node, vm.vmid, 'reboot')">
                                                    Reboot
                                                </DropdownLink>
                                                <div class="border-t border-gray-100 dark:border-gray-600"></div>
                                                <DropdownLink as="button" @click="openMigrateModal(vm)">
                                                    Migrate
                                                </DropdownLink>
                                                <DropdownLink as="button" class="text-red-600" @click="confirmReinstall(vm.vmid)">
                                                    Reinstall
                                                </DropdownLink>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create VM Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Create New Virtual Machine
                </h2>

                <div class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="node" value="Node" />
                        <select id="node" v-model="form.node" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option v-for="node in nodes" :key="node" :value="node">{{ node }}</option>
                        </select>
                        <InputError :message="form.errors.node" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="vmid" value="VM ID" />
                        <TextInput id="vmid" v-model="form.vmid" type="number" class="mt-1 block w-full" />
                        <InputError :message="form.errors.vmid" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="memory" value="Memory (MB)" />
                            <TextInput id="memory" v-model="form.memory" type="number" class="mt-1 block w-full" />
                            <InputError :message="form.errors.memory" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="cores" value="Cores" />
                            <TextInput id="cores" v-model="form.cores" type="number" class="mt-1 block w-full" />
                            <InputError :message="form.errors.cores" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="password" value="Cloud-Init Password (Optional)" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" />
                         <InputError :message="form.errors.password" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="sshkeys" value="SSH Keys (Optional)" />
                        <textarea id="sshkeys" v-model="form.sshkeys" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" rows="3"></textarea>
                        <InputError :message="form.errors.sshkeys" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showCreateModal = false"> Cancel </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="submit">
                        Create VM
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Migrate Modal -->
        <Modal :show="showMigrateModal" @close="showMigrateModal = false">
            <div class="p-6">
                 <h2 class="text-lg font-medium text-gray-900">
                    Migrate VM {{ migrateForm.vmid }}
                </h2>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">Current Node: {{ migrateForm.source_node }}</p>
                </div>
                 <div class="mt-6">
                    <InputLabel for="target_node" value="Target Node" />
                    <select id="target_node" v-model="migrateForm.target_node" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option v-for="node in nodes.filter(n => n !== migrateForm.source_node)" :key="node" :value="node">{{ node }}</option>
                    </select>
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showMigrateModal = false"> Cancel </SecondaryButton>
                    <PrimaryButton class="ml-3" :class="{ 'opacity-25': migrateForm.processing }" :disabled="migrateForm.processing || !migrateForm.target_node" @click="submitMigrate">
                        Migrate
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
