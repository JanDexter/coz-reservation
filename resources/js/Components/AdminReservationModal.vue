<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    reservation: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'updated']);

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'active', label: 'Active' },
    { value: 'partial', label: 'Partial Payment' },
    { value: 'paid', label: 'Paid' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const paymentOptions = [
    { value: '', label: 'Select method' },
    { value: 'cash', label: 'Cash' },
    { value: 'gcash', label: 'GCash' },
    { value: 'maya', label: 'Maya' },
    { value: 'card', label: 'Card' },
    { value: 'bank', label: 'Bank Transfer' },
];

const localReservation = ref(null);
const closingReservation = ref(false);
const cancellingReservation = ref(false);
const showCancelModal = ref(false);
const cancelReason = ref('');

const form = useForm({
    status: 'pending',
    payment_method: '',
    amount_paid: '',
    start_time: '',
    end_time: '',
    hours: '',
    pax: '',
    notes: '',
    remove_discount: false,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(value ?? 0));
};

const toInputDateTime = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (num) => String(num).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const totalCost = computed(() => Number(localReservation.value?.total_cost ?? 0));

const remainingBalance = computed(() => {
    if (!localReservation.value) return 0;
    const cost = Number(localReservation.value.total_cost ?? 0);
    const paid = Number(form.amount_paid || 0);
    return cost - paid;
});

watch(
    () => props.reservation,
    (reservation) => {
        if (!reservation) {
            localReservation.value = null;
            form.reset();
            form.clearErrors();
            return;
        }

        // Clone to avoid mutating parent reference
        localReservation.value = JSON.parse(JSON.stringify(reservation));

        form.defaults({
            status: reservation.status || 'pending',
            payment_method: reservation.payment_method || '',
            amount_paid: reservation.amount_paid ?? '',
            start_time: toInputDateTime(reservation.start_time),
            end_time: toInputDateTime(reservation.end_time),
            hours: reservation.hours ?? '',
            pax: reservation.pax ?? '',
            notes: reservation.notes || '',
            remove_discount: false,
        });
        form.reset();
        form.clearErrors();
    },
    { immediate: true }
);

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            form.clearErrors();
            closingReservation.value = false;
        }
    }
);

const handleClose = () => {
    if (!form.processing && !closingReservation.value) {
        emit('close');
    }
};

const submit = () => {
    if (!localReservation.value) return;

    form
        .transform((data) => ({
            ...data,
            amount_paid: data.amount_paid === '' ? null : Number(data.amount_paid),
            hours: data.hours === '' ? null : Number(data.hours),
            pax: data.pax === '' ? null : Number(data.pax),
            start_time: data.start_time ? new Date(data.start_time).toISOString() : null,
            end_time: data.end_time ? new Date(data.end_time).toISOString() : null,
        }))
        .put(route('admin.reservations.update', localReservation.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated');
            },
            onFinish: () => {
                form.transform((payload) => payload); // reset transform pipeline
            },
        });
};

const closeReservation = () => {
    if (!localReservation.value || closingReservation.value) return;
    if (!confirm('Mark this reservation as completed?')) {
        return;
    }

    closingReservation.value = true;
    router.post(route('admin.reservations.close', localReservation.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated');
        },
        onFinish: () => {
            closingReservation.value = false;
        },
    });
};

const openCancelModal = () => {
    if (localReservation.value && 
        !['completed', 'cancelled'].includes(localReservation.value.status)) {
        showCancelModal.value = true;
        cancelReason.value = '';
    }
};

const closeCancelModal = () => {
    if (!cancellingReservation.value) {
        showCancelModal.value = false;
        cancelReason.value = '';
    }
};

const cancelReservation = () => {
    if (!localReservation.value || cancellingReservation.value) return;

    cancellingReservation.value = true;
    router.post(
        route('admin.reservations.cancel', localReservation.value.id),
        { reason: cancelReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCancelModal.value = false;
                cancelReason.value = '';
                emit('updated');
            },
            onFinish: () => {
                cancellingReservation.value = false;
            },
        }
    );
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
        <div class="absolute inset-0 bg-black/60" @click="handleClose"></div>
        <div class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-center justify-between bg-gradient-to-r from-[#2f4686] to-[#3956a3] px-6 py-4 text-white">
                <div>
                    <h2 class="text-xl font-semibold">Transaction Details</h2>
                    <p v-if="localReservation" class="text-sm text-blue-100">Reservation #{{ localReservation.id }}</p>
                </div>
                <button type="button" class="rounded-lg p-2 hover:bg-white/15" @click="handleClose" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </header>

            <section class="grid gap-6 px-6 py-6 md:grid-cols-[1.2fr_1fr]">
                <div class="space-y-5">
                    <div class="border border-gray-200 rounded-xl p-4">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Customer & Session</h3>
                        <div class="space-y-2 text-sm text-gray-700">
                            <p v-if="localReservation?.customer"><span class="font-semibold text-gray-900">Customer:</span> {{ localReservation.customer.name }}</p>
                            <p v-if="localReservation?.customer?.email"><span class="font-semibold text-gray-900">Email:</span> {{ localReservation.customer.email }}</p>
                            <p v-if="localReservation?.customer?.phone"><span class="font-semibold text-gray-900">Phone:</span> {{ localReservation.customer.phone }}</p>
                            <p v-if="localReservation?.space_type"><span class="font-semibold text-gray-900">Space Type:</span> {{ localReservation.space_type.name }}</p>
                            <p v-if="localReservation?.space"><span class="font-semibold text-gray-900">Space:</span> {{ localReservation.space.name }}</p>
                            <p v-if="localReservation?.hours"><span class="font-semibold text-gray-900">Hours:</span> {{ localReservation.hours }}</p>
                            <p v-if="localReservation?.pax"><span class="font-semibold text-gray-900">Pax:</span> {{ localReservation.pax }}</p>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Payment Summary</h3>
                        <ul class="space-y-2 text-sm">
                            <li v-if="localReservation?.is_discounted" class="flex items-center justify-between">
                                <span class="text-gray-600">Discount Applied</span>
                                <span class="font-semibold text-emerald-600">{{ localReservation.applied_discount_percentage }}%</span>
                            </li>
                            <li class="flex items-center justify-between">
                                <span class="text-gray-600">Total Cost</span>
                                <span class="font-semibold text-gray-900">{{ formatCurrency(totalCost) }}</span>
                            </li>
                            <li class="flex items-center justify-between">
                                <span class="text-gray-600">Amount Paid</span>
                                <span class="font-semibold text-green-600">{{ formatCurrency(form.amount_paid || 0) }}</span>
                            </li>
                            <li class="flex items-center justify-between">
                                <span class="text-gray-600">Balance</span>
                                <span :class="remainingBalance <= 0 ? 'font-semibold text-emerald-600' : 'font-semibold text-orange-600'">
                                    {{ formatCurrency(remainingBalance) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="status">
                                Status
                            </label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                            >
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.status" class="mt-1 text-xs text-red-500">{{ form.errors.status }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="payment_method">
                                Payment Method
                            </label>
                            <select
                                id="payment_method"
                                v-model="form.payment_method"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                            >
                                <option v-for="option in paymentOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.payment_method" class="mt-1 text-xs text-red-500">{{ form.errors.payment_method }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="amount_paid">
                                Amount Paid
                            </label>
                            <input
                                id="amount_paid"
                                v-model.number="form.amount_paid"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                            />
                            <p v-if="form.errors.amount_paid" class="mt-1 text-xs text-red-500">{{ form.errors.amount_paid }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="start_time">
                                    Start Time
                                </label>
                                <input
                                    id="start_time"
                                    v-model="form.start_time"
                                    type="datetime-local"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                                />
                                <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-500">{{ form.errors.start_time }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="end_time">
                                    End Time
                                </label>
                                <input
                                    id="end_time"
                                    v-model="form.end_time"
                                    type="datetime-local"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                                />
                                <p v-if="form.errors.end_time" class="mt-1 text-xs text-red-500">{{ form.errors.end_time }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="hours">
                                    Hours
                                </label>
                                <input
                                    id="hours"
                                    v-model.number="form.hours"
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                                />
                                <p v-if="form.errors.hours" class="mt-1 text-xs text-red-500">{{ form.errors.hours }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="pax">
                                    Pax
                                </label>
                                <input
                                    id="pax"
                                    v-model.number="form.pax"
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                                />
                                <p v-if="form.errors.pax" class="mt-1 text-xs text-red-500">{{ form.errors.pax }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="notes">
                                Notes
                            </label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#2f4686] focus:ring-[#2f4686]"
                            ></textarea>
                            <p v-if="form.errors.notes" class="mt-1 text-xs text-red-500">{{ form.errors.notes }}</p>
                        </div>

                        <!-- Discount Removal Option (only for future reservations with discount) -->
                        <div v-if="localReservation?.is_future && localReservation?.is_discounted" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.remove_discount"
                                    class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                />
                                <div class="flex-1">
                                    <span class="block text-sm font-semibold text-gray-900">Remove Discount</span>
                                    <span class="block text-xs text-gray-600">
                                        This will remove the {{ localReservation.applied_discount_percentage }}% discount and recalculate the total cost.
                                    </span>
                                </div>
                            </label>
                            <p v-if="form.errors.remove_discount" class="mt-2 text-xs text-red-500">{{ form.errors.remove_discount }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500">
                    Updated {{ new Date().toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', month: 'short', day: 'numeric' }) }}
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100"
                        :disabled="form.processing || closingReservation || cancellingReservation"
                        @click="handleClose"
                    >
                        Close
                    </button>
                    <button
                        v-if="localReservation && !['completed', 'cancelled', 'active'].includes(localReservation.status)"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || closingReservation || cancellingReservation"
                        @click="openCancelModal"
                    >
                        Cancel Reservation
                    </button>
                    <button
                        v-if="localReservation && localReservation.status === 'active'"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || closingReservation || cancellingReservation"
                        @click="closeReservation"
                    >
                        {{ closingReservation ? 'Closing…' : 'Mark as Completed' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-[#2f4686] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#3956a3] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || closingReservation || cancellingReservation"
                        @click="submit"
                    >
                        {{ form.processing ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </footer>
        </div>
    </div>

    <!-- Cancel Reservation Modal -->
    <div v-if="showCancelModal" class="fixed inset-0 z-[60] flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/70" @click="closeCancelModal"></div>
        <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 text-white">
                <h3 class="text-lg font-semibold">Cancel Reservation</h3>
                <p class="text-sm text-red-100">This action will cancel the reservation and process any applicable refunds</p>
            </header>

            <div class="px-6 py-6">
                <div class="mb-4 rounded-lg bg-yellow-50 border border-yellow-200 p-4">
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="text-sm text-yellow-800">
                            <p class="font-semibold mb-1">Cancellation Policy</p>
                            <ul class="list-disc list-inside space-y-0.5 text-xs">
                                <li>24+ hours before: 100% refund</li>
                                <li>12-24 hours: 75% refund</li>
                                <li>6-12 hours: 50% refund</li>
                                <li>Less than 6 hours: 25% refund</li>
                                <li>After start time: No refund</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700" for="cancel-reason">
                        Reason for Cancellation (Optional)
                    </label>
                    <textarea
                        id="cancel-reason"
                        v-model="cancelReason"
                        rows="3"
                        placeholder="Enter reason for cancelling this reservation..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500"
                        :disabled="cancellingReservation"
                    ></textarea>
                </div>
            </div>

            <footer class="flex gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button
                    type="button"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100"
                    :disabled="cancellingReservation"
                    @click="closeCancelModal"
                >
                    Keep Reservation
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                    :disabled="cancellingReservation"
                    @click="cancelReservation"
                >
                    {{ cancellingReservation ? 'Cancelling…' : 'Confirm Cancellation' }}
                </button>
            </footer>
        </div>
    </div>
</template>
