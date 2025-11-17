<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import gcashLogo from '../../img/customer_view/GCash_logo.svg';
import mayaLogo from '../../img/customer_view/Maya_logo.svg';
import { offlineStorage } from '../utils/offlineStorage';
import { formatDateTimePH } from '@/utils/timezone';

const props = defineProps({
    reservation: {
        type: Object,
        required: true,
    },
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'updated']);

const processing = ref(false);
const currentTime = ref(new Date());
const extendHours = ref(1);
const showExtendForm = ref(false);
const showPaymentForm = ref(false);
const paymentAmount = ref(0);
const selectedPaymentMethod = ref('');
const wifiCredentials = ref(null);
const isOnline = ref(navigator.onLine);

let timeInterval;

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
};

const formatDateTime = (datetime) => formatDateTimePH(datetime) || '';

const getStatusStyle = (status) => {
    const styles = {
        pending: 'bg-yellow-100 border-yellow-300 text-yellow-800',
        on_hold: 'bg-orange-100 border-orange-300 text-orange-800',
        confirmed: 'bg-blue-100 border-blue-300 text-blue-800',
        active: 'bg-green-100 border-green-300 text-green-800',
        paid: 'bg-emerald-100 border-emerald-300 text-emerald-800',
        partial: 'bg-sky-100 border-sky-300 text-sky-800',
        completed: 'bg-gray-100 border-gray-300 text-gray-600',
        cancelled: 'bg-red-100 border-red-300 text-red-800',
    };
    return styles[status] || styles.pending;
};

const getStatusLabel = (status) => {
    const labels = {
        pending: 'Pending',
        on_hold: 'On Hold',
        confirmed: 'Confirmed',
        active: 'Active',
        paid: 'Paid',
        partial: 'Partial Payment',
        completed: 'Completed',
        cancelled: 'Cancelled',
    };
    return labels[status] || status;
};

// Calculate remaining/elapsed time
const timeInfo = computed(() => {
    if (!props.reservation.end_time) return null;
    
    const start = new Date(props.reservation.start_time);
    const end = new Date(props.reservation.end_time);
    const now = currentTime.value;
    
    const totalDuration = end - start;
    const elapsed = now - start;
    const remaining = end - now;
    
    const elapsedHours = Math.floor(elapsed / (1000 * 60 * 60));
    const elapsedMinutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
    
    const remainingHours = Math.floor(remaining / (1000 * 60 * 60));
    const remainingMinutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
    
    const percentageElapsed = (elapsed / totalDuration) * 100;
    
    return {
        elapsed: {
            hours: elapsedHours,
            minutes: elapsedMinutes,
            display: `${elapsedHours}h ${elapsedMinutes}m`,
        },
        remaining: {
            hours: remainingHours,
            minutes: remainingMinutes,
            display: remaining > 0 ? `${remainingHours}h ${remainingMinutes}m` : 'Expired',
            expired: remaining <= 0,
            urgent: remaining > 0 && remaining < 30 * 60 * 1000, // Less than 30 minutes
        },
        percentage: Math.min(100, Math.max(0, percentageElapsed)),
    };
});

const canExtend = computed(() => {
    return ['active', 'paid'].includes(props.reservation.status) && 
           !timeInfo.value?.remaining.expired;
});

const canEndEarly = computed(() => {
    return ['active'].includes(props.reservation.status) && 
           !timeInfo.value?.remaining.expired;
});

const canPay = computed(() => {
    return ['pending', 'partial', 'on_hold'].includes(props.reservation.status);
});

const canCancel = computed(() => {
    // Allow cancellation only for reservations that haven't started yet
    // Exclude: completed, cancelled, and active (in-progress)
    return !['completed', 'cancelled', 'active'].includes(props.reservation.status);
});

// Calculate countdown until reservation starts
const countdownInfo = computed(() => {
    if (!props.reservation.start_time) return null;
    
    const start = new Date(props.reservation.start_time);
    const now = currentTime.value;
    const diff = start - now;
    
    // If already started or passed
    if (diff <= 0) {
        return {
            hasStarted: true,
            display: 'Started',
            hours: 0,
            minutes: 0,
            seconds: 0,
        };
    }
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    return {
        hasStarted: false,
        display: hours > 0 ? `${hours}h ${minutes}m ${seconds}s` : `${minutes}m ${seconds}s`,
        hours,
        minutes,
        seconds,
    };
});

// Calculate live refund eligibility
const refundEligibility = computed(() => {
    if (!props.reservation.start_time) return null;
    
    const start = new Date(props.reservation.start_time);
    const now = currentTime.value;
    const hoursUntil = (start - now) / (1000 * 60 * 60);
    
    let tier = '';
    let percentage = 0;
    let color = '';
    let icon = '';
    
    if (hoursUntil < 0) {
        tier = 'No refund';
        percentage = 0;
        color = 'bg-gray-100 border-gray-300 text-gray-700';
        icon = '⏱️';
    } else if (hoursUntil >= 12) {
        tier = 'Full refund';
        percentage = 100;
        color = 'bg-green-100 border-green-300 text-green-700';
        icon = '✅';
    } else if (hoursUntil >= 6) {
        tier = 'High refund';
        percentage = 90;
        color = 'bg-emerald-100 border-emerald-300 text-emerald-700';
        icon = '✨';
    } else if (hoursUntil >= 3) {
        tier = 'Good refund';
        percentage = 75;
        color = 'bg-blue-100 border-blue-300 text-blue-700';
        icon = '👍';
    } else if (hoursUntil >= 1) {
        tier = 'Partial refund';
        percentage = 50;
        color = 'bg-orange-100 border-orange-300 text-orange-700';
        icon = '⚠️';
    } else if (hoursUntil > 0) {
        tier = 'Low refund';
        percentage = 25;
        color = 'bg-red-100 border-red-300 text-red-700';
        icon = '🔻';
    } else {
        tier = 'No refund';
        percentage = 0;
        color = 'bg-gray-100 border-gray-300 text-gray-700';
        icon = '⏱️';
    }
    
    return {
        tier,
        percentage,
        color,
        icon,
        hoursUntil: Math.max(0, hoursUntil),
    };
});

// Calculate extension cost
const extensionCost = computed(() => {
    const rate = props.reservation.effective_hourly_rate || 0;
    return rate * extendHours.value;
});

// Handle extend reservation
const handleExtend = () => {
    if (!extendHours.value || extendHours.value < 1) return;
    if (!selectedPaymentMethod.value) return;
    
    processing.value = true;
    router.post(route('reservations.extend', props.reservation.id), {
        hours: extendHours.value,
        payment_method: selectedPaymentMethod.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showExtendForm.value = false;
            selectedPaymentMethod.value = '';
            emit('updated');
            emit('close');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

// Handle end early
const handleEndEarly = () => {
    if (!confirm('Are you sure you want to end this reservation early? Any refund will be processed according to policy.')) {
        return;
    }
    
    processing.value = true;
    router.post(route('reservations.end-early', props.reservation.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated');
            emit('close');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

// Handle payment
const handlePayment = () => {
    if (!paymentAmount.value || paymentAmount.value <= 0) return;
    if (!selectedPaymentMethod.value) {
        alert('Please select a payment method');
        return;
    }
    
    // Mock payment success for GCash/Maya - simulate immediately
    processing.value = true;
    
    // Simulate payment processing delay then proceed
    setTimeout(() => {
        // Send to backend to update reservation
        router.post(route('customer.reservations.pay', props.reservation.id), {
            amount: paymentAmount.value,
            payment_method: selectedPaymentMethod.value,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                processing.value = false;
                showPaymentForm.value = false;
                paymentAmount.value = 0;
                selectedPaymentMethod.value = '';
                
                // Show success message
                alert('✓ Payment Successful!\n\nThis was a mock payment for demonstration purposes. No actual charges were made.');
                
                emit('updated');
                // Don't close immediately, let user see the updated status
                setTimeout(() => {
                    emit('close');
                }, 500);
            },
            onError: (errors) => {
                processing.value = false;
                const errorMsg = errors?.message || 'Payment failed. Please try again.';
                alert('Payment Error: ' + errorMsg);
            },
        });
    }, 1000); // 1 second delay to simulate payment processing
};

// Handle cancel with refund calculation
const calculateRefund = () => {
    if (!props.reservation || props.reservation.amount_paid <= 0) {
        return null;
    }

    const amountPaid = parseFloat(props.reservation.amount_paid);
    const now = new Date();
    const startTime = new Date(props.reservation.start_time);
    const hoursUntilStart = (startTime - now) / (1000 * 60 * 60);

    let refundPercentage = 0;
    let policyMessage = '';

    if (hoursUntilStart >= 12) {
        refundPercentage = 100;
        policyMessage = '12+ hours before start - Full refund';
    } else if (hoursUntilStart >= 6) {
        refundPercentage = 90;
        policyMessage = '6-12 hours before start - 90% refund';
    } else if (hoursUntilStart >= 3) {
        refundPercentage = 75;
        policyMessage = '3-6 hours before start - 75% refund';
    } else if (hoursUntilStart >= 1) {
        refundPercentage = 50;
        policyMessage = '1-3 hours before start - 50% refund';
    } else if (hoursUntilStart > 0) {
        refundPercentage = 25;
        policyMessage = 'Less than 1 hour before start - 25% refund';
    } else {
        refundPercentage = 0;
        policyMessage = 'Reservation already started - No refund';
    }

    const refundAmount = (amountPaid * refundPercentage) / 100;
    const cancellationFee = amountPaid - refundAmount;

    return {
        refundAmount: refundAmount.toFixed(2),
        cancellationFee: cancellationFee.toFixed(2),
        percentage: refundPercentage,
        policyMessage,
        hoursUntilStart: hoursUntilStart.toFixed(1),
    };
};

const handleCancel = () => {
    refundInfo.value = calculateRefund();
    showCancelConfirmation.value = true;
};

const confirmCancel = () => {
    cancelProcessing.value = true;
    router.delete(route('reservations.destroy', props.reservation.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCancelConfirmation.value = false;
            emit('updated');
            emit('close');
        },
        onFinish: () => {
            cancelProcessing.value = false;
        },
    });
};

const closeCancelConfirmation = () => {
    if (!cancelProcessing.value) {
        showCancelConfirmation.value = false;
        refundInfo.value = null;
    }
};

const close = () => {
    if (!processing.value) {
        emit('close');
    }
};

// Generate WiFi credentials
const generateWiFiCredentials = (reservationId) => {
    const timestamp = Date.now();
    const ssid = 'COZ-WORKSPACE';
    const username = `user_${reservationId}_${timestamp.toString().slice(-6)}`;
    const password = btoa(`${reservationId}${timestamp}`).slice(0, 12).toUpperCase();
    
    const credentials = {
        ssid,
        username,
        password,
        expiresAt: props.reservation.end_time,
    };
    
    // Save to offline storage
    offlineStorage.saveWiFiCredentials(credentials);
    
    return credentials;
};

// Copy to clipboard helper
const copyToClipboard = (text, label) => {
    navigator.clipboard.writeText(text).then(() => {
        alert(`${label} copied to clipboard!`);
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
};

// Check if reservation is active and should show WiFi
const showWiFi = computed(() => {
    return ['active', 'paid'].includes(props.reservation.status);
});

// Set default payment amount to remaining balance
const openPaymentForm = () => {
    paymentAmount.value = props.reservation.amount_remaining || props.reservation.total_cost || 0;
    selectedPaymentMethod.value = '';
    showPaymentForm.value = true;
};

onMounted(() => {
    timeInterval = setInterval(() => {
        currentTime.value = new Date();
    }, 1000); // Update every second for accurate timer
    
    // Load WiFi credentials if available
    if (showWiFi.value) {
        const savedWifi = offlineStorage.getWiFiCredentials();
        if (savedWifi && savedWifi.expiresAt === props.reservation.end_time) {
            wifiCredentials.value = savedWifi;
        } else {
            wifiCredentials.value = generateWiFiCredentials(props.reservation.id);
        }
    }
    
    // Track online/offline status
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
});

onBeforeUnmount(() => {
    if (timeInterval) clearInterval(timeInterval);
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
});

const updateOnlineStatus = () => {
    isOnline.value = navigator.onLine;
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/60 transition-opacity" @click="close"></div>
            
            <!-- Modal -->
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="bg-gradient-to-r from-[#2f4686] to-[#3956a3] px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-white">Reservation Details</h3>
                        <p class="text-sm text-blue-100">{{ reservation.space_type?.name || 'Space Reservation' }}</p>
                    </div>
                    <button
                        @click="close"
                        class="p-2 hover:bg-white/20 rounded-lg transition-colors"
                        aria-label="Close"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Status Badge -->
                    <div class="flex items-center justify-between">
                        <span class="px-4 py-2 rounded-lg text-sm font-bold uppercase tracking-wide" :class="getStatusStyle(reservation.status)">
                            {{ getStatusLabel(reservation.status) }}
                        </span>
                        <span class="text-sm text-gray-500">ID: #{{ reservation.id }}</span>
                    </div>

                    <!-- Countdown Timer (for upcoming reservations) -->
                    <div v-if="countdownInfo && !countdownInfo.hasStarted && !['completed', 'cancelled'].includes(reservation.status)" class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4 border-2 border-purple-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-semibold text-gray-700">Time Until Start</span>
                            </div>
                            <span class="text-2xl font-bold text-purple-600 font-mono">
                                {{ countdownInfo.display }}
                            </span>
                        </div>
                        
                        <!-- Live Refund Eligibility -->
                        <div v-if="refundEligibility && reservation.amount_paid > 0" class="mt-3 pt-3 border-t border-purple-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-medium text-gray-600">Cancellation Refund:</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ refundEligibility.icon }}</span>
                                    <span :class="['px-3 py-1 rounded-full text-xs font-bold border-2', refundEligibility.color]">
                                        {{ refundEligibility.percentage }}% - {{ refundEligibility.tier }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-gray-600 text-center">
                                <span v-if="refundEligibility.percentage === 100">Cancel anytime for full refund (12+ hours remaining)</span>
                                <span v-else-if="refundEligibility.percentage === 90">Refund drops to 75% in {{ Math.floor(refundEligibility.hoursUntil - 6) }}h {{ Math.floor(((refundEligibility.hoursUntil - 6) % 1) * 60) }}m</span>
                                <span v-else-if="refundEligibility.percentage === 75">Refund drops to 50% in {{ Math.floor(refundEligibility.hoursUntil - 3) }}h {{ Math.floor(((refundEligibility.hoursUntil - 3) % 1) * 60) }}m</span>
                                <span v-else-if="refundEligibility.percentage === 50">Refund drops to 25% in {{ Math.floor(refundEligibility.hoursUntil - 1) }}h {{ Math.floor(((refundEligibility.hoursUntil - 1) % 1) * 60) }}m</span>
                                <span v-else-if="refundEligibility.percentage === 25">Refund drops to 0% at start time</span>
                                <span v-else>No refund available (reservation started)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Timer (for active reservations) -->
                    <div v-if="reservation.status === 'active' && timeInfo" class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4 border-2 border-blue-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-semibold text-gray-700">Session Progress</span>
                            </div>
                            <span class="text-2xl font-bold" :class="timeInfo.remaining.urgent ? 'text-red-600 animate-pulse' : 'text-blue-600'">
                                {{ timeInfo.remaining.display }}
                            </span>
                        </div>
                        
                        <!-- Progress bar -->
                        <div class="relative h-3 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                class="absolute inset-y-0 left-0 rounded-full transition-all duration-1000"
                                :class="timeInfo.remaining.urgent ? 'bg-red-500' : 'bg-blue-500'"
                                :style="{ width: `${timeInfo.percentage}%` }"
                            ></div>
                        </div>
                        
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                            <span>Elapsed: {{ timeInfo.elapsed.display }}</span>
                            <span>{{ Math.round(timeInfo.percentage) }}% complete</span>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="space-y-3">
                        <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#2f4686]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Customer Information
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Name</div>
                                <div class="font-medium">{{ reservation.customer?.name || reservation.customer_name || 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Email</div>
                                <div class="font-medium">{{ reservation.customer?.email || reservation.customer_email || 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Phone</div>
                                <div class="font-medium">{{ reservation.customer?.phone || reservation.customer_phone || 'N/A' }}</div>
                            </div>
                            <div v-if="reservation.customer?.company_name || reservation.customer_company_name">
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Company</div>
                                <div class="font-medium">{{ reservation.customer?.company_name || reservation.customer_company_name }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Reservation Details -->
                    <div class="space-y-3">
                        <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#2f4686]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Booking Information
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Start Time</div>
                                <div class="font-medium">{{ formatDateTime(reservation.start_time) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">End Time</div>
                                <div class="font-medium">{{ formatDateTime(reservation.end_time) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Duration</div>
                                <div class="font-medium">{{ reservation.hours }} hour(s)</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Pax</div>
                                <div class="font-medium">{{ reservation.pax }} person(s)</div>
                            </div>
                            <div>
                                <div class="text-gray-500 text-xs uppercase tracking-wide mb-1">Payment Method</div>
                                <div class="font-medium uppercase">{{ reservation.payment_method || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                        <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#2f4686]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Payment Summary
                        </h4>
                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Cost</span>
                                <span class="font-semibold">{{ formatCurrency(reservation.total_cost) }}</span>
                            </div>
                            <div v-if="reservation.amount_paid > 0" class="flex justify-between text-green-600">
                                <span>Amount Paid</span>
                                <span class="font-semibold">{{ formatCurrency(reservation.amount_paid) }}</span>
                            </div>
                            <div v-if="reservation.is_partially_paid" class="flex justify-between text-orange-600 font-bold">
                                <span>Remaining Balance</span>
                                <span>{{ formatCurrency(reservation.amount_remaining) }}</span>
                            </div>
                            <div v-else-if="reservation.status === 'paid' || reservation.status === 'completed'" class="flex justify-between text-green-600 font-bold">
                                <span>Status</span>
                                <span>✓ Fully Paid</span>
                            </div>
                        </div>
                    </div>

                    <!-- Refund Policy Information -->
                    <div v-if="!['completed', 'cancelled'].includes(reservation.status)" class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
                        <div class="flex items-start gap-2">
                            <svg class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="flex-1">
                                <h4 class="font-semibold text-amber-900 mb-2">Cancellation & Refund Policy</h4>
                                <div class="text-xs text-amber-800 space-y-1.5">
                                    <div class="flex justify-between items-center py-1 border-b border-amber-200">
                                        <span class="font-medium">12+ hours before start</span>
                                        <span class="font-bold text-green-700">100% refund</span>
                                    </div>
                                    <div class="flex justify-between items-center py-1 border-b border-amber-200">
                                        <span class="font-medium">6-12 hours before</span>
                                        <span class="font-bold text-emerald-700">90% refund</span>
                                    </div>
                                    <div class="flex justify-between items-center py-1 border-b border-amber-200">
                                        <span class="font-medium">3-6 hours before</span>
                                        <span class="font-bold text-blue-700">75% refund</span>
                                    </div>
                                    <div class="flex justify-between items-center py-1 border-b border-amber-200">
                                        <span class="font-medium">1-3 hours before</span>
                                        <span class="font-bold text-orange-700">50% refund</span>
                                    </div>
                                    <div class="flex justify-between items-center py-1">
                                        <span class="font-medium">Less than 1 hour</span>
                                        <span class="font-bold text-red-700">25% refund</span>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-amber-700 italic flex items-start gap-1">
                                    <svg class="h-3 w-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>The refund percentage is based on time before your reservation start time. Cancellation fees are deducted from the total amount paid.</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- WiFi Credentials (for active/paid reservations) -->
                    <div v-if="showWiFi && wifiCredentials" class="bg-blue-50 border-2 border-blue-300 rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071a11 11 0 0114.14 0M1.394 9.393a15.5 15.5 0 0121.213 0" />
                                </svg>
                                <h4 class="font-semibold text-blue-900">WiFi Access Credentials</h4>
                                <span v-if="!isOnline" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                    </svg>
                                    Offline
                                </span>
                            </div>
                        </div>
                        
                        <div v-if="!isOnline" class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-600 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-xs text-amber-800">
                                <strong>You're offline.</strong> Your credentials are saved and will remain available when you reconnect.
                            </p>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="bg-white rounded-lg p-3 space-y-1">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-blue-700 font-semibold">Network Name (SSID)</span>
                                    <button @click="copyToClipboard(wifiCredentials.ssid, 'SSID')" class="text-blue-600 hover:text-blue-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                                <p class="font-mono text-sm font-bold text-blue-900">{{ wifiCredentials.ssid }}</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-3 space-y-1">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-blue-700 font-semibold">Username</span>
                                    <button @click="copyToClipboard(wifiCredentials.username, 'Username')" class="text-blue-600 hover:text-blue-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                                <p class="font-mono text-sm font-bold text-blue-900">{{ wifiCredentials.username }}</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-3 space-y-1">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-blue-700 font-semibold">Password</span>
                                    <button @click="copyToClipboard(wifiCredentials.password, 'Password')" class="text-blue-600 hover:text-blue-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                                <p class="font-mono text-sm font-bold text-blue-900">{{ wifiCredentials.password }}</p>
                            </div>
                            
                            <p class="text-[10px] text-blue-700 italic flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Credentials valid until {{ formatDateTime(reservation.end_time) }}
                            </p>
                        </div>
                    </div>

                    <!-- Extend Form -->
                    <div v-if="showExtendForm" class="bg-blue-50 rounded-xl p-4 border border-blue-200 space-y-3">
                        <h4 class="font-semibold text-gray-900">Extend Reservation</h4>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Additional Hours
                            </label>
                            <input
                                v-model.number="extendHours"
                                type="number"
                                min="1"
                                max="12"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2f4686] focus:border-transparent"
                            />
                            <div class="text-sm text-gray-600">
                                Extension Cost: <span class="font-bold text-[#2f4686]">{{ formatCurrency(extensionCost) }}</span>
                            </div>
                        </div>

                        <!-- Payment Method Selection for Extension -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Payment Method
                            </label>
                            
                            <!-- Show all options if reservation is active (ongoing) -->
                            <template v-if="reservation.status === 'active'">
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        type="button"
                                        @click="selectedPaymentMethod = 'cash'"
                                        class="relative flex flex-col items-center justify-center p-3 border-2 rounded-lg transition-all hover:shadow-md"
                                        :class="selectedPaymentMethod === 'cash' ? 'border-[#2f4686] bg-blue-50 shadow-md' : 'border-gray-300 hover:border-gray-400'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mb-1" :class="selectedPaymentMethod === 'cash' ? 'text-[#2f4686]' : 'text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <span class="text-xs font-semibold">Cash</span>
                                        <div v-if="selectedPaymentMethod === 'cash'" class="absolute top-1 right-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#2f4686]" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                    <button
                                        type="button"
                                        @click="selectedPaymentMethod = 'gcash'"
                                        class="relative flex flex-col items-center justify-center p-3 border-2 rounded-lg transition-all hover:shadow-md"
                                        :class="selectedPaymentMethod === 'gcash' ? 'border-[#2f4686] bg-blue-50 shadow-md' : 'border-gray-300 hover:border-gray-400'"
                                    >
                                        <img :src="gcashLogo" alt="GCash" class="h-8 mb-1" />
                                        <span class="text-xs font-semibold">GCash</span>
                                        <div v-if="selectedPaymentMethod === 'gcash'" class="absolute top-1 right-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#2f4686]" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                    <button
                                        type="button"
                                        @click="selectedPaymentMethod = 'maya'"
                                        class="relative flex flex-col items-center justify-center p-3 border-2 rounded-lg transition-all hover:shadow-md"
                                        :class="selectedPaymentMethod === 'maya' ? 'border-[#2f4686] bg-blue-50 shadow-md' : 'border-gray-300 hover:border-gray-400'"
                                    >
                                        <img :src="mayaLogo" alt="Maya" class="h-8 mb-1" />
                                        <span class="text-xs font-semibold">Maya</span>
                                        <div v-if="selectedPaymentMethod === 'maya'" class="absolute top-1 right-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#2f4686]" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </div>
                                <p class="text-xs text-blue-700 italic">
                                    💡 Cash payment available because your reservation is currently active
                                </p>
                            </template>
                            
                            <!-- Only online payments if not yet active -->
                            <template v-else>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        @click="selectedPaymentMethod = 'gcash'"
                                        class="relative flex flex-col items-center justify-center p-3 border-2 rounded-lg transition-all hover:shadow-md"
                                        :class="selectedPaymentMethod === 'gcash' ? 'border-[#2f4686] bg-blue-50 shadow-md' : 'border-gray-300 hover:border-gray-400'"
                                    >
                                        <img :src="gcashLogo" alt="GCash" class="h-8 mb-1" />
                                        <span class="text-xs font-semibold">GCash</span>
                                        <div v-if="selectedPaymentMethod === 'gcash'" class="absolute top-1 right-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#2f4686]" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                    <button
                                        type="button"
                                        @click="selectedPaymentMethod = 'maya'"
                                        class="relative flex flex-col items-center justify-center p-3 border-2 rounded-lg transition-all hover:shadow-md"
                                        :class="selectedPaymentMethod === 'maya' ? 'border-[#2f4686] bg-blue-50 shadow-md' : 'border-gray-300 hover:border-gray-400'"
                                    >
                                        <img :src="mayaLogo" alt="Maya" class="h-8 mb-1" />
                                        <span class="text-xs font-semibold">Maya</span>
                                        <div v-if="selectedPaymentMethod === 'maya'" class="absolute top-1 right-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#2f4686]" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </div>
                                <p class="text-xs text-amber-700 italic flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    Cash payment only available during active reservations
                                </p>
                            </template>
                            <p v-if="!selectedPaymentMethod" class="text-xs text-red-500 mt-1">Please select a payment method</p>
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="handleExtend"
                                :disabled="processing || !extendHours || extendHours < 1 || !selectedPaymentMethod"
                                class="flex-1 px-4 py-2 bg-[#2f4686] hover:bg-[#3956a3] text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ processing ? 'Processing...' : 'Confirm Extension' }}
                            </button>
                            <button
                                @click="showExtendForm = false; selectedPaymentMethod = ''"
                                :disabled="processing"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <div v-if="showPaymentForm" class="bg-green-50 rounded-xl p-4 border border-green-200 space-y-3">
                        <h4 class="font-semibold text-gray-900">Make Payment</h4>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Payment Amount
                            </label>
                            <input
                                v-model.number="paymentAmount"
                                type="number"
                                :min="0"
                                :max="reservation.amount_remaining || reservation.total_cost"
                                step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2f4686] focus:border-transparent"
                            />
                            <div class="text-xs text-gray-600">
                                Maximum: {{ formatCurrency(reservation.amount_remaining || reservation.total_cost) }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Payment Method
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    @click="selectedPaymentMethod = 'gcash'"
                                    class="relative flex flex-col items-center justify-center p-4 border-2 rounded-xl transition-all hover:shadow-md"
                                    :class="selectedPaymentMethod === 'gcash' ? 'border-[#2f4686] bg-blue-50 shadow-md' : 'border-gray-300 hover:border-gray-400'"
                                >
                                    <img :src="gcashLogo" alt="GCash" class="h-10 mb-2" />
                                    <span class="text-sm font-semibold">GCash</span>
                                    <div v-if="selectedPaymentMethod === 'gcash'" class="absolute top-2 right-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#2f4686]" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    @click="selectedPaymentMethod = 'maya'"
                                    class="relative flex flex-col items-center justify-center p-4 border-2 rounded-xl transition-all hover:shadow-md"
                                    :class="selectedPaymentMethod === 'maya' ? 'border-[#2f4686] bg-blue-50 shadow-md' : 'border-gray-300 hover:border-gray-400'"
                                >
                                    <img :src="mayaLogo" alt="Maya" class="h-10 mb-2" />
                                    <span class="text-sm font-semibold">Maya</span>
                                    <div v-if="selectedPaymentMethod === 'maya'" class="absolute top-2 right-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#2f4686]" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 italic mt-2">
                                <strong>Note:</strong> GCash and Maya payments are mock transactions for demonstration purposes only.
                            </p>
                            <p v-if="!selectedPaymentMethod" class="text-xs text-red-500 mt-1">Please select a payment method</p>
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="handlePayment"
                                :disabled="processing || !paymentAmount || paymentAmount <= 0 || !selectedPaymentMethod"
                                class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <svg v-if="processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ processing ? 'Processing Payment...' : 'Submit Payment' }}</span>
                            </button>
                            <button
                                @click="showPaymentForm = false; selectedPaymentMethod = ''"
                                :disabled="processing"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-lg transition-colors disabled:opacity-50"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex flex-wrap gap-2">
                    <button
                        v-if="canExtend && !showExtendForm"
                        @click="showExtendForm = true"
                        :disabled="processing"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Extend Time
                    </button>
                    
                    <button
                        v-if="canPay && !showPaymentForm"
                        @click="openPaymentForm"
                        :disabled="processing"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Pay Balance
                    </button>
                    
                    <button
                        v-if="canEndEarly"
                        @click="handleEndEarly"
                        :disabled="processing"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg transition-colors disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                        </svg>
                        End Early
                    </button>
                    
                    <button
                        v-if="canCancel"
                        @click="handleCancel"
                        :disabled="processing"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancellation Confirmation Modal -->
    <div v-if="showCancelConfirmation" class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900">Cancel Reservation</h3>
                    <p class="text-sm text-gray-600 mt-1">Are you sure you want to cancel this reservation?</p>
                </div>
            </div>

            <!-- Refund Information -->
            <div v-if="refundInfo && reservation.amount_paid > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-3">
                <h4 class="font-semibold text-blue-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Refund Calculation
                </h4>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-700">Original Payment:</span>
                        <span class="font-semibold">₱{{ parseFloat(reservation.amount_paid).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-green-600">
                        <span>Refund Amount ({{ refundInfo.percentage }}%):</span>
                        <span class="font-bold">₱{{ refundInfo.refundAmount }}</span>
                    </div>
                    <div v-if="refundInfo.cancellationFee > 0" class="flex justify-between text-orange-600">
                        <span>Cancellation Fee:</span>
                        <span class="font-semibold">₱{{ refundInfo.cancellationFee }}</span>
                    </div>
                    <div class="pt-2 border-t border-blue-200">
                        <p class="text-xs text-blue-700 italic">{{ refundInfo.policyMessage }}</p>
                        <p v-if="refundInfo.hoursUntilStart > 0" class="text-xs text-gray-600 mt-1">
                            Time until start: {{ refundInfo.hoursUntilStart }} hours
                        </p>
                    </div>
                </div>

                <div v-if="refundInfo.refundAmount > 0" class="bg-white rounded-lg p-3 border border-blue-200">
                    <p class="text-xs text-gray-700">
                        <strong>Refund Request Details:</strong><br>
                        Amount: ₱{{ refundInfo.refundAmount }} to {{ reservation.payment_method?.toUpperCase() }} account
                    </p>
                    <p class="text-xs text-amber-600 mt-2 font-semibold flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Pending Admin Approval
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Your refund request will be reviewed and processed by an administrator. You will be notified once approved.</p>
                </div>
            </div>

            <div v-else-if="reservation.amount_paid > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-700">
                    <strong>No payment refund:</strong><br>
                    This reservation has already started or is past the refund window.
                </p>
            </div>

            <div v-else class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-700">
                    No payment has been made for this reservation.
                </p>
            </div>

            <!-- Warning message -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-xs text-red-700 font-medium">
                    ⚠️ This action cannot be undone. Your reservation will be permanently cancelled.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button
                    @click="closeCancelConfirmation"
                    :disabled="cancelProcessing"
                    class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-lg transition-colors disabled:opacity-50"
                >
                    Keep Reservation
                </button>
                <button
                    @click="confirmCancel"
                    :disabled="cancelProcessing"
                    class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                >
                    <svg v-if="cancelProcessing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ cancelProcessing ? 'Cancelling...' : 'Confirm Cancellation' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
