<script setup>
import { ref, onMounted } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    vmid: {
        type: Number,
        required: true,
    },
});

const bandwidth = ref(null);
const loading = ref(true);

const chartData = ref({
    labels: ['Used', 'Remaining'],
    datasets: [{
        data: [0, 100],
        backgroundColor: [
            'rgba(59, 130, 246, 0.8)',
            'rgba(229, 231, 235, 0.8)',
        ],
        borderColor: [
            'rgb(59, 130, 246)',
            'rgb(229, 231, 235)',
        ],
        borderWidth: 1,
    }],
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return context.label + ': ' + context.parsed.toFixed(2) + ' TB';
                }
            }
        }
    },
};

const fetchBandwidth = async () => {
    try {
        const response = await fetch(`/api/vms/${props.vmid}/bandwidth`);
        const data = await response.json();
        bandwidth.value = data;

        chartData.value = {
            labels: ['Used', 'Remaining'],
            datasets: [{
                data: [data.used_tb, data.remaining_tb],
                backgroundColor: [
                    data.usage_percent > 80 ? 'rgba(239, 68, 68, 0.8)' : 'rgba(59, 130, 246, 0.8)',
                    'rgba(229, 231, 235, 0.8)',
                ],
                borderColor: [
                    data.usage_percent > 80 ? 'rgb(239, 68, 68)' : 'rgb(59, 130, 246)',
                    'rgb(229, 231, 235)',
                ],
                borderWidth: 1,
            }],
        };

        loading.value = false;
    } catch (error) {
        console.error('Failed to fetch bandwidth:', error);
        loading.value = false;
    }
};

onMounted(() => {
    fetchBandwidth();
});
</script>

<template>
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="font-semibold text-lg mb-4">Bandwidth Usage</h3>

        <div v-if="loading" class="text-center py-8">
            <p class="text-gray-500">Loading...</p>
        </div>

        <template v-else-if="bandwidth">
            <div class="mb-4">
                <div class="flex items-baseline justify-between mb-2">
                    <span class="text-sm text-gray-600">Monthly Usage</span>
                    <span class="text-2xl font-bold" :class="bandwidth.usage_percent > 80 ? 'text-red-600' : 'text-blue-600'">
                        {{ bandwidth.used_tb.toFixed(2) }} TB / {{ bandwidth.allocated_tb }} TB
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div 
                        class="h-3 rounded-full transition-all" 
                        :class="bandwidth.usage_percent > 80 ? 'bg-red-600' : 'bg-blue-600'"
                        :style="{ width: Math.min(bandwidth.usage_percent, 100) + '%' }"
                    ></div>
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ bandwidth.usage_percent.toFixed(1) }}% used • Resets on {{ bandwidth.reset_date }}
                </div>
            </div>

            <div class="h-48">
                <Doughnut :data="chartData" :options="chartOptions" />
            </div>

            <!-- Daily Usage Table (collapsible) -->
            <details v-if="bandwidth.daily_usage && bandwidth.daily_usage.length > 0" class="mt-4">
                <summary class="cursor-pointer text-sm font-semibold text-gray-700">
                    View Daily Usage ({{ bandwidth.daily_usage.length }} days)
                </summary>
                <div class="mt-2 max-h-48 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-xs">Date</th>
                                <th class="px-2 py-1 text-right text-xs">Usage (GB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in bandwidth.daily_usage" :key="day.date" class="border-t">
                                <td class="px-2 py-1">{{ day.date }}</td>
                                <td class="px-2 py-1 text-right font-mono">{{ day.total_gb }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </template>
    </div>
</template>
