<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import PaymentModal from '@/Components/PaymentModal.vue';
import AdminReservationModal from '@/Components/AdminReservationModal.vue';
import { UserGroupIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    stats: Object,
    customers: Object,
    spaceTypes: Array,
    recentTransactions: Array,
    activeServices: Array,
});


const searchQuery = ref('');
const showPaymentModal = ref(false);
const selectedPayment = ref(null);
const showReservationModal = ref(false);
const selectedReservation = ref(null);

const openPaymentModal = (service) => {
    const calculatedCost = service.hourly_rate * (service.start_time ? Math.max(1, Math.ceil((Date.now() - new Date(service.start_time).getTime()) / (1000 * 60 * 60))) : 1);
    selectedPayment.value = {
        id: service.id,
        customer_name: service.customer_name,
        space_name: service.space_name,
        space_type: service.space_type,
        total_cost: calculatedCost,
        cost: calculatedCost,
        amount_paid: service.amount_paid || 0,
        amount_remaining: service.amount_remaining ?? (calculatedCost - (service.amount_paid || 0)),
        status: service.status,
    };
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    selectedPayment.value = null;
};

const openReservationModal = (transaction) => {
    if (!transaction) return;
    selectedReservation.value = {
        id: transaction.id,
        status: transaction.status,
        payment_method: transaction.payment_method || '',
        amount_paid: transaction.amount_paid || 0,
        amount_remaining: transaction.amount_remaining || 0,
        total_cost: transaction.cost || transaction.total_cost || 0,
        notes: transaction.notes || '',
        start_time: transaction.start_time,
        end_time: transaction.end_time,
        hours: transaction.hours || null,
        pax: transaction.pax || null,
        is_open_time: transaction.is_open_time || false,
        space_type: { name: transaction.space_type },
        space: { name: transaction.space_name },
        customer: {
            name: transaction.customer_name,
            email: transaction.customer_email || null,
            phone: transaction.customer_phone || null,
        },
    };
    showReservationModal.value = true;
};

const closeReservationModal = () => {
    showReservationModal.value = false;
    selectedReservation.value = null;
};

const handleReservationUpdated = () => {
    closeReservationModal();
    router.reload({ only: ['recentTransactions', 'activeServices'] });
};

const showSpaceReservationsModal = ref(false);
const spaceReservations = ref([]);
const selectedSpace = ref(null);

const viewSpaceReservations = async (space) => {
    selectedSpace.value = space;
    try {
        const response = await fetch(route('spaces.reservations', space.id));
        const data = await response.json();
        spaceReservations.value = data.reservations || [];
        showSpaceReservationsModal.value = true;
    } catch (error) {
        console.error('Error fetching space reservations:', error);
        alert('Failed to load reservations for this space');
    }
};

const closeSpaceReservationsModal = () => {
    showSpaceReservationsModal.value = false;
    selectedSpace.value = null;
    spaceReservations.value = [];
};

const formatDateTime = (dateTime) => {
    if (!dateTime) return 'N/A';
    const date = new Date(dateTime);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Manila'
    });
};



const formatLocalDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'Asia/Manila' });
};

const formatLocalDateTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Manila' });
};

const getStatusColor = (status) => {
    const colors = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-red-100 text-red-800',
        closed: 'bg-red-100 text-red-800',
        reserved: 'bg-blue-100 text-blue-800',
        cancelled: 'bg-gray-100 text-gray-800',
    };
    return colors[status.toLowerCase()] || 'bg-gray-100 text-gray-800';
};

// Live clock for open-time running totals
const nowTick = ref(Date.now());
let tickTimer;

onMounted(() => {
    tickTimer = setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (tickTimer) clearInterval(tickTimer);
});

const runningHours = (start) => {
    if (!start) return 0;
    const diff = nowTick.value - new Date(start).getTime();
    return Math.max(1, Math.ceil(diff / (1000 * 60 * 60)));
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return formatLocalDate(dateString);
};

const getTotalSpaces = (spaceType) => {
    return spaceType?.spaces?.length || 0;
};

const getOccupiedSpaces = (spaceType) => {
    return spaceType?.spaces?.filter(space => space.is_currently_occupied).length || 0;
};

const getAvailableSpaces = (spaceType) => {
    return spaceType?.spaces?.filter(space => !space.is_currently_occupied).length || 0;
};

const getOccupancyPercentage = (spaceType) => {
    const total = getTotalSpaces(spaceType);
    const occupied = getOccupiedSpaces(spaceType);
    return total > 0 ? Math.round((occupied / total) * 100) : 0;
};

const getOccupancyFraction = (spaceType) => {
    const occupied = getOccupiedSpaces(spaceType);
    const total = getTotalSpaces(spaceType);
    return `${occupied}/${total}`;
};

const getNextAvailableTime = (spaceType) => {
    if (!spaceType?.spaces) return null;
    
    const occupiedSpaces = spaceType.spaces
        .filter(space => space.is_currently_occupied && space.current_occupation?.end_time)
        .sort((a, b) => new Date(a.current_occupation.end_time) - new Date(b.current_occupation.end_time));
    
    if (occupiedSpaces.length === 0) {
        return null;
    }
    
    const nextFree = new Date(occupiedSpaces[0].current_occupation.end_time);
    const now = new Date();
    
    if (nextFree <= now) {
        return 'Available now';
    }
    
    const diff = nextFree - now;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
};

const getTimeUntilFree = (space) => {
    if (!space.current_occupation?.end_time) return null;
    
    const until = new Date(space.current_occupation.end_time);
    const now = new Date();
    
    if (until <= now) return 'Available now';
    
    const diff = until - now;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
};

// Helper functions for overall space statistics
const getAllSpaces = () => {
    if (!props.spaceTypes) return [];
    return props.spaceTypes.flatMap(spaceType => spaceType.spaces || []);
};

const getAvailableSpacesCount = () => {
    return getAllSpaces().filter(space => space.status === 'available').length;
};

const getOccupiedSpacesCount = () => {
    return getAllSpaces().filter(space => space.status === 'occupied').length;
};

const getOverallOccupancyPercentage = () => {
    const total = getAllSpaces().length;
    const occupied = getOccupiedSpacesCount();
    return total > 0 ? Math.round((occupied / total) * 100) : 0;
};

const getSlotAvailabilityColor = (spaceType) => {
    const total = getTotalSpaces(spaceType);
    const available = getAvailableSpaces(spaceType);
    const availabilityPercentage = total > 0 ? (available / total) * 100 : 0;
    
    if (availabilityPercentage >= 60) {
        return 'bg-green-100'; // Many slots available - green
    } else if (availabilityPercentage >= 20) {
        return 'bg-yellow-100'; // Some slots available - yellow
    } else {
        return 'bg-red-100'; // Few/no slots available - red
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Dashboard</h2>
                        <p class="text-sm text-gray-600 mt-1">Overview of business operations and real-time metrics</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <Link
                            :href="route('customers.create')"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add Customer
                        </Link>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                        <UserGroupIcon class="w-7 h-7 text-blue-600" />
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Customers</dt>
                                        <dd class="text-2xl font-semibold text-gray-900">{{ stats.total_customers }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                        <CheckCircleIcon class="w-7 h-7 text-green-600" />
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Active Customers</dt>
                                        <dd class="text-2xl font-semibold text-gray-900">{{ stats.active_customers }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                        <XCircleIcon class="w-7 h-7 text-red-600" />
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Inactive Customers</dt>
                                        <dd class="text-2xl font-semibold text-gray-900">{{ stats.inactive_customers }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Space Slots by Type (Admin only) -->
                <div v-if="($page.props.auth.user.is_admin || $page.props.auth.can.admin_access) && spaceTypes && spaceTypes.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Space Slots</h3>
                            <Link
                                :href="route('space-management.index')"
                                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded text-sm"
                            >
                                Manage Slots
                            </Link>
                        </div>
                        
                        <!-- Space Type Slots Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div 
                                v-for="spaceType in spaceTypes" 
                                :key="spaceType.id"
                                class="rounded-lg p-4 border-2 hover:shadow-lg transition-all"
                                :class="getSlotAvailabilityColor(spaceType)"
                            >
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-lg font-semibold text-gray-900">{{ spaceType.name }}</h4>
                                        <div class="text-sm font-medium text-gray-600">₱{{ spaceType.hourly_rate || spaceType.default_price }}/hr</div>
                                    </div>
                                    <div class="text-3xl font-bold text-gray-900 mb-1">{{ getOccupancyFraction(spaceType) }}</div>
                                    <div class="text-sm text-gray-600 mb-3">slots occupied</div>
                                    
                                    <!-- Spaces list with future reservations -->
                                    <div v-if="spaceType.spaces && spaceType.spaces.length > 0" class="space-y-2 mt-4">
                                        <div 
                                            v-for="space in spaceType.spaces" 
                                            :key="space.id"
                                            class="p-3 bg-white rounded-md shadow-sm border"
                                            :class="space.is_currently_occupied ? 'border-red-300' : (space.has_future_reservations ? 'border-yellow-300' : 'border-green-300')"
                                        >
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-medium text-sm">{{ space.name }}</span>
                                                <span 
                                                    class="text-xs px-2 py-1 rounded-full font-semibold"
                                                    :class="space.is_currently_occupied ? 'bg-red-100 text-red-800' : (space.has_future_reservations ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')"
                                                >
                                                    {{ space.is_currently_occupied ? 'Occupied' : (space.has_future_reservations ? 'Reserved' : 'Available') }}
                                                </span>
                                            </div>
                                            
                                            <!-- Current Occupation -->
                                            <div v-if="space.is_currently_occupied && space.current_occupation" class="text-xs text-gray-600 mb-2 p-2 bg-red-50 rounded">
                                                <p class="font-semibold text-red-900">Currently Occupied:</p>
                                                <p>{{ space.current_occupation.customer_name }}</p>
                                                <p>Until: {{ formatDateTime(space.current_occupation.end_time) }}</p>
                                            </div>
                                            
                                            <!-- Future Reservations Notice -->
                                            <div v-if="!space.is_currently_occupied && space.has_future_reservations && space.future_reservations && space.future_reservations.length > 0" class="text-xs text-gray-600 mb-2 p-2 bg-yellow-50 rounded border border-yellow-200">
                                                <p class="font-semibold text-yellow-900 mb-1">📅 Upcoming Reservation:</p>
                                                <div v-for="(futureRes, idx) in space.future_reservations.slice(0, 1)" :key="idx">
                                                    <p class="text-yellow-800">{{ futureRes.customer_name }}</p>
                                                    <p class="text-yellow-700">{{ formatDateTime(futureRes.start_time) }} - {{ formatDateTime(futureRes.end_time) }}</p>
                                                </div>
                                                <p v-if="space.future_reservations.length > 1" class="text-yellow-600 mt-1 italic">
                                                    +{{ space.future_reservations.length - 1 }} more reservation(s)
                                                </p>
                                            </div>
                                            
                                            <!-- Action Button -->
                                            <button
                                                @click="viewSpaceReservations(space)"
                                                class="w-full mt-2 text-xs bg-blue-500 hover:bg-blue-600 text-white font-semibold py-1.5 px-3 rounded transition-colors"
                                            >
                                                📋 View All Reservations
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div v-else class="text-sm text-gray-500 italic mt-4">
                                        No spaces configured
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Services & Recent Transactions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Active Services -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Active Services</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Space</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="service in activeServices" :key="service.id" class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ service.customer_name }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <div>{{ service.space_name }}</div>
                                                <div class="text-xs text-gray-400">{{ service.space_type }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ formatLocalDateTime(service.start_time) }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(service.status || 'active')}`">
                                                    {{ (service.status || 'active').toUpperCase() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex flex-col">
                                                    <div>
                                                        ₱{{ service.hourly_rate }}/h
                                                        <span v-if="service.is_open_time" class="ml-1 text-xs text-orange-600">(Open)</span>
                                                    </div>
                                                    <div v-if="service.is_open_time" class="text-xs text-gray-500">
                                                        Elapsed: {{ runningHours(service.start_time) }}h · Est: ₱{{ (runningHours(service.start_time) * (service.hourly_rate || 0)).toFixed(2) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <button
                                                    @click="openPaymentModal(service)"
                                                    class="text-green-600 hover:text-green-800 font-medium"
                                                >
                                                    Pay Now
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!activeServices || activeServices.length === 0">
                                            <td colspan="6" class="px-4 py-3 text-center text-sm text-gray-500">No active services</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Transactions</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Space</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr 
                                            v-for="transaction in recentTransactions" 
                                            :key="transaction.id" 
                                            @click="openReservationModal(transaction)"
                                            class="hover:bg-gray-50 cursor-pointer"
                                        >
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ transaction.customer_name }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <div>{{ transaction.space_name }}</div>
                                                <div class="text-xs text-gray-400">{{ transaction.space_type }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ formatLocalDate(transaction.end_time) }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">₱{{ transaction.cost ? Number(transaction.cost).toFixed(2) : '0.00' }}</td>
                                        </tr>
                                        <tr v-if="!recentTransactions || recentTransactions.length === 0">
                                            <td colspan="4" class="px-4 py-3 text-center text-sm text-gray-500">No recent transactions</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Management -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <!-- Search and Filter Bar -->
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <button class="p-2 text-gray-400 hover:text-gray-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.707A1 1 0 013 7V4z"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="flex-1 max-w-md">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Search customers..."
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <div class="flex items-center space-x-1">
                                            <span>#</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                            </svg>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <div class="flex items-center space-x-1">
                                            <span>Name</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                            </svg>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service Availed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <div class="flex items-center space-x-1">
                                            <span>Status</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                            </svg>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(customer, index) in customers.data" :key="customer.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ index + 1 + (customers.current_page - 1) * customers.per_page }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ customer.name }}</div>
                                            <div class="text-sm text-gray-500">{{ customer.phone || customer.email }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ customer.service_type || 'No service selected' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(customer.status)}`">
                                            {{ customer.status.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(customer.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ customer.service_start_time ? new Date(customer.service_start_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ₱{{ customer.amount_paid ? Number(customer.amount_paid).toLocaleString() : '0' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <Link
                                                :href="route('customers.show', customer.id)"
                                                class="text-blue-600 hover:text-blue-500"
                                            >
                                                View
                                            </Link>
                                            <Link
                                                :href="route('customers.edit', customer.id)"
                                                class="text-indigo-600 hover:text-indigo-500"
                                            >
                                                Edit
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-700">
                                    {{ customers.from }}-{{ customers.to }} of {{ customers.total }} customers
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-700">Rows per page:</span>
                                <select class="border border-gray-300 rounded text-sm">
                                    <option>10</option>
                                    <option>25</option>
                                    <option>50</option>
                                </select>
                                <div class="flex items-center space-x-1 ml-4">
                                    <button
                                        v-if="customers.prev_page_url"
                                        @click="router.get(customers.prev_page_url)"
                                        class="p-1 text-gray-400 hover:text-gray-600"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                        </svg>
                                    </button>
                                    <span class="text-sm text-gray-700">{{ customers.current_page }}/{{ customers.last_page }}</span>
                                    <button
                                        v-if="customers.next_page_url"
                                        @click="router.get(customers.next_page_url)"
                                        class="p-1 text-gray-400 hover:text-gray-600"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Modal -->
        <PaymentModal
            :show="showPaymentModal"
            :reservation="selectedPayment"
            @close="closePaymentModal"
            @paid="router.reload({ only: ['activeServices', 'recentTransactions'] })"
        />
        
        <!-- Admin Reservation Modal -->
        <AdminReservationModal
            :show="showReservationModal"
            :reservation="selectedReservation"
            @close="closeReservationModal"
            @updated="handleReservationUpdated"
        />
        
        <!-- Space Reservations Modal -->
        <div v-if="showSpaceReservationsModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeSpaceReservationsModal"></div>
                
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Reservations for {{ selectedSpace?.name }}
                            </h3>
                            <button @click="closeSpaceReservationsModal" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="reservation in spaceReservations" :key="reservation.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ reservation.customer_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(reservation.start_time) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(reservation.end_time) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span 
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                                :class="{
                                                    'bg-green-100 text-green-800': reservation.is_active,
                                                    'bg-blue-100 text-blue-800': reservation.is_future,
                                                    'bg-gray-100 text-gray-800': reservation.is_past
                                                }"
                                            >
                                                {{ reservation.is_active ? 'Active' : (reservation.is_future ? 'Future' : 'Past') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(reservation.status)}`">
                                                {{ reservation.status.toUpperCase() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">₱{{ reservation.total_cost }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm space-x-2">
                                            <button
                                                @click="openReservationModal(reservation)"
                                                class="text-blue-600 hover:text-blue-800 font-medium"
                                            >
                                                View
                                            </button>
                                            <button
                                                v-if="reservation.amount_remaining > 0"
                                                @click="openPaymentModal({ ...reservation, space_name: selectedSpace?.name })"
                                                class="text-green-600 hover:text-green-800 font-medium"
                                            >
                                                Pay
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!spaceReservations || spaceReservations.length === 0">
                                        <td colspan="7" class="px-4 py-3 text-center text-sm text-gray-500">No reservations found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            type="button"
                            @click="closeSpaceReservationsModal"
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

