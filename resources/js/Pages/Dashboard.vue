<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import PaymentModal from '@/Components/PaymentModal.vue';
import AdminReservationModal from '@/Components/AdminReservationModal.vue';
import { UserGroupIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/solid';
import { formatDateTimePH, formatDatePH, formatTimePH } from '@/utils/timezone';

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

const formatDateTime = (dateTime) => {
    const formatted = formatDateTimePH(dateTime);
    return formatted || 'N/A';
};

const formatLocalDate = (dateString) => formatDatePH(dateString) || '';

const formatLocalDateTime = (dateString) => formatDateTimePH(dateString) || '';

const formatLocalTime = (dateString) => formatTimePH(dateString) || '-';

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
                                        {{ formatLocalTime(customer.service_start_time) }}
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
    </AuthenticatedLayout>
</template>

