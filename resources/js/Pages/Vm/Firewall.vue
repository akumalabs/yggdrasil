<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';

const props = defineProps({
    vm: Object,
    rules: { type: Array, default: () => [] },
});

const showAddModal = ref(false);

const form = useForm({
    type: 'in',
    action: 'ACCEPT',
    proto: '',
    source: '',
    dest: '',
    sport: '',
    dport: '',
    comment: '',
});

const submit = () => {
    form.post(route('vms.firewall.store', props.vm.vmid), {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        }
    });
};
</script>

<template>
    <Head :title="`VM ${vm.vmid} - Firewall`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    VM {{ vm.name }} ({{ vm.vmid }}) - Firewall
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
                <!-- Rules List -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                         <div class="flex justify-between mb-4">
                            <h3 class="text-lg font-medium">Firewall Rules</h3>
                            <PrimaryButton @click="showAddModal = true">Add Rule</PrimaryButton>
                        </div>

                        <div v-if="rules.length === 0" class="text-center py-8 text-gray-500">
                            No rules defined.
                        </div>

                        <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Type</th>
                                    <th class="px-4 py-2 text-left">Action</th>
                                    <th class="px-4 py-2 text-left">Proto</th>
                                    <th class="px-4 py-2 text-left">Source</th>
                                    <th class="px-4 py-2 text-left">Dest</th>
                                    <th class="px-4 py-2 text-left">Ports</th>
                                    <th class="px-4 py-2 text-left">Comment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(rule, index) in rules" :key="index">
                                    <td class="px-4 py-2 uppercase font-mono">{{ rule.type }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded text-xs font-bold"
                                            :class="{
                                                'bg-green-100 text-green-800': rule.action === 'ACCEPT',
                                                'bg-red-100 text-red-800': rule.action === 'DROP' || rule.action === 'REJECT'
                                            }">
                                            {{ rule.action }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">{{ rule.proto || 'any' }}</td>
                                    <td class="px-4 py-2">{{ rule.source || 'any' }}</td>
                                    <td class="px-4 py-2">{{ rule.dest || 'any' }}</td>
                                    <td class="px-4 py-2">{{ rule.dport || rule.sport ? (rule.dport || '*') + ':' + (rule.sport || '*') : 'any' }}</td>
                                    <td class="px-4 py-2 text-gray-500 italic">{{ rule.comment }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Rule Modal -->
        <Modal :show="showAddModal" @close="showAddModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Add Firewall Rule</h2>
                
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Type" />
                            <select v-model="form.type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="in">Inbound (IN)</option>
                                <option value="out">Outbound (OUT)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Action" />
                            <select v-model="form.action" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="ACCEPT">ACCEPT</option>
                                <option value="DROP">DROP</option>
                                <option value="REJECT">REJECT</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Protocol" />
                            <select v-model="form.proto" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Any</option>
                                <option value="tcp">TCP</option>
                                <option value="udp">UDP</option>
                                <option value="icmp">ICMP</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <InputLabel value="Source IP/CIDR" />
                            <TextInput v-model="form.source" class="mt-1 block w-full" placeholder="e.g. 192.168.1.0/24" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                         <div>
                            <InputLabel value="Dest Port" />
                            <TextInput v-model="form.dport" class="mt-1 block w-full" placeholder="e.g. 80, 443" />
                        </div>
                        <div>
                            <InputLabel value="Source Port" />
                            <TextInput v-model="form.sport" class="mt-1 block w-full" placeholder="Usually empty" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Comment" />
                         <TextInput v-model="form.comment" class="mt-1 block w-full" />
                    </div>

                    <div class="flex justify-end mt-6">
                        <SecondaryButton @click="showAddModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ml-3" :disabled="form.processing">Add Rule</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
