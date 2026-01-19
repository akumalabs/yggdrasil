<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    nodes: Array,
    selectedNode: String,
    storage: Array,
    contents: Array,
});

const changeNode = (e) => {
    router.visit(route('storage.index', { node: e.target.value }));
};

const formatSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <Head title="Storage Browser" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Storage Browser
                </h2>
                <div>
                    <select :value="selectedNode" @change="changeNode" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option v-for="node in nodes" :key="node" :value="node">{{ node }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        
                        <div v-if="contents.length === 0" class="text-center py-8 text-gray-500">
                            No content found on this node.
                        </div>

                        <table v-else class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Storage</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name/VolID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Format</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(item, index) in contents" :key="index">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                            :class="{
                                                'bg-blue-100 text-blue-800': item.content === 'iso',
                                                'bg-purple-100 text-purple-800': item.content === 'vztmpl',
                                                'bg-gray-100 text-gray-800': item.content === 'rootdir' || item.content === 'images'
                                            }">
                                            {{ item.content }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ item.storage }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium">{{ item.volid ? item.volid.split('/').pop() : 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ item.format }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ formatSize(item.size) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
