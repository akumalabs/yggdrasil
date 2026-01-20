<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    templates: Array,
    users: Array,
    nodes: Array,
});

const form = ref({
    user_id: '',
    template_vmid: '',
    name: '',
    hostname: '',
    cores: 2,
    memory: 4,
    disk: 40,
    bandwidth: 2,
    password: '',
});

const processing = ref(false);

const submit = () => {
    processing.value = true;
    router.post('/admin/vms', form.value, {
        onFinish: () => processing.value = false,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create Virtual Machine
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- User Assignment -->
                        <div>
                            <InputLabel for="user_id" value="Assign to User" />
                            <select
                                v-model="form.user_id"
                                id="user_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Select User...</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>

                        <!-- Template Selection -->
                        <div>
                            <InputLabel for="template" value="Template" />
                            <select
                                v-model="form.template_vmid"
                                id="template"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Select Template...</option>
                                <option v-for="template in templates" :key="template.vmid" :value="template.vmid">
                                    VMID {{ template.vmid }} - {{ template.name }}
                                </option>
                            </select>
                        </div>

                        <!-- VM Name -->
                        <div>
                            <InputLabel for="name" value="VM Name (Display)" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <p class="text-sm text-gray-600 mt-1">Friendly name shown in the panel</p>
                        </div>

                        <!-- Hostname -->
                        <div>
                            <InputLabel for="hostname" value="Hostname (optional)" />
                            <TextInput
                                id="hostname"
                                v-model="form.hostname"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Auto-filled from name if empty"
                            />
                            <p class="text-sm text-gray-600 mt-1">DNS-compliant hostname (lowercase, use dots)</p>
                        </div>

                        <!-- Resources Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <!-- CPU Cores -->
                            <div>
                                <InputLabel for="cores" value="CPU Cores" />
                                <TextInput
                                    id="cores"
                                    v-model.number="form.cores"
                                    type="number"
                                    min="1"
                                    max="32"
                                    class="mt-1 block w-full"
                                    required
                                />
                            </div>

                            <!-- Memory (GB) -->
                            <div>
                                <InputLabel for="memory" value="RAM (GB)" />
                                <TextInput
                                    id="memory"
                                    v-model.number="form.memory"
                                    type="number"
                                    min="1"
                                    max="256"
                                    class="mt-1 block w-full"
                                    required
                                />
                            </div>

                            <!-- Disk (GB) -->
                            <div>
                                <InputLabel for="disk" value="Disk (GB)" />
                                <TextInput
                                    id="disk"
                                    v-model.number="form.disk"
                                    type="number"
                                    min="10"
                                    max="1000"
                                    class="mt-1 block w-full"
                                    required
                                />
                            </div>

                            <!-- Bandwidth (TB/month) -->
                            <div>
                                <InputLabel for="bandwidth" value="Bandwidth (TB/month)" />
                                <TextInput
                                    id="bandwidth"
                                    v-model.number="form.bandwidth"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="mt-1 block w-full"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Root Password -->
                        <div>
                            <InputLabel for="password" value="Root Password" />
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                required
                                minlength="8"
                            />
                            <p class="text-sm text-gray-600 mt-1">Minimum 8 characters</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-4">
                            <SecondaryButton @click="router.visit('/admin/vms')">
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton :disabled="processing">
                                {{ processing ? 'Creating...' : 'Create VM' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
