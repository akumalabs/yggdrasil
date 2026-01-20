<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    vmid: {
        type: Number,
        required: true,
    },
    timeframe: {
        type: String,
        default: 'hour',
    },
});

const metrics = ref(null);
const loading = ref(true);
const interval = ref(null);

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            position: 'top',
        },
        title: {
            display: true,
            text: 'Resource Usage',
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: function(value) {
                    return value + '%';
                }
            }
        },
    },
};

const cpuChartData = ref({
    labels: [],
    datasets: [{
        label: 'CPU Usage',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        borderColor: 'rgb(59, 130, 246)',
        data: [],
        fill: true,
    }],
});

const memoryChartData = ref({
    labels: [],
    datasets: [{
        label: 'Memory Usage',
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        borderColor: 'rgb(16, 185, 129)',
        data: [],
        fill: true,
    }],
});

const fetchMetrics = async () => {
    try {
        const response = await fetch(`/api/vms/${props.vmid}/metrics?timeframe=${props.timeframe}`);
        const data = await response.json();
        metrics.value = data;

        // Update charts
        if (data.history && data.history.length > 0) {
            const labels = data.history.map(point => {
                const date = new Date(point.time * 1000);
                return date.toLocaleTimeString();
            });

            cpuChartData.value = {
                labels,
                datasets: [{
                    label: 'CPU Usage',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgb(59, 130, 246)',
                    data: data.history.map(point => point.cpu),
                    fill: true,
                }],
            };

            memoryChartData.value = {
                labels,
                datasets: [{
                    label: 'Memory Usage',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderColor: 'rgb(16, 185, 129)',
                    data: data.history.map(point => point.memory),
                    fill: true,
                }],
            };
        }

        loading.value = false;
    } catch (error) {
        console.error('Failed to fetch metrics:', error);
        loading.value = false;
    }
};

onMounted(() => {
    fetchMetrics();
    // Refresh every 30 seconds
    interval.value = setInterval(fetchMetrics, 30000);
});

onUnmounted(() => {
    if (interval.value) {
        clearInterval(interval.value);
    }
});

const formatBytes = (bytes) => {
    if (!bytes) return '0 MB';
    const mb = bytes / 1024 / 1024;
    if (mb < 1024) return mb.toFixed(2) + ' MB';
    return (mb / 1024).toFixed(2) + ' GB';
};
</script>

<template>
    <div class="space-y-6">
        <div v-if="loading" class="text-center py-12">
            <p class="text-gray-500">Loading metrics...</p>
        </div>

        <template v-else-if="metrics">
            <!-- Current Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-600">CPU Usage</div>
                    <div class="mt-2 text-3xl font-bold text-blue-600">
                        {{ metrics.current.cpu.toFixed(1) }}%
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Source: {{ metrics.source }}
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-600">Memory Usage</div>
                    <div class="mt-2 text-3xl font-bold text-green-600">
                        {{ metrics.current.memory.usage_percent.toFixed(1) }}%
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ formatBytes(metrics.current.memory.used) }} / {{ formatBytes(metrics.current.memory.total) }}
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="text-sm text-gray-600">Network Traffic</div>
                    <div class="mt-2 text-xl font-bold">
                        <div class="text-purple-600">↓ {{ formatBytes(metrics.current.network.traffic_in) }}</div>
                        <div class="text-orange-600">↑ {{ formatBytes(metrics.current.network.traffic_out) }}</div>
                    </div>
                </div>
            </div>

            <!-- Historical Charts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="h-64">
                        <Line :data="cpuChartData" :options="chartOptions" />
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="h-64">
                        <Line :data="memoryChartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <!-- OS Info (if guest agent available) -->
            <div v-if="metrics.current.os" class="bg-white p-4 rounded-lg shadow">
                <h3 class="font-semibold mb-2">Operating System</h3>
                <p class="text-sm">{{ metrics.current.os.name }} {{ metrics.current.os.version }}</p>
                <div class="mt-2 text-xs text-green-600">
                    ✓ QEMU Guest Agent Active
                </div>
            </div>

            <!-- Disk Info (if guest agent available) -->
            <div v-if="metrics.current.disk && metrics.current. disk.length > 0" class="bg-white p-4 rounded-lg shadow">
                <h3 class="font-semibold mb-2">Disk Usage</h3>
                <div class="space-y-2">
                    <div v-for="disk in metrics.current.disk" :key="disk.mountpoint" class="flex items-center justify-between">
                        <span class="text-sm">{{ disk.mountpoint }}</span>
                        <div class="flex-1 mx-4 bg-gray-200 rounded-full h-2">
                            <div 
                                class="bg-blue-600 h-2 rounded-full" 
                                :style="{ width: disk.usage_percent + '%' }"
                            ></div>
                        </div>
                        <span class="text-sm font-semibold">{{ disk.usage_percent.toFixed(1) }}%</span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
