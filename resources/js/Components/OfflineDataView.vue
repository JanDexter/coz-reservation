<template>
    <div v-if="hasOfflineData" class="bg-white rounded-xl shadow-lg border-2 border-blue-300 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                Your Saved Reservations
                <span v-if="!isOnline" class="text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded-full">
                    Available Offline
                </span>
            </h3>
            <button 
                @click="refreshData" 
                class="text-blue-600 hover:text-blue-800 transition-colors"
                title="Refresh data"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>

        <!-- Reservation Details -->
        <div v-if="reservation" class="mb-6 bg-blue-50 rounded-lg p-4">
            <h4 class="font-semibold text-gray-800 mb-3">Reservation Details</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-gray-600">Space</p>
                    <p class="font-medium text-gray-900">{{ reservation.space_name || reservation.space_type_name || 'Not specified' }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Status</p>
                    <p class="font-medium" :class="statusClass">
                        {{ reservation.status || 'Active' }}
                    </p>
                </div>
                <div>
                    <p class="text-gray-600">Start Time</p>
                    <p class="font-medium text-gray-900">{{ formatDateTime(reservation.start_time) }}</p>
                </div>
                <div>
                    <p class="text-gray-600">End Time</p>
                    <p class="font-medium text-gray-900">{{ formatDateTime(reservation.end_time) }}</p>
                </div>
                <div v-if="reservation.total_price">
                    <p class="text-gray-600">Total Price</p>
                    <p class="font-medium text-gray-900">{{ formatCurrency(reservation.total_price) }}</p>
                </div>
                <div v-if="reservation.payment_method">
                    <p class="text-gray-600">Payment Method</p>
                    <p class="font-medium text-gray-900 uppercase">{{ reservation.payment_method }}</p>
                </div>
            </div>
            
            <!-- Time Remaining -->
            <div v-if="timeRemaining" class="mt-4 pt-4 border-t border-blue-200">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Time Remaining</span>
                    <span class="text-lg font-bold text-blue-600">{{ timeRemaining }}</span>
                </div>
            </div>
        </div>

        <!-- WiFi Credentials -->
        <div v-if="wifiCredentials" class="bg-green-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                    WiFi Access
                </h4>
                <button 
                    @click="copyAllCredentials" 
                    class="text-xs bg-green-600 text-white px-3 py-1 rounded-full hover:bg-green-700 transition-colors"
                >
                    Copy All
                </button>
            </div>
            
            <div class="space-y-2">
                <div class="flex items-center justify-between bg-white rounded px-3 py-2">
                    <div>
                        <p class="text-xs text-gray-600">Network Name (SSID)</p>
                        <p class="font-mono font-medium text-gray-900">{{ wifiCredentials.ssid }}</p>
                    </div>
                    <button 
                        @click="copyToClipboard(wifiCredentials.ssid, 'SSID')" 
                        class="text-green-600 hover:text-green-800"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
                
                <div class="flex items-center justify-between bg-white rounded px-3 py-2">
                    <div>
                        <p class="text-xs text-gray-600">Username</p>
                        <p class="font-mono font-medium text-gray-900">{{ wifiCredentials.username }}</p>
                    </div>
                    <button 
                        @click="copyToClipboard(wifiCredentials.username, 'Username')" 
                        class="text-green-600 hover:text-green-800"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
                
                <div class="flex items-center justify-between bg-white rounded px-3 py-2">
                    <div>
                        <p class="text-xs text-gray-600">Password</p>
                        <p class="font-mono font-medium text-gray-900">{{ wifiCredentials.password }}</p>
                    </div>
                    <button 
                        @click="copyToClipboard(wifiCredentials.password, 'Password')" 
                        class="text-green-600 hover:text-green-800"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="wifiCredentials.expiresAt" class="mt-3 pt-3 border-t border-green-200">
                <p class="text-xs text-gray-600">
                    Valid until {{ formatDateTime(wifiCredentials.expiresAt) }}
                </p>
            </div>
        </div>

        <!-- Clear Data Button -->
        <div class="mt-4 pt-4 border-t border-gray-200">
            <button 
                @click="clearAllData" 
                class="text-sm text-red-600 hover:text-red-800 flex items-center gap-1"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Clear Saved Data
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { offlineStorage } from '../utils/offlineStorage';

const props = defineProps({
    isOnline: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['copy-success', 'data-cleared']);

const reservation = ref(null);
const wifiCredentials = ref(null);
const currentTime = ref(new Date());
let timeInterval = null;

const hasOfflineData = computed(() => {
    return reservation.value || wifiCredentials.value;
});

const statusClass = computed(() => {
    if (!reservation.value?.status) return 'text-gray-900';
    
    const status = reservation.value.status.toLowerCase();
    if (status.includes('active') || status.includes('confirmed')) return 'text-green-600';
    if (status.includes('pending')) return 'text-orange-600';
    if (status.includes('completed')) return 'text-blue-600';
    if (status.includes('cancelled')) return 'text-red-600';
    
    return 'text-gray-900';
});

const timeRemaining = computed(() => {
    if (!reservation.value?.end_time) return null;
    
    const end = new Date(reservation.value.end_time);
    const remaining = end - currentTime.value;
    
    if (remaining <= 0) return 'Expired';
    
    const hours = Math.floor(remaining / (1000 * 60 * 60));
    const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
    
    if (hours > 0) {
        return `${hours}h ${minutes}m ${seconds}s`;
    }
    return `${minutes}m ${seconds}s`;
});

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    } catch (error) {
        return dateString;
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
};

const copyToClipboard = async (text, label) => {
    try {
        await navigator.clipboard.writeText(text);
        emit('copy-success', `${label} copied to clipboard!`);
    } catch (error) {
        console.error('Failed to copy:', error);
    }
};

const copyAllCredentials = async () => {
    if (!wifiCredentials.value) return;
    
    const text = `WiFi Credentials
SSID: ${wifiCredentials.value.ssid}
Username: ${wifiCredentials.value.username}
Password: ${wifiCredentials.value.password}`;
    
    try {
        await navigator.clipboard.writeText(text);
        emit('copy-success', 'All WiFi credentials copied!');
    } catch (error) {
        console.error('Failed to copy:', error);
    }
};

const loadData = () => {
    reservation.value = offlineStorage.getReservation();
    wifiCredentials.value = offlineStorage.getWiFiCredentials();
};

const refreshData = () => {
    offlineStorage.cleanupExpired();
    loadData();
    emit('copy-success', 'Data refreshed!');
};

const clearAllData = () => {
    if (confirm('Are you sure you want to clear all saved offline data?')) {
        offlineStorage.clearAll();
        reservation.value = null;
        wifiCredentials.value = null;
        emit('data-cleared');
    }
};

onMounted(() => {
    loadData();
    
    // Update time every second for countdown
    timeInterval = setInterval(() => {
        currentTime.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
});

// Expose method for parent to reload data
defineExpose({
    loadData,
    refreshData,
});
</script>
