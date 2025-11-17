<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaymentModal from '@/Components/PaymentModal.vue';

const props = defineProps({
    transactions: Object,
    pendingPayments: Array,
    filters: Object,
    summary: Object,
});

const activeFilter = ref(props.filters.filter || 'all');
const activeType = ref(props.filters.type || 'all');

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value || 0);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getTypeColor = (type) => {
    const colors = {
        payment: 'bg-green-100 text-green-800 border-green-200',
        refund: 'bg-orange-100 text-orange-800 border-orange-200',
        cancellation: 'bg-red-100 text-red-800 border-red-200',
    };
    return colors[type] || 'bg-gray-100 text-gray-800 border-gray-200';
};

const getTypeIcon = (type) => {
    const icons = {
        payment: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        refund: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
        cancellation: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    };
    return icons[type] || 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
};

const applyFilter = (filter) => {
    router.get(route('transactions.index'), {
        filter: filter,
        type: activeType.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const applyTypeFilter = (type) => {
    router.get(route('transactions.index'), {
        filter: activeFilter.value,
        type: type,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Payment modal state
const showPaymentModal = ref(false);
const selectedPaymentReservation = ref(null);

const openPaymentModal = (payment) => {
    selectedPaymentReservation.value = {
        id: payment.id,
        space_type: payment.space_type?.name,
        space_name: payment.space?.name,
        customer_name: payment.customer?.name,
        customer_id: payment.customer?.id,
        start_time: payment.start_time,
        end_time: payment.end_time,
        hours: payment.hours,
        total_cost: payment.cost,  // PaymentModal expects total_cost
        cost: payment.cost,
        amount_paid: payment.amount_paid,
        amount_remaining: payment.balance,  // Add the remaining balance
    };
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    selectedPaymentReservation.value = null;
};
</script>

<template>
    <Head title="Transactions" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Transactions</h2>
                        <p class="text-sm text-gray-600 mt-1">Complete history of all financial transactions including refunds</p>
                    </div>
                    <a
                        :href="route('transactions.export', { filter: activeFilter, type: activeType })"
                        download
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download
                    </a>
                </div>
                
                <!-- Pending Refunds Alert -->
                <div v-if="summary.pendingRefunds > 0" class="mb-6 rounded-xl bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-500 p-4 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    {{ summary.pendingRefunds }} Pending Refund {{ summary.pendingRefunds === 1 ? 'Request' : 'Requests' }}
                                </h3>
                                <p class="text-sm text-gray-700 mt-1">
                                    Customer cancellation refund requests are awaiting your review and approval.
                                </p>
                            </div>
                        </div>
                        <Link
                            :href="route('refunds.index', { status: 'pending' })"
                            class="flex-shrink-0 inline-flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg transition-colors shadow-md hover:shadow-lg"
                        >
                            <span>Review Refunds</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-gradient-to-br from-green-500 to-green-600 p-6 text-white shadow-lg">
                        <div class="mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium opacity-90">Total Payments</span>
                        </div>
                        <p class="text-3xl font-bold">{{ formatCurrency(summary.totalPayments) }}</p>
                    </div>

                    <div class="rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 p-6 text-white shadow-lg">
                        <div class="mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                            </svg>
                            <span class="text-sm font-medium opacity-90">Total Refunds</span>
                        </div>
                        <p class="text-3xl font-bold">{{ formatCurrency(summary.totalRefunds) }}</p>
                    </div>

                    <div class="rounded-xl bg-gradient-to-br from-red-500 to-red-600 p-6 text-white shadow-lg">
                        <div class="mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium opacity-90">Cancellations</span>
                        </div>
                        <p class="text-3xl font-bold">{{ summary.totalCancellations }}</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6 rounded-xl bg-white p-6 shadow-lg">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- Date Filter -->
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Time Period</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="filter in ['all', 'daily', 'weekly', 'monthly']"
                                    :key="filter"
                                    @click="applyFilter(filter)"
                                    :class="[
                                        'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                                        activeFilter === filter
                                            ? 'bg-[#2f4686] text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    ]"
                                >
                                    {{ filter.charAt(0).toUpperCase() + filter.slice(1) }}
                                </button>
                            </div>
                        </div>

                        <!-- Type Filter -->
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Transaction Type</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="type in ['all', 'payment', 'refund', 'cancellation']"
                                    :key="type"
                                    @click="applyTypeFilter(type)"
                                    :class="[
                                        'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                                        activeType === type
                                            ? 'bg-[#2f4686] text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    ]"
                                >
                                    {{ type.charAt(0).toUpperCase() + type.slice(1) }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Payments Section -->
                <div v-if="pendingPayments && pendingPayments.length > 0" class="mb-6 overflow-hidden rounded-xl bg-white shadow-lg">
                    <div class="bg-gradient-to-r from-yellow-500 to-amber-500 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Pending Payments ({{ pendingPayments.length }})
                        </h3>
                        <p class="text-sm text-yellow-50 mt-1">Unpaid reservations awaiting payment</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Space</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Hours</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Total Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Balance Due</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="payment in pendingPayments" :key="payment.id" class="hover:bg-yellow-50">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-gray-900">
                                        #{{ payment.id }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ payment.customer?.name || 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        <div>{{ payment.space_type?.name || 'N/A' }}</div>
                                        <div v-if="payment.space" class="text-xs text-gray-500">{{ payment.space.name }}</div>
                                        <div v-if="payment.is_open_time" class="mt-1">
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                Open Time
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-600">
                                        <div>{{ formatDate(payment.start_time) }}</div>
                                        <div>to {{ formatDate(payment.end_time) }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ payment.hours || 0 }}h
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-gray-900">
                                        {{ formatCurrency(payment.cost) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <span class="font-bold text-red-600">
                                            {{ formatCurrency(payment.balance) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <button
                                            @click="openPaymentModal(payment)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            Pay Now
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Transaction Logs Table -->
                <div class="overflow-hidden rounded-xl bg-white shadow-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Processed By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ formatDate(transaction.created_at) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div :class="['flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold', getTypeColor(transaction.type)]">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" :d="getTypeIcon(transaction.type)" />
                                                </svg>
                                                {{ transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <span class="font-mono text-xs text-gray-600">{{ transaction.reference_number || 'N/A' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ transaction.customer?.name || 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <span :class="[
                                            'font-semibold',
                                            transaction.amount >= 0 ? 'text-green-600' : 'text-red-600'
                                        ]">
                                            {{ transaction.amount >= 0 ? '+' : '' }}{{ formatCurrency(transaction.amount) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <div class="max-w-xs truncate" :title="transaction.description">
                                            {{ transaction.description || 'N/A' }}
                                        </div>
                                        <div v-if="transaction.notes" class="mt-1 max-w-xs truncate text-xs text-gray-500" :title="transaction.notes">
                                            {{ transaction.notes }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ transaction.processed_by?.name || 'Customer' }}
                                    </td>
                                </tr>

                                <tr v-if="!transactions.data.length">
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="text-gray-500">No transaction logs found</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="transactions.data.length" class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                Showing {{ transactions.from }} to {{ transactions.to }} of {{ transactions.total }} logs
                            </div>
                            <div class="flex gap-2">
                                <Link
                                    v-for="link in transactions.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'rounded-lg px-3 py-1.5 text-sm font-medium',
                                        link.active
                                            ? 'bg-[#2f4686] text-white'
                                            : link.url
                                            ? 'bg-white text-gray-700 hover:bg-gray-100'
                                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                ></Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <PaymentModal
            :show="showPaymentModal"
            :reservation="selectedPaymentReservation"
            @close="closePaymentModal"
        />
    </AuthenticatedLayout>
</template>
