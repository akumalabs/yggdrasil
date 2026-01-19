<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    vm: Object,
    config: Object,
});

const form = useForm({
    memory: props.config.memory || '',
    cores: props.config.cores || '',
    cipassword: '',
    sshkeys: decodeURIComponent(props.config.sshkeys || ''),
});

const submit = () => {
    form.post(route('vms.update', props.vm.vmid), {
        preserveScroll: true,
        onSuccess: () => form.reset('cipassword'),
    });
};

const toggleRescue = (enable) => {
    if (!confirm(enable ? 'Enable Rescue Mode? This will change boot order to CD-ROM first.' : 'Disable Rescue Mode? This will restore boot order to Hard Disk.')) return;
    
    router.post(route('vms.rescue', props.vm.vmid), { enable }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`VM ${vm.vmid} - Details`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    VM {{ vm.name }} ({{ vm.vmid }})
                </h2>
                <div class="space-x-4">
                    <Link :href="route('vms.console', vm.vmid)" class="text-indigo-600 hover:text-indigo-900 font-bold">
                        Open Console
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Configuration</h3>
                    
                    <form @submit.prevent="submit" class="space-y-6 max-w-xl">
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

                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium text-gray-700 mb-2">Rescue Mode</h4>
                            <div class="flex items-center justify-between bg-gray-50 p-3 rounded">
                                <span class="text-sm text-gray-600">
                                    Rescue mode prioritizes booting from CD-ROM. Use this to recover a broken system.
                                </span>
                                <div class="space-x-2">
                                     <SecondaryButton type="button" @click="toggleRescue(true)" v-if="!config.boot?.includes('order=ide2')">Enable</SecondaryButton>
                                     <SecondaryButton type="button" @click="toggleRescue(false)" v-else class="text-red-600">Disable</SecondaryButton>
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium text-gray-700 mb-2">Cloud-Init Configuration</h4>
                             <div>
                                <InputLabel for="cipassword" value="Set New Password (User)" />
                                <TextInput id="cipassword" v-model="form.cipassword" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep unchanged" />
                                <InputError :message="form.errors.cipassword" class="mt-2" />
                            </div>
                            <div class="mt-4">
                                <InputLabel for="sshkeys" value="SSH Public Keys" />
                                <textarea id="sshkeys" v-model="form.sshkeys" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" rows="4"></textarea>
                                <InputError :message="form.errors.sshkeys" class="mt-2" />
                            </div>
                        </div>

                        <div class="bg-blue-50 p-4 rounded-md text-sm text-blue-700">
                            Note: Some changes require a reboot to take effect if hot-plug is typically not enabled for CPU/Memory in the VM options.
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">Save Changes</PrimaryButton>
                            
                            <transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
                            </transition>
                        </div>
                    </form>
                </div>

                <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Network & Cloud-Init</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                         <div>
                            <span class="font-bold">IP Config:</span> {{ config.ipconfig0 || 'N/A' }}
                         </div>
                         <div>
                            <span class="font-bold">SSH Keys:</span> {{ config.sshkeys ? 'Present' : 'None' }}
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
