<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import CustomerQuickCreateModal from '@/Components/CustomerQuickCreateModal.vue';
import PaymentModal from '@/Components/PaymentModal.vue';
import { formatDatePH, formatDateTimePH } from '@/utils/timezone';

const onRowClick = (space, event) => {
    if (event.target.closest('a, button, input')) {
        return;
    }
    // Assuming you have a route for showing space details
    // router.get(route('space-management.show', space.id));
};

const props = defineProps({
    spaceTypes: Array,
    customers: Array,
});

const updatingPricing = ref(null);
const assigningSpace = ref(null);
const showCustomerModal = ref(false);
const selectedSpaceForAssignment = ref(null);
const showCreateCustomerForm = ref(false);
const customerSearch = ref('');
const customerInputDirty = ref(false);
const showCustomerDropdown = ref(false);
const highlightedCustomerIndex = ref(0);
const customerDropdownRef = ref(null);
const showPaymentModal = ref(false);
const selectedPaymentSpace = ref(null);
const isOpenTimeMode = ref(false); // Track if we're starting an open time session
// Pricing modal state
const showPricingModalId = ref(null); // spaceTypeId or null
const pricingForm = ref({ hourly_rate: '', pricing_type: 'per_person', default_discount_hours: '', default_discount_percentage: '' });
const pricingErrors = ref({ hourly_rate: '', pricing_type: '', default_discount_hours: '', default_discount_percentage: '' });

// Details modal state (name, description, photo)
const showDetailsModalId = ref(null); // spaceTypeId or null
const detailsForm = ref({ name: '', description: '', photo: null, remove_photo: false });
const detailsErrors = ref({ name: '', description: '', photo: '' });
const detailsPreviewUrl = ref('');

// Local toast notifications
const toast = ref({ show: false, type: 'success', message: '' });
let toastTimerId = null;
const showToast = (message, type = 'success', duration = 3000) => {
    toast.value = { show: true, type, message };
    if (toastTimerId) clearTimeout(toastTimerId);
    toastTimerId = setTimeout(() => { toast.value.show = false; }, duration);
};
// Open Time UI state
const openTimeSubmitting = ref(false);

const openDetailsModal = (spaceType) => {
    showDetailsModalId.value = spaceType.id;
    detailsForm.value = {
        name: spaceType.name || '',
        description: spaceType.description || '',
        photo: null,
        remove_photo: false,
    };
    detailsErrors.value = { name: '', description: '', photo: '' };
    // Try to use server photo if present for initial preview
    detailsPreviewUrl.value = spaceType.photo_path ? (route('customer.view') + 'storage/' + spaceType.photo_path).replace(/\/\/+/, '/') : '';
};

const closeDetailsModal = () => {
    showDetailsModalId.value = null;
};

const onPhotoSelected = (e) => {
    const file = e?.target?.files?.[0] || null;
    detailsForm.value.photo = file;
    detailsForm.value.remove_photo = false;
    if (detailsPreviewUrl.value) try { URL.revokeObjectURL(detailsPreviewUrl.value); } catch(_) {}
    detailsPreviewUrl.value = file ? URL.createObjectURL(file) : '';
};

const validateDetails = () => {
    detailsErrors.value = { name: '', description: '', photo: '' };
    let ok = true;
    const v = detailsForm.value || {};
    if (!v.name || !String(v.name).trim()) {
        detailsErrors.value.name = 'Name is required';
        ok = false;
    }
    if (v.photo) {
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowed.includes(v.photo.type)) {
            detailsErrors.value.photo = 'Photo must be JPG, PNG, or WEBP';
            ok = false;
        }
        const maxBytes = 5 * 1024 * 1024;
        if (v.photo.size > maxBytes) {
            detailsErrors.value.photo = 'Photo must be smaller than 5MB';
            ok = false;
        }
    }
    return ok;
};

const savingDetails = ref(false);
const saveDetailsModal = () => {
    const id = showDetailsModalId.value;
    if (!id) return;
    if (!validateDetails()) return;
    savingDetails.value = true;

    const formData = new FormData();
    formData.append('_method', 'PATCH');
    formData.append('name', detailsForm.value.name || '');
    formData.append('description', detailsForm.value.description || '');
    if (detailsForm.value.photo) {
        formData.append('photo', detailsForm.value.photo);
    }
    if (detailsForm.value.remove_photo && !detailsForm.value.photo) {
        formData.append('remove_photo', '1');
    }

    router.post(route('space-management.update-details', id), formData, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            detailsErrors.value.name = errors?.name || '';
            detailsErrors.value.description = errors?.description || '';
            detailsErrors.value.photo = errors?.photo || '';
            showToast('Failed to save details. Please check the form and try again.', 'error');
            savingDetails.value = false;
        },
        onSuccess: () => {
            savingDetails.value = false;
            closeDetailsModal();
            showToast('Details updated successfully.', 'success');
            router.reload({ only: ['spaceTypes'] });
        },
        onFinish: () => {
            savingDetails.value = false;
        }
    });
};

const hasCustomers = computed(() => props.customers && props.customers.length > 0);

const newCustomerForm = useForm({
    name: '',
    company_name: '',
    email: '',
    phone: '',
    status: 'active',
});

// Timezone helpers (force Asia/Manila across all clients)
const PH_OFFSET_MINUTES = 8 * 60; // UTC+8, no DST in PH
const pad2 = (n) => String(n).padStart(2, '0');

const getRoundedNow = () => {
    const now = new Date();
    now.setSeconds(0, 0);
    return now;
};

// Convert a Date (point in time) into a datetime-local string in Asia/Manila
const toLocalDateTimeInput = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const normalized = new Date(date.getTime());
    normalized.setSeconds(0, 0);
    // Build a UTC timestamp from the Date's UTC components
    const utcMs = Date.UTC(
        normalized.getUTCFullYear(),
        normalized.getUTCMonth(),
        normalized.getUTCDate(),
        normalized.getUTCHours(),
        normalized.getUTCMinutes()
    );
    // Shift to PH local time by adding +08:00
    const phMs = utcMs + PH_OFFSET_MINUTES * 60 * 1000;
    const ph = new Date(phMs);
    const y = ph.getUTCFullYear();
    const m = pad2(ph.getUTCMonth() + 1);
    const d = pad2(ph.getUTCDate());
    const hh = pad2(ph.getUTCSeconds() === 60 ? ph.getUTCHours() + 1 : ph.getUTCHours());
    const mm = pad2(ph.getUTCMinutes());
    return `${y}-${m}-${d}T${hh}:${mm}`;
};

const nowLocalDateTime = () => toLocalDateTimeInput(getRoundedNow());

// Given a datetime-local string in PH, add 1 hour and return a new PH datetime-local string
const computeDefaultEndTime = (startValue) => {
    if (!startValue) return '';
    const [datePart, timePart] = String(startValue).split('T');
    if (!datePart || !timePart) return '';
    const [y, m, d] = datePart.split('-').map((n) => parseInt(n, 10));
    const [hh, mm] = timePart.split(':').map((n) => parseInt(n, 10));
    if ([y, m, d, hh, mm].some((v) => Number.isNaN(v))) return '';
    // Convert PH local to UTC by subtracting 8 hours
    const utcMs = Date.UTC(y, m - 1, d, hh - 8, mm, 0, 0);
    const endUtcMs = utcMs + 60 * 60 * 1000; // +1 hour
    return toLocalDateTimeInput(new Date(endUtcMs));
};

const getDefaultAssignment = () => {
    const start = nowLocalDateTime();
    return {
        customer_id: null,
        start_time: start,
        occupied_until: computeDefaultEndTime(start),
        custom_hourly_rate: '',
    };
};

// Assignment form state
const assignment = ref(getDefaultAssignment());

const startTimeDirty = ref(false);
const endTimeDirty = ref(false);
let startTimeSyncId = null;

const updateStartTimeToNow = () => {
    const start = nowLocalDateTime();
    assignment.value.start_time = start;
    startTimeDirty.value = false;
    assignment.value.occupied_until = computeDefaultEndTime(start);
    endTimeDirty.value = false;
};

// Global ticking ref to refresh countdowns in real-time
const nowTick = ref(Date.now());
let tickIntervalId = null;

// Auto-release timers per space id
const releaseTimers = new Map();

const scheduleReleaseForSpace = (space) => {
    // Clear any existing timer for this space
    if (releaseTimers.has(space.id)) {
        clearTimeout(releaseTimers.get(space.id));
        releaseTimers.delete(space.id);
    }
    if (space.status !== 'occupied' || !space.occupied_until) return;
    const end = new Date(space.occupied_until).getTime();
    const now = Date.now();
    const delay = end - now;
    const fireIn = Math.max(0, delay);
    // Schedule a one-time release call at end time
    const timerId = setTimeout(() => {
        // Double-check condition before releasing
        if (space.status === 'occupied' && new Date(space.occupied_until).getTime() <= Date.now()) {
            router.patch(route('space-management.release-space', space.id), {}, { preserveState: false });
        }
        releaseTimers.delete(space.id);
    }, fireIn);
    releaseTimers.set(space.id, timerId);
};

const scheduleAllReleaseTimers = () => {
    // Clear all existing timers
    for (const [, id] of releaseTimers) clearTimeout(id);
    releaseTimers.clear();
    // Schedule for all spaces
    if (!props.spaceTypes) return;
    props.spaceTypes.forEach(st => {
        (st.spaces || []).forEach(scheduleReleaseForSpace);
    });
};

onMounted(() => {
    // Tick every 1 second for real-time countdown/countup updates
    tickIntervalId = setInterval(() => { nowTick.value = Date.now(); }, 1000);
    scheduleAllReleaseTimers();
    document.addEventListener('click', onClickOutsideCustomerDropdown);
});

onBeforeUnmount(() => {
    if (tickIntervalId) clearInterval(tickIntervalId);
    for (const [, id] of releaseTimers) clearTimeout(id);
    releaseTimers.clear();
    if (toastTimerId) clearTimeout(toastTimerId);
    stopStartTimeSync();
    document.removeEventListener('click', onClickOutsideCustomerDropdown);
});

// Reschedule timers when spaces update
watch(() => props.spaceTypes, () => {
    scheduleAllReleaseTimers();
}, { deep: true });

watch(showCustomerModal, (visible) => {
    if (visible) {
        customerSearch.value = '';
        customerInputDirty.value = false;
        showCustomerDropdown.value = false;
        highlightedCustomerIndex.value = 0;
        updateStartTimeToNow();
        startStartTimeSync();
    } else {
        stopStartTimeSync();
        startTimeDirty.value = false;
        showCustomerDropdown.value = false;
    }
});

const displayName = (c) => {
    return c?.name || c?.company_name || c?.contact_person || c?.email || `Customer #${c?.id}`;
};

const selectedCustomer = computed(() => {
    return props.customers?.find(c => c.id === assignment.value.customer_id) || null;
});

const activeCustomerQuery = computed(() => {
    if (!customerInputDirty.value && assignment.value.customer_id) {
        return '';
    }
    return customerSearch.value;
});

const filteredCustomers = computed(() => {
    const allCustomers = props.customers || [];
    const query = activeCustomerQuery.value.toLowerCase().trim();
    if (!query) return allCustomers;

    const terms = query.split(/\s+/).filter(Boolean);
    if (!terms.length) return allCustomers;

    return allCustomers.filter((customer) => {
        const searchableValues = [
            customer.id,
            customer.name,
            customer.company_name,
            customer.contact_person,
            customer.email,
            customer.phone,
            customer.phone ? String(customer.phone).replace(/[^0-9+]/g, '') : null,
        ]
            .filter(Boolean)
            .map((value) => String(value).toLowerCase());

        if (!searchableValues.length) return false;

        return terms.every((term) =>
            searchableValues.some((value) => value.includes(term))
        );
    });
});

watch(filteredCustomers, (list) => {
    if (!list.length) {
        highlightedCustomerIndex.value = -1;
        return;
    }
    if (highlightedCustomerIndex.value < 0 || highlightedCustomerIndex.value >= list.length) {
        highlightedCustomerIndex.value = 0;
    }
});

watch(() => assignment.value.customer_id, (id) => {
    if (!id) {
        if (!customerInputDirty.value) {
            customerSearch.value = '';
        }
        return;
    }
    const customer = props.customers?.find((c) => c.id === id);
    if (customer) {
        customerSearch.value = displayName(customer);
        customerInputDirty.value = false;
    }
});

watch(() => props.customers, () => {
    if (!assignment.value.customer_id || customerInputDirty.value) return;
    const customer = props.customers?.find((c) => c.id === assignment.value.customer_id);
    if (customer) {
        customerSearch.value = displayName(customer);
    }
});

const updateEndTimeFromStart = () => {
    if (endTimeDirty.value) return;
    if (!assignment.value.start_time) {
        assignment.value.occupied_until = '';
        return;
    }
    const candidate = computeDefaultEndTime(assignment.value.start_time);
    assignment.value.occupied_until = candidate;
};

watch(() => assignment.value.start_time, () => {
    updateEndTimeFromStart();
});

const openCustomerDropdown = () => {
    showCustomerDropdown.value = true;
    if (filteredCustomers.value.length) {
        highlightedCustomerIndex.value = Math.max(0, Math.min(highlightedCustomerIndex.value, filteredCustomers.value.length - 1));
    } else {
        highlightedCustomerIndex.value = -1;
    }
};

const closeCustomerDropdown = () => {
    showCustomerDropdown.value = false;
    if (filteredCustomers.value.length) {
        highlightedCustomerIndex.value = Math.max(0, Math.min(highlightedCustomerIndex.value, filteredCustomers.value.length - 1));
    } else {
        highlightedCustomerIndex.value = -1;
    }
};

const onCustomerInput = (event) => {
    const value = event?.target?.value ?? '';
    customerSearch.value = value;
    customerInputDirty.value = true;
    assignment.value.customer_id = null;
    openCustomerDropdown();
};

const selectCustomer = (customer) => {
    if (!customer) return;
    assignment.value.customer_id = customer.id;
    customerSearch.value = displayName(customer);
    customerInputDirty.value = false;
    closeCustomerDropdown();
};

const highlightNextCustomer = () => {
    if (!filteredCustomers.value.length) return;
    if (!showCustomerDropdown.value) {
        openCustomerDropdown();
    }
    if (highlightedCustomerIndex.value < 0) {
        highlightedCustomerIndex.value = 0;
        return;
    }
    highlightedCustomerIndex.value = (highlightedCustomerIndex.value + 1) % filteredCustomers.value.length;
};

const highlightPrevCustomer = () => {
    if (!filteredCustomers.value.length) return;
    if (!showCustomerDropdown.value) {
        openCustomerDropdown();
    }
    if (highlightedCustomerIndex.value < 0) {
        highlightedCustomerIndex.value = filteredCustomers.value.length - 1;
        return;
    }
    highlightedCustomerIndex.value = (highlightedCustomerIndex.value - 1 + filteredCustomers.value.length) % filteredCustomers.value.length;
};

const selectHighlightedCustomer = () => {
    if (highlightedCustomerIndex.value < 0) return;
    const customer = filteredCustomers.value[highlightedCustomerIndex.value];
    if (customer) {
        selectCustomer(customer);
    }
};

const clearCustomerSelection = () => {
    assignment.value.customer_id = null;
    customerSearch.value = '';
    customerInputDirty.value = true;
    openCustomerDropdown();
};

const onClickOutsideCustomerDropdown = (event) => {
    if (!customerDropdownRef.value) return;
    if (customerDropdownRef.value.contains(event.target)) return;
    showCustomerDropdown.value = false;
};

const confirmAssign = () => {
    if (!assignment.value.customer_id) return;

    if (isOpenTimeMode.value) {
        // Start open time session and reload spaceTypes
        openTimeSubmitting.value = true;
        router.post(
            route('space-management.start-open-time', selectedSpaceForAssignment.value),
            { customer_id: assignment.value.customer_id },
            {
                preserveState: false,
                onSuccess: () => {
                    showToast('Open time started.', 'success');
                    showCustomerModal.value = false;
                    resetAssignment();
                    router.get(route('space-management.index'), {}, { preserveState: false, only: ['spaceTypes'] });
                },
                onError: (errors) => {
                    const msg = (errors && (errors.customer_id || errors.error)) || 'Failed to start open time.';
                    showToast(String(msg), 'error', 5000);
                },
                onFinish: () => {
                    openTimeSubmitting.value = false;
                    assigningSpace.value = null;
                    selectedSpaceForAssignment.value = null;
                },
            }
        );
        return;
    }
    // Regular assignment
    assignToCustomer(assignment.value.customer_id);
};

const resetAssignment = () => {
    assignment.value = getDefaultAssignment();
    startTimeDirty.value = false;
    endTimeDirty.value = false;
    isOpenTimeMode.value = false; // Reset open time mode
};

const startStartTimeSync = () => {
    if (startTimeSyncId) clearInterval(startTimeSyncId);
    startTimeSyncId = setInterval(() => {
        if (!showCustomerModal.value || startTimeDirty.value) return;
        const latest = nowLocalDateTime(); // already PH localized
        if (assignment.value.start_time !== latest) {
            assignment.value.start_time = latest;
        } else {
            updateEndTimeFromStart();
        }
    }, 5000);
};

const stopStartTimeSync = () => {
    if (!startTimeSyncId) return;
    clearInterval(startTimeSyncId);
    startTimeSyncId = null;
};

const updatePricing = (spaceTypeId, newPrice) => {
    updatingPricing.value = spaceTypeId;
    router.patch(route('space-management.update-pricing', spaceTypeId), {
        default_price: newPrice
    }, {
        onFinish: () => { updatingPricing.value = null; }
    });
};

const minDateTimeLocal = () => nowLocalDateTime();

const startOpenTime = (space) => {
    selectedSpaceForAssignment.value = space.id;
    resetAssignment();
    isOpenTimeMode.value = true; // Mark this as open time mode AFTER reset
    if (hasCustomers.value) {
        showCustomerModal.value = true;
    } else {
        showCreateCustomerForm.value = true;
    }
};

const endOpenTime = (space) => {
    if (confirm('End open time session for this space? The total cost will be calculated.')) {
        router.post(route('space-management.end-open-time', space.id), {}, {
            preserveState: false,
            onSuccess: () => {
                showToast('Open time ended and transaction saved.', 'success');
                router.get(route('space-management.index'), {}, { preserveState: false, only: ['spaceTypes'] });
            },
            onError: () => {
                showToast('Failed to end open time.', 'error');
            }
        });
    }
};

const assignSpace = (spaceId) => {
    selectedSpaceForAssignment.value = spaceId;
    isOpenTimeMode.value = false; // Not open time mode
    resetAssignment();
    if (hasCustomers.value) {
        showCustomerModal.value = true;
    } else {
        showCreateCustomerForm.value = true;
    }
};

const assignToCustomer = (customerId) => {
    assigningSpace.value = selectedSpaceForAssignment.value;
    
    // If in open time mode, use different route without time validation
    if (isOpenTimeMode.value) {
        const payload = {
            customer_id: customerId,
        };
        
        router.post(route('space-management.start-open-time', selectedSpaceForAssignment.value), payload, {
            preserveState: false,
            onFinish: () => {
                assigningSpace.value = null;
                selectedSpaceForAssignment.value = null;
                showCustomerModal.value = false;
                isOpenTimeMode.value = false;
                resetAssignment();
            }
        });
        return;
    }
    
    // Normal assignment flow with time validation
    const payload = {
        customer_id: customerId,
    };
    let startString = assignment.value.start_time;
    if (!startString) {
        updateStartTimeToNow();
        startString = assignment.value.start_time;
    }
    const startDate = new Date(startString);
    const now = getRoundedNow();
    if (startDate < now) {
        showToast('Start time must be now or in the future.', 'error');
        updateStartTimeToNow();
        assigningSpace.value = null;
        return;
    }
    payload.start_time = startString;
    if (assignment.value.occupied_until) {
        const occupiedUntilDate = new Date(assignment.value.occupied_until);
        if (occupiedUntilDate < now) {
            alert('End time must be in the future.');
            assigningSpace.value = null;
            return;
        }
        if (occupiedUntilDate <= startDate) {
            alert('End time must be after the start time.');
            assigningSpace.value = null;
            return;
        }
        payload.occupied_until = assignment.value.occupied_until;
    }
    if (assignment.value.custom_hourly_rate) payload.custom_hourly_rate = assignment.value.custom_hourly_rate;

    router.patch(route('space-management.assign-space', selectedSpaceForAssignment.value), payload, {
        preserveState: false,
        onFinish: () => {
            assigningSpace.value = null;
            selectedSpaceForAssignment.value = null;
            showCustomerModal.value = false;
            resetAssignment();
        }
    });
};

const onStartTimeInput = (event) => {
    if (!event?.target?.value) {
        updateStartTimeToNow();
        return;
    }
    startTimeDirty.value = true;
};

const onEndTimeInput = () => {
    endTimeDirty.value = true;
};

const switchToCreateForm = () => {
    showCustomerModal.value = false;
    showCreateCustomerForm.value = true;
};

const onCustomerCreated = () => {
    showCreateCustomerForm.value = false;
    // Reload customers and re-open the assignment modal
    router.reload({ 
        only: ['customers'],
        onSuccess: () => {
            showCustomerModal.value = true;
        }
    });
};

const submitNewCustomer = () => {
    newCustomerForm.post(route('customers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newCustomerForm.reset();
            showCreateCustomerForm.value = false;
            // Reload customers and re-open the assignment modal
            router.reload({ 
                only: ['customers'],
                onSuccess: () => {
                    showCustomerModal.value = true;
                }
            });
        },
    });
};

const releaseSpace = (spaceId) => {
    if (confirm('Are you sure you want to release this space?')) {
        router.patch(route('space-management.release-space', spaceId), {}, {
            preserveState: false, // Reload page to update calendar
        });
    }
};

const openPaymentForSpace = (space) => {
    // Prefer the active reservation attached by the backend
    const active = space.active_reservation || null;
    if (active) {
        selectedPaymentSpace.value = {
            id: active.id, // real reservation id
            total_cost: active.total_cost ?? active.cost ?? 0,
            cost: active.cost ?? active.total_cost ?? 0,
            amount_paid: active.amount_paid ?? 0,
            amount_remaining: active.amount_remaining ?? Math.max((active.total_cost ?? 0) - (active.amount_paid ?? 0), 0),
            status: active.status,
            space_name: space.name,
            space_type: props.spaceTypes.find(st => st.spaces.some(s => s.id === space.id))?.name,
        };
        showPaymentModal.value = true;
        return;
    }

    // Fallback: estimate a temporary reservation payload
    const est = {
        id: space.id, // not ideal, but allows modal to open
        space_name: space.name,
        space_type: props.spaceTypes.find(st => st.spaces.some(s => s.id === space.id))?.name,
        total_cost: 0,
        cost: 0,
        amount_paid: 0,
        amount_remaining: 0,
    };
    if (space.occupied_from) {
        const hours = Math.max(1, Math.floor((Date.now() - new Date(space.occupied_from).getTime()) / (1000 * 60 * 60)));
        const rate = space.hourly_rate || props.spaceTypes.find(st => st.spaces.some(s => s.id === space.id))?.hourly_rate || 0;
        est.total_cost = hours * rate;
        est.cost = est.total_cost;
        est.amount_remaining = est.total_cost;
    }
    selectedPaymentSpace.value = est;
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    selectedPaymentSpace.value = null;
};

const getStatusColor = (status) => {
    if (status === 'available') return 'bg-green-100 text-green-800';
    if (status === 'scheduled') return 'bg-blue-100 text-blue-800';
    if (status === 'occupied') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
};

const getTotalSpaces = (spaceType) => {
    return spaceType.spaces.length;
};

const getOccupiedSpaces = (spaceType) => {
    return spaceType.spaces.filter(space => space.status === 'occupied').length;
};

const getAvailableSpaces = (spaceType) => {
    return spaceType.spaces.filter(space => (space.dynamic_status || space.status) === 'available').length;
};

const getOccupancyFraction = (spaceType) => {
    const occupied = getOccupiedSpaces(spaceType);
    const total = getTotalSpaces(spaceType);
    return `${occupied}/${total}`;
};

const getNextAvailableTime = (spaceType) => {
    const occupiedSpaces = spaceType.spaces
        .filter(space => space.status === 'occupied' && space.occupied_until)
        .sort((a, b) => new Date(a.occupied_until) - new Date(b.occupied_until));
    
    if (occupiedSpaces.length === 0) {
        return null;
    }
    
    const nextFree = new Date(occupiedSpaces[0].occupied_until);
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

// Open modal populated with current values
const openPricingModal = (spaceType) => {
    showPricingModalId.value = spaceType.id;
    pricingForm.value = {
        hourly_rate: spaceType.hourly_rate ?? spaceType.default_price ?? '',
        pricing_type: spaceType.pricing_type ?? 'per_person',
        default_discount_hours: spaceType.default_discount_hours ?? '',
        default_discount_percentage: spaceType.default_discount_percentage ?? '',
    };
    pricingErrors.value = { hourly_rate: '', pricing_type: '', default_discount_hours: '', default_discount_percentage: '' };
};

const closePricingModal = () => {
    showPricingModalId.value = null;
};

const validatePricing = () => {
    pricingErrors.value = { hourly_rate: '', pricing_type: '', default_discount_hours: '', default_discount_percentage: '' };
    const v = pricingForm.value || {};
    let ok = true;
    const rate = v.hourly_rate;
    const hours = v.default_discount_hours;
    const percent = v.default_discount_percentage;

    if (rate === '' || isNaN(rate) || Number(rate) < 0) {
        pricingErrors.value.hourly_rate = 'Rate must be a number greater than or equal to 0.';
        ok = false;
    }

    // Discount fields are optional, but when provided must be valid and both should be set
    const hasHours = hours !== '' && hours !== null && hours !== undefined;
    const hasPercent = percent !== '' && percent !== null && percent !== undefined;

    if (hasHours) {
        if (isNaN(hours) || Number(hours) < 1) {
            pricingErrors.value.default_discount_hours = 'Discount starts after at least 1 hour.';
            ok = false;
        }
    }
    if (hasPercent) {
        if (isNaN(percent) || Number(percent) < 0 || Number(percent) > 100) {
            pricingErrors.value.default_discount_percentage = 'Discount percent must be between 0 and 100.';
            ok = false;
        }
    }
    if (hasHours !== hasPercent) {
        if (!hasHours) pricingErrors.value.default_discount_hours = 'Provide hours when setting a discount percent.';
        if (!hasPercent) pricingErrors.value.default_discount_percentage = 'Provide a percent when setting discount hours.';
        ok = false;
    }

    return ok;
};

const savePricingModal = () => {
    const id = showPricingModalId.value;
    if (!id) return;
    if (!validatePricing()) return;
    const val = pricingForm.value || {};
    updatingPricing.value = id;
    const payload = {
        hourly_rate: Number(val.hourly_rate),
        pricing_type: val.pricing_type || 'per_person',
        default_discount_hours: val.default_discount_hours === '' ? null : Number(val.default_discount_hours),
        default_discount_percentage: val.default_discount_percentage === '' ? null : Number(val.default_discount_percentage),
    };
    router.patch(route('space-management.update-pricing', id), payload, {
        preserveScroll: true,
        onError: (errors) => {
            // Map server-side validation errors (422) to inline errors
            pricingErrors.value.hourly_rate = errors?.hourly_rate ?? pricingErrors.value.hourly_rate;
            pricingErrors.value.pricing_type = errors?.pricing_type ?? pricingErrors.value.pricing_type;
            pricingErrors.value.default_discount_hours = errors?.default_discount_hours ?? pricingErrors.value.default_discount_hours;
            pricingErrors.value.default_discount_percentage = errors?.default_discount_percentage ?? pricingErrors.value.default_discount_percentage;
            showToast('Failed to save pricing. Please check the form and try again.', 'error');
            updatingPricing.value = null;
        },
        onSuccess: () => {
            updatingPricing.value = null;
            closePricingModal();
            showToast('Pricing updated successfully.', 'success');
            // Refresh only the spaceTypes list so new values are reflected
            router.reload({ only: ['spaceTypes'] });
        },
    });
};

const getTimeUntilFree = (space) => {
    if (!space.occupied_until) return null;
    // Reference nowTick to make this reactive over time
    void nowTick.value;
    const until = new Date(space.occupied_until);
    const now = new Date(nowTick.value);
    
    if (until <= now) return 'Available now';
    
    const diff = until - now;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    if (hours > 0) {
        return `${hours}h ${minutes}m ${seconds}s`;
    } else if (minutes > 0) {
        return `${minutes}m ${seconds}s`;
    } else {
        return `${seconds}s`;
    }
};

// Format time for display with real-time updates
const formatTimeDisplay = (space) => {
    // Reference nowTick to make this reactive
    void nowTick.value;
    const now = new Date(nowTick.value);
    
    // Use active_reservation data if available, fallback to space fields
    const startTime = space.active_reservation?.start_time || space.occupied_from;
    const endTime = space.active_reservation?.end_time || space.occupied_until;
    const status = space.dynamic_status || space.status;
    
    // Handle open time (no end time)
    if (status === 'occupied' && !endTime && startTime) {
        const start = new Date(startTime);
        const elapsed = now - start;
        const hours = Math.floor(elapsed / (1000 * 60 * 60));
        const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
        
        if (hours > 0) {
            return `⏱️ ${hours}h ${minutes}m ${seconds}s elapsed`;
        } else if (minutes > 0) {
            return `⏱️ ${minutes}m ${seconds}s elapsed`;
        } else {
            return `⏱️ ${seconds}s elapsed`;
        }
    }
    
    // Handle scheduled reservation (hasn't started yet)
    if (status === 'occupied' && startTime) {
        const start = new Date(startTime);
        if (start > now) {
            const diff = start - now;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            if (hours > 0) {
                return `🕐 Starts in ${hours}h ${minutes}m ${seconds}s`;
            } else if (minutes > 0) {
                return `🕐 Starts in ${minutes}m ${seconds}s`;
            } else {
                return `🕐 Starts in ${seconds}s`;
            }
        }
    }
    
    // Handle countdown to end time
    if (status === 'occupied' && endTime) {
        const until = new Date(endTime);
        if (until > now) {
            const diff = until - now;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            if (hours > 0) {
                return `⏳ ${hours}h ${minutes}m ${seconds}s remaining`;
            } else if (minutes > 0) {
                return `⏳ ${minutes}m ${seconds}s remaining`;
            } else {
                return `⏳ ${seconds}s remaining`;
            }
        } else {
            return '✅ Time expired - Ready to release';
        }
    }
    
    return null;
};

// Format time for reservation (similar to space but uses reservation fields)
const formatReservationTimeDisplay = (reservation) => {
    // Reference nowTick to make this reactive
    void nowTick.value;
    const now = new Date(nowTick.value);
    
    // Handle open time (no end time)
    if (!reservation.end_time && reservation.start_time) {
        const start = new Date(reservation.start_time);
        const elapsed = now - start;
        const hours = Math.floor(elapsed / (1000 * 60 * 60));
        const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
        
        if (hours > 0) {
            return `⏱️ ${hours}h ${minutes}m ${seconds}s elapsed`;
        } else if (minutes > 0) {
            return `⏱️ ${minutes}m ${seconds}s elapsed`;
        } else {
            return `⏱️ ${seconds}s elapsed`;
        }
    }
    
    // Handle scheduled reservation (hasn't started yet)
    if (reservation.start_time) {
        const start = new Date(reservation.start_time);
        if (start > now) {
            const diff = start - now;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            if (hours > 0) {
                return `🕐 Starts in ${hours}h ${minutes}m ${seconds}s`;
            } else if (minutes > 0) {
                return `🕐 Starts in ${minutes}m ${seconds}s`;
            } else {
                return `🕐 Starts in ${seconds}s`;
            }
        }
    }
    
    // Handle countdown to end time
    if (reservation.end_time) {
        const until = new Date(reservation.end_time);
        if (until > now) {
            const diff = until - now;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            if (hours > 0) {
                return `⏳ ${hours}h ${minutes}m ${seconds}s remaining`;
            } else if (minutes > 0) {
                return `⏳ ${minutes}m ${seconds}s remaining`;
            } else {
                return `⏳ ${seconds}s remaining`;
            }
        } else {
            return '✅ Time expired';
        }
    }
    
    return null;
};

const formatLocalDate = (dateString) => formatDatePH(dateString) || '';

const formatLocalDateTime = (dateString) => formatDateTimePH(dateString) || '';

// Removed inline edit state in favor of modal

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

watch(() => props.customers, () => {
    // no-op: selectedCustomer is computed; simply ensures reactivity chain runs
});

const refreshCustomers = () => {
    router.reload({ only: ['customers'] });
};

// --- Create/extend Space Type (batch sub spaces) ---
const showCreateType = ref(false);
const createTypeForm = useForm({
    name: '',
    description: '',
    hourly_rate: '',
    pricing_type: 'per_person',
    default_discount_hours: '',
    default_discount_percentage: '',
    initial_slots: 1,
});
const submitCreateType = () => {
    createTypeForm.post(route('space-management.store-space-type'), {
        preserveScroll: true,
        onSuccess: () => {
            createTypeForm.reset();
            createTypeForm.initial_slots = 1;
            createTypeForm.pricing_type = 'per_person';
            showCreateType.value = false;
        }
    });
};

// --- Add a single space under a space type ---
const showAddSpace = ref({}); // keyed by spaceTypeId -> bool
const addSpaceValues = ref({}); // keyed by spaceTypeId -> { name, hourly_rate, discount_hours, discount_percentage }
const toggleAddSpace = (spaceTypeId) => {
    showAddSpace.value[spaceTypeId] = !showAddSpace.value[spaceTypeId];
    if (showAddSpace.value[spaceTypeId] && !addSpaceValues.value[spaceTypeId]) {
        addSpaceValues.value[spaceTypeId] = { name: '', hourly_rate: '', discount_hours: '', discount_percentage: '' };
    }
};
const submittingAddSpace = ref({}); // keyed by spaceTypeId -> bool
const submitAddSpace = (spaceTypeId) => {
    const payload = { ...addSpaceValues.value[spaceTypeId] };
    submittingAddSpace.value[spaceTypeId] = true;
    router.post(route('space-management.store-space', spaceTypeId), payload, {
        preserveScroll: true,
        onFinish: () => { submittingAddSpace.value[spaceTypeId] = false; },
        onSuccess: () => {
            addSpaceValues.value[spaceTypeId] = { name: '', hourly_rate: '', discount_hours: '', discount_percentage: '' };
            showAddSpace.value[spaceTypeId] = false;
        }
    });
};

// --- Delete single space and bulk remove subspaces ---
const deletingSpace = ref({}); // keyed by spaceId -> bool
const deleteSpace = (space) => {
    if (space.status !== 'available') {
        alert('Release the space before deleting.');
        return;
    }
    if (!confirm(`Permanently delete ${space.name}? This cannot be undone.`)) return;
    deletingSpace.value[space.id] = true;
    router.delete(route('space-management.destroy-space', space.id), {
        preserveScroll: true,
        onFinish: () => { deletingSpace.value[space.id] = false; }
    });
};

const bulkRemoving = ref({}); // keyed by spaceTypeId -> bool
const bulkRemoveCount = ref({}); // keyed by spaceTypeId -> number
const bulkRemove = (spaceType) => {
    const count = parseInt(bulkRemoveCount.value[spaceType.id] || 1, 10);
    if (!count || count < 1) return;
    if (!confirm(`Remove ${count} available space(s) from ${spaceType.name}? This cannot be undone.`)) return;
    bulkRemoving.value[spaceType.id] = true;
    router.delete(route('space-management.bulk-destroy-spaces', spaceType.id), {
        data: { count },
        preserveScroll: true,
        onFinish: () => { bulkRemoving.value[spaceType.id] = false; }
    });
};

// --- Delete entire space type ---
const deletingType = ref({}); // keyed by spaceTypeId -> bool
const deleteSpaceType = (spaceType) => {
    if (!confirm(`Delete the entire space type "${spaceType.name}"? All its spaces will be removed. This cannot be undone.`)) return;
    deletingType.value[spaceType.id] = true;
    router.delete(route('space-management.destroy-space-type', spaceType.id), {
        preserveScroll: true,
        onFinish: () => { deletingType.value[spaceType.id] = false; }
    });
};

// Utility function to find a space by id
const findSpaceById = (spaceId) => {
    for (const spaceType of props.spaceTypes) {
        const space = spaceType.spaces.find(s => s.id === spaceId);
        if (space) return space;
    }
    return null;
};
</script>

<template>
    <Head title="Space Management" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header matching Dashboard style -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Space Management</h2>
                        <p class="text-sm text-gray-600 mt-1">Configure spaces, types, and pricing structures</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <Link
                            :href="route('dashboard')"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-sm"
                        >
                            ← Back to Dashboard
                        </Link>
                    </div>
                </div>

                <!-- Create/Extend Space Type -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Create or Extend Space Type</h3>
                            <button
                                @click="showCreateType = !showCreateType"
                                class="text-sm px-3 py-1 rounded border border-gray-300 hover:bg-gray-50"
                            >
                                {{ showCreateType ? 'Hide' : 'Open' }}
                            </button>
                        </div>
                        <div v-if="showCreateType" class="mt-4">
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700">Type Name</label>
                                    <input v-model="createTypeForm.name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="e.g., Mini Space, Conference Room" />
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-xs font-medium text-gray-700">Rate (₱/h)</label>
                                    <input v-model.number="createTypeForm.hourly_rate" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-xs font-medium text-gray-700">Pricing Type</label>
                                    <select v-model="createTypeForm.pricing_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="per_person">Per Person</option>
                                        <option value="per_reservation">Per Reservation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700">Disc. After (h)</label>
                                    <input v-model.number="createTypeForm.default_discount_hours" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700">Discount (%)</label>
                                    <input v-model.number="createTypeForm.default_discount_percentage" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700">Initial Slots</label>
                                    <input v-model.number="createTypeForm.initial_slots" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <div class="md:col-span-6">
                                    <label class="block text-xs font-medium text-gray-700">Description (optional)</label>
                                    <input v-model="createTypeForm.description" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Short description" />
                                </div>
                                <div class="md:col-span-6 flex justify-end">
                                    <button @click="submitCreateType" :disabled="createTypeForm.processing" class="bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 text-white text-sm py-2 px-4 rounded">
                                        {{ createTypeForm.processing ? 'Saving…' : 'Save Type & Create Slots' }}
                                    </button>
                                </div>
                            </div>
                            <p class="mt-2 text-xxs text-gray-500">Tip: If a type with the same name exists, this will add the specified number of slots to it.</p>
                            <div v-if="createTypeForm.errors && Object.keys(createTypeForm.errors).length" class="mt-3 text-xs text-red-600">
                                <div v-for="(msg, key) in createTypeForm.errors" :key="key">• {{ msg }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Space Slots Management (aligned with statistics cards) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Space Slots</h3>
                        
                        <!-- Space Type Slots Grid - Same styling as Dashboard -->
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                            <div 
                                v-for="spaceType in spaceTypes" 
                                :key="spaceType.id"
                                class="rounded-lg p-4 hover:shadow-sm transition-shadow"
                                :class="getSlotAvailabilityColor(spaceType)"
                            >
                                <div class="text-center">
                                    <h4 class="text-sm font-medium text-gray-900 mb-2">{{ spaceType.name }}</h4>
                                    <div class="text-2xl font-bold text-gray-900 mb-1">{{ getOccupancyFraction(spaceType) }}</div>
                                    <div class="text-sm text-gray-600 mb-2">slots occupied</div>
                                    <div class="text-sm text-gray-500">₱{{ spaceType.hourly_rate || spaceType.default_price }}/hr</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Space Management -->
                <div class="space-y-8">
                    <div v-for="spaceType in spaceTypes" :key="spaceType.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">                            <div class="flex items-center justify-between gap-4 mb-6 flex-nowrap">
                <h3 class="text-xl font-semibold text-gray-900 whitespace-nowrap truncate flex-1 min-w-0">{{ spaceType.name }}</h3>
                                
                                <!-- Actions -->
                                <div class="ml-auto flex flex-row items-center gap-3 flex-none">
                                    <button @click="openDetailsModal(spaceType)" class="text-xs px-3 py-1.5 rounded bg-indigo-600 text-white hover:bg-indigo-700">Edit Details</button>
                                    <button @click="openPricingModal(spaceType)" class="text-xs px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700">Edit Rate & Discount</button>
                                    <button @click="toggleAddSpace(spaceType.id)" class="text-xs px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 whitespace-nowrap">{{ showAddSpace[spaceType.id] ? 'Cancel' : 'Add Space' }}</button>
                                    <div class="flex items-center gap-2 flex-none">
                                        <label class="text-xs text-gray-600">Remove</label>
                                        <input type="number" min="1" :max="getAvailableSpaces(spaceType)" class="w-16 px-2 py-1 border border-gray-300 rounded text-xs" v-model.number="bulkRemoveCount[spaceType.id]" placeholder="1" />
                                        <button @click="bulkRemove(spaceType)" :disabled="bulkRemoving[spaceType.id] || !getAvailableSpaces(spaceType)" class="text-xs px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50 whitespace-nowrap">{{ bulkRemoving[spaceType.id] ? 'Removing…' : 'Remove Available' }}</button>
                                    </div>
                                    <button @click="deleteSpaceType(spaceType)" :disabled="deletingType[spaceType.id]" class="text-xs px-3 py-1.5 rounded border border-red-300 text-red-700 hover:bg-red-50 whitespace-nowrap">
                                        {{ deletingType[spaceType.id] ? 'Deleting…' : 'Delete Type' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Inline Add Space Form -->
                            <div v-if="showAddSpace[spaceType.id]" class="mb-4 p-3 rounded border border-gray-200 bg-gray-50">
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Name (optional)</label>
                                        <input v-model="addSpaceValues[spaceType.id].name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" :placeholder="spaceType.name + ' ' + (spaceType.spaces.length + 1)" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Rate (₱/h)</label>
                                        <input v-model.number="addSpaceValues[spaceType.id].hourly_rate" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Use default" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Disc. After (h)</label>
                                        <input v-model.number="addSpaceValues[spaceType.id].discount_hours" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Use default" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Discount (%)</label>
                                        <input v-model.number="addSpaceValues[spaceType.id].discount_percentage" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Use default" />
                                    </div>
                                    <div class="flex md:justify-end">
                                        <button @click="submitAddSpace(spaceType.id)" :disabled="submittingAddSpace[spaceType.id]" class="self-end bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 text-white text-sm py-2 px-4 rounded w-full md:w-auto">{{ submittingAddSpace[spaceType.id] ? 'Adding…' : 'Create Space' }}</button>
                                    </div>
                                </div>
                                <p class="mt-2 text-xxs text-gray-500">Leave fields blank to inherit from this type.</p>
                            </div>

                            <!-- Individual Spaces Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <div 
                                    v-for="space in spaceType.spaces" 
                                    :key="space.id"
                                    class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow overflow-hidden cursor-pointer"
                                    @click="onRowClick(space, $event)"
                                ><div class="flex justify-between items-start mb-3">
                                        <h4 class="font-medium text-gray-900 whitespace-nowrap truncate max-w-[60%]">{{ space.name }}</h4>
                                        <div class="text-right">
                                            <span 
                                                :class="getStatusColor(space.dynamic_status || space.status)"
                                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full mb-1"
                                            >
                                                {{ (space.dynamic_status || space.status).toUpperCase() }}
                                            </span>
                                            <div v-if="(space.dynamic_status || space.status) === 'occupied'" class="text-xs font-medium text-blue-600 mt-1">
                                                {{ formatTimeDisplay(space) }}
                                            </div>
                                            <div v-if="(space.dynamic_status || space.status) === 'scheduled'" class="text-xs font-medium text-blue-600 mt-1">
                                                📅 Booked for later
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Scheduled Booking Info -->
                                    <div v-if="(space.dynamic_status || space.status) === 'scheduled' && space.next_reservation" class="mb-3 p-2 bg-blue-50 border border-blue-200 rounded">
                                        <p class="text-xs font-semibold text-blue-800 mb-1">⏰ Next Booking</p>
                                        <p class="text-xs text-blue-700">
                                            <span class="font-medium">{{ space.next_customer_name }}</span>
                                        </p>
                                        <p class="text-xs text-blue-600 mt-1">
                                            Starts in <span class="font-semibold">{{ space.next_booking_in_hours }}</span> hours
                                        </p>
                                        <p class="text-xs text-blue-500">
                                            {{ formatLocalDateTime(space.next_reservation.start_time) }}
                                        </p>
                                        <p v-if="space.future_reservations_count > 1" class="text-xs text-blue-500 mt-1 italic">
                                            +{{ space.future_reservations_count - 1 }} more booking(s) scheduled
                                        </p>
                                    </div>
                                    
                                    <!-- Space Details -->
                                    <div v-if="(space.dynamic_status || space.status) === 'occupied' && (space.active_reservation || space.current_customer)" class="mb-3">
                                        <p class="text-sm text-gray-600">Occupied by:</p>
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ space.active_reservation ? space.active_reservation.customer.company_name || space.active_reservation.customer.name || space.current_customer_name : space.current_customer.company_name }}
                                        </p>
                                        <p class="text-xs text-gray-500 truncate">
                                            {{ space.active_reservation ? (space.active_reservation.customer.contact_person || space.active_reservation.customer.email) : space.current_customer.contact_person }}
                                        </p><div class="flex flex-col gap-1 text-xs text-gray-500 mt-1">
                                            <span v-if="space.active_reservation?.start_time || space.occupied_from" class="truncate">
                                                From: {{ formatLocalDateTime(space.active_reservation?.start_time || space.occupied_from) }}
                                            </span>
                                            <span v-if="space.active_reservation?.end_time || space.occupied_until" class="truncate">
                                                Until: {{ formatLocalDateTime(space.active_reservation?.end_time || space.occupied_until) }}
                                            </span>
                                            <span v-else-if="!(space.active_reservation?.end_time || space.occupied_until)" class="truncate text-green-600 font-medium">
                                                Open Time (No end limit)
                                            </span>
                                        </div>
                                        
                                        <!-- Show next booking after current one -->
                                        <div v-if="space.next_reservation" class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded">
                                            <p class="text-xs font-semibold text-amber-800">⚠️ Next booking after this:</p>
                                            <p class="text-xs text-amber-700">
                                                {{ space.next_customer_name }} at {{ formatLocalDateTime(space.next_reservation.start_time) }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Pricing Information -->
                                    <div v-if="(space.dynamic_status || space.status) === 'available' || (space.dynamic_status || space.status) === 'scheduled'" class="mb-3 p-2 bg-green-50 rounded">
                                        <p class="text-xs text-green-700 font-medium">Available for booking</p>
                                        <p class="text-xs text-green-600">
                                            ₱{{ space.hourly_rate || spaceType.hourly_rate || spaceType.default_price }}/hour
                                        </p>
                                        <p v-if="spaceType.default_discount_hours" class="text-xs text-green-600">
                                            {{ spaceType.default_discount_percentage }}% off after {{ spaceType.default_discount_hours }}h
                                        </p>
                                        
                                        <!-- Show if there are future bookings even when currently available -->
                                        <div v-if="space.next_reservation" class="mt-2 pt-2 border-t border-green-200">
                                            <p class="text-xs font-semibold text-amber-700">⏰ Upcoming booking:</p>
                                            <p class="text-xs text-amber-600">
                                                {{ space.next_customer_name }} in {{ space.next_booking_in_hours }} hours
                                            </p>
                                            <p class="text-xs text-amber-500">
                                                {{ formatLocalDateTime(space.next_reservation.start_time) }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex space-x-2">
                                        <button
                                            v-if="(space.dynamic_status || space.status) === 'available' || (space.dynamic_status || space.status) === 'scheduled'"
                                            @click="assignSpace(space.id)"
                                            class="flex-1 bg-blue-500 hover:bg-blue-600 text-white text-xs py-1 px-2 rounded"
                                            :disabled="assigningSpace === space.id"
                                        >
                                            {{ assigningSpace === space.id ? 'Assigning...' : 'Assign' }}
                                        </button>

                                        <button
                                            v-if="(space.dynamic_status || space.status) === 'available' || (space.dynamic_status || space.status) === 'scheduled'"
                                            @click="startOpenTime(space)"
                                            class="flex-1 bg-green-500 hover:bg-green-600 text-white text-xs py-1 px-2 rounded"
                                        >
                                            Open Time
                                        </button>

                                        <button
                                            v-if="(space.dynamic_status || space.status) === 'occupied'"
                                            @click="openPaymentForSpace(space)"
                                            class="flex-1 bg-green-500 hover:bg-green-600 text-white text-xs py-1 px-2 rounded"
                                        >
                                            Pay
                                        </button>

                                        <button
                                            v-if="(space.dynamic_status || space.status) === 'occupied' && (space.active_reservation?.end_time || space.occupied_until)"
                                            @click="releaseSpace(space.id)"
                                            class="relative flex-1 bg-red-500 hover:bg-red-600 text-white text-xs py-1 px-2 rounded"
                                        >
                                            Release
                                            <span v-if="space.occupied_until" class="absolute -top-2 -right-2 bg-gray-800 text-white text-xxs px-1 rounded-full">
                                                {{ getTimeUntilFree(space) }}
                                            </span>
                                        </button>

                                        <button
                                            v-if="(space.dynamic_status || space.status) === 'occupied' && !(space.active_reservation?.end_time || space.occupied_until)"
                                            @click="endOpenTime(space)"
                                            class="flex-1 bg-orange-500 hover:bg-orange-600 text-white text-xs py-1 px-2 rounded"
                                        >
                                            End Open Time
                                        </button>

                                        <button
                                            v-if="(space.dynamic_status || space.status) === 'available'"
                                            @click="deleteSpace(space)"
                                            class="flex-1 bg-white border border-red-300 text-red-700 hover:bg-red-50 text-xs py-1 px-2 rounded"
                                            :disabled="deletingSpace[space.id]"
                                        >
                                            {{ deletingSpace[space.id] ? 'Deleting…' : 'Delete' }}
                                        </button>
                                    </div>
                                </div>                            </div>

                            <!-- Unassigned Reservations (from public bookings) -->
                            <div v-if="spaceType.unassigned_reservations && spaceType.unassigned_reservations.length > 0" class="mt-6 border-t pt-6">
                                <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    Active Online Bookings (Not Assigned to Physical Space)
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    <div 
                                        v-for="reservation in spaceType.unassigned_reservations" 
                                        :key="reservation.id"
                                        class="border-2 border-orange-300 bg-orange-50 rounded-lg p-4 hover:shadow-md transition-shadow"
                                    >
                                        <div class="flex justify-between items-start mb-3">
                                            <h4 class="font-medium text-gray-900 text-sm">Online Booking #{{ reservation.id }}</h4>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                                ACTIVE
                                            </span>
                                        </div>
                                        
                                        <!-- Time Display -->
                                        <div class="text-xs font-medium text-blue-600 mb-3">
                                            {{ formatReservationTimeDisplay(reservation) }}
                                        </div>

                                        <!-- Customer Details -->
                                        <div class="mb-3">
                                            <p class="text-sm text-gray-600">Customer:</p>
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ reservation.customer?.name || 'N/A' }}</p>
                                            <p v-if="reservation.customer?.email" class="text-xs text-gray-500 truncate">{{ reservation.customer.email }}</p>
                                        </div>

                                        <!-- Reservation Details -->
                                        <div class="flex flex-col gap-1 text-xs text-gray-600 mb-3 bg-white p-2 rounded">
                                            <span class="truncate">
                                                <strong>From:</strong> {{ formatLocalDateTime(reservation.start_time) }}
                                            </span>
                                            <span v-if="reservation.end_time" class="truncate">
                                                <strong>Until:</strong> {{ formatLocalDateTime(reservation.end_time) }}
                                            </span>
                                            <span v-else class="truncate text-green-600 font-medium">
                                                Open Time (No end limit)
                                            </span>
                                            <span v-if="reservation.pax" class="truncate">
                                                <strong>Pax:</strong> {{ reservation.pax }} person{{ reservation.pax > 1 ? 's' : '' }}
                                            </span>
                                            <span v-if="reservation.hours" class="truncate">
                                                <strong>Duration:</strong> {{ reservation.hours }} hour{{ reservation.hours > 1 ? 's' : '' }}
                                            </span>
                                        </div>

                                        <!-- Note -->
                                        <p class="text-xxs text-orange-700 bg-orange-100 p-2 rounded">
                                            💡 This booking is active but hasn't been assigned to a specific physical space yet.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details Modal -->
                <div v-if="showDetailsModalId !== null" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeDetailsModal"></div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Space Type Details</h3>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Name</label>
                                                <input 
                                                    v-model="detailsForm.name" 
                                                    type="text"
                                                    class="mt-1 block w-full rounded-md shadow-sm text-sm border"
                                                    :class="detailsErrors.name ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300'"
                                                />
                                                <p v-if="detailsErrors.name" class="mt-1 text-xxs text-red-600">{{ detailsErrors.name }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Description</label>
                                                <textarea 
                                                    v-model="detailsForm.description" 
                                                    rows="3"
                                                    class="mt-1 block w-full rounded-md shadow-sm text-sm border"
                                                    :class="detailsErrors.description ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300'"
                                                />
                                                <p v-if="detailsErrors.description" class="mt-1 text-xxs text-red-600">{{ detailsErrors.description }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Photo (JPG, PNG, WEBP, max 5MB)</label>
                                                <input 
                                                    type="file" 
                                                    accept="image/jpeg,image/png,image/webp"
                                                    @change="onPhotoSelected"
                                                    class="mt-1 block w-full text-sm"
                                                />
                                                <div v-if="detailsPreviewUrl" class="mt-2 flex items-start gap-3">
                                                    <img :src="detailsPreviewUrl" alt="Preview" class="w-24 h-24 object-cover rounded border" />
                                                    <div class="text-xs text-gray-600 space-y-1">
                                                        <p>Preview</p>
                                                        <button type="button" class="text-red-600 hover:text-red-700"
                                                            @click="() => { detailsForm.value.photo = null; detailsForm.value.remove_photo = true; detailsPreviewUrl.value = ''; }">
                                                            Remove photo
                                                        </button>
                                                    </div>
                                                </div>
                                                <p v-if="detailsErrors.photo" class="mt-1 text-xxs text-red-600">{{ detailsErrors.photo }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button @click="saveDetailsModal" :disabled="savingDetails" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">
                                    {{ savingDetails ? 'Saving…' : 'Save' }}
                                </button>
                                <button @click="closeDetailsModal" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Assignment Modal -->
                <div v-if="showCustomerModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showCustomerModal = false, resetAssignment()"></div>
                        
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                        <div class="flex items-center justify-between mb-2">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                                {{ isOpenTimeMode ? 'Start Open Time Session' : 'Assign Customer to Space' }}
                                            </h3>
                                            <button @click="refreshCustomers" class="text-xs text-blue-600 hover:text-blue-700">Refresh list</button>
                                        </div>

                                        <!-- Open Time Info -->
                                        <div v-if="isOpenTimeMode" class="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-md">
                                            <p class="text-sm text-orange-800 font-medium">⏱️ Open Time Session</p>
                                            <p class="text-xs text-orange-600 mt-1">
                                                No end time required. The session will start now and cost will be calculated when you end it.
                                            </p>
                                        </div>

                                        <!-- Scheduling & Rate (hidden for open time) -->
                                        <div v-if="!isOpenTimeMode" class="mb-4 grid grid-cols-1 gap-3">
                                            <div class="flex items-start gap-2">
                                                <div class="flex-1">
                                                    <label class="block text-xs font-medium text-gray-700">Start</label>
                                                    <input type="datetime-local" :min="minDateTimeLocal()" v-model="assignment.start_time" @input="onStartTimeInput" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                                </div>
                                                <div class="flex-1">
                                                    <label class="block text-xs font-medium text-gray-700">End</label>
                                                    <input type="datetime-local" :min="assignment.start_time || minDateTimeLocal()" v-model="assignment.occupied_until" @input="onEndTimeInput" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Custom Hourly Rate (optional)</label>
                                                <div class="relative mt-1">
                                                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-500">₱</span>
                                                    <input type="number" step="0.01" min="0" v-model="assignment.custom_hourly_rate" class="pl-6 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Use default if empty" />
                                                </div>
                                                <p class="mt-1 text-xxs text-gray-500 flex items-center gap-1">
                                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
                                                    Leave blank to use the space or space-type default rate. Discounts apply after configured hours.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Customer selection -->
                                        <div class="space-y-2">
                                            <label class="block text-xs font-medium text-gray-700">Select Customer</label>
                                            <div ref="customerDropdownRef" class="relative">
                                                <input
                                                    type="text"
                                                    :value="customerSearch"
                                                    placeholder="Search name, company, email, phone"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm text-sm pr-9"
                                                    @focus="openCustomerDropdown"
                                                    @input="onCustomerInput"
                                                    @keydown.down.prevent="highlightNextCustomer"
                                                    @keydown.up.prevent="highlightPrevCustomer"
                                                    @keydown.enter.prevent="selectHighlightedCustomer"
                                                    @keydown.esc.stop.prevent="closeCustomerDropdown"
                                                    @keydown.tab="closeCustomerDropdown"
                                                    aria-autocomplete="list"
                                                    role="combobox"
                                                    :aria-expanded="showCustomerDropdown"
                                                    aria-haspopup="listbox"
                                                    :aria-activedescendant="highlightedCustomerIndex >= 0 && filteredCustomers[highlightedCustomerIndex] ? 'customer-option-'+filteredCustomers[highlightedCustomerIndex].id : undefined"
                                                    autocomplete="off"
                                                />
                                                <button
                                                    v-if="assignment.customer_id"
                                                    type="button"
                                                    @click="clearCustomerSelection"
                                                    class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600"
                                                    aria-label="Clear customer selection"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                                <div
                                                    v-if="showCustomerDropdown"
                                                    class="absolute z-10 mt-1 w-full max-h-60 overflow-auto rounded-md border border-gray-200 bg-white shadow-lg"
                                                    role="listbox"
                                                >
                                                    <template v-if="filteredCustomers.length">
                                                        <button
                                                            v-for="(c, index) in filteredCustomers"
                                                            :id="'customer-option-'+c.id"
                                                            :key="c.id"
                                                            type="button"
                                                            class="w-full text-left px-3 py-2 text-sm"
                                                            :class="index === highlightedCustomerIndex ? 'bg-blue-600 text-white' : 'bg-white text-gray-900 hover:bg-gray-50'"
                                                            @mousedown.prevent
                                                            @click="selectCustomer(c)"
                                                            @mouseenter="highlightedCustomerIndex = index"
                                                            :aria-selected="assignment.customer_id === c.id"
                                                            role="option"
                                                        >
                                                            <div class="font-medium">{{ displayName(c) }}</div>
                                                            <div class="text-xxs" :class="index === highlightedCustomerIndex ? 'text-blue-100' : 'text-gray-500'">
                                                                {{ c.company_name || 'No company' }} • {{ c.email }}{{ c.phone ? ' • '+c.phone : '' }}
                                                            </div>
                                                        </button>
                                                    </template>
                                                    <div v-else class="px-3 py-2 text-sm text-gray-500">No matching customers.</div>
                                                </div>
                                            </div>
                                            <div v-if="selectedCustomer" class="mt-3 p-3 rounded border border-gray-200 bg-gray-50 text-sm">
                                                <div class="font-medium text-gray-900">{{ displayName(selectedCustomer) }}</div>
                                                <div class="text-gray-700">{{ selectedCustomer.company_name || '—' }}</div>
                                                <div class="text-gray-600">{{ selectedCustomer.contact_person || '—' }}</div>
                                                <div class="text-gray-500">{{ selectedCustomer.email }} • {{ selectedCustomer.phone || '—' }}</div>
                                            </div>
                                        </div>

                                        <div class="mt-4 pt-4 border-t border-gray-200 flex flex-col sm:flex-row gap-2 sm:justify-between">
                                            <button
                                                @click="switchToCreateForm"
                                                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded inline-flex items-center justify-center"
                                            >
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                </svg>
                                                Add New Customer
                                            </button>
                                            <button
                                                :disabled="!assignment.customer_id || openTimeSubmitting"
                                                @click="confirmAssign"
                                                class="flex-1 bg-blue-500 disabled:bg-blue-300 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded"
                                            >
                                                {{ isOpenTimeMode ? (openTimeSubmitting ? 'Starting…' : 'Start Open Time') : 'Assign' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button 
                                    @click="showCustomerModal = false, resetAssignment()"
                                    type="button" 
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create Customer Form Modal (shared component) -->
                <CustomerQuickCreateModal :show="showCreateCustomerForm" @close="showCreateCustomerForm = false" @created="onCustomerCreated" />

                <!-- Pricing Modal -->
                <div v-if="showPricingModalId !== null" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closePricingModal"></div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Rate & Discount</h3>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Rate (₱/h)</label>
                                                <input 
                                                    v-model.number="pricingForm.hourly_rate" 
                                                    type="number" min="0" step="0.01" 
                                                    class="mt-1 block w-full rounded-md shadow-sm text-sm border"
                                                    :class="pricingErrors.hourly_rate ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300'"
                                                />
                                                <p v-if="pricingErrors.hourly_rate" class="mt-1 text-xxs text-red-600">{{ pricingErrors.hourly_rate }}</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Pricing Type</label>
                                                <select 
                                                    v-model="pricingForm.pricing_type" 
                                                    class="mt-1 block w-full rounded-md shadow-sm text-sm border border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                                >
                                                    <option value="per_person">Per Person per Hour</option>
                                                    <option value="per_reservation">Per Reservation per Hour</option>
                                                </select>
                                                <p class="mt-1 text-xxs text-gray-500">
                                                    {{ pricingForm.pricing_type === 'per_person' ? 'Price multiplies by number of people' : 'Flat rate regardless of number of people' }}
                                                </p>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700">Disc. After (h)</label>
                                                    <input 
                                                        v-model.number="pricingForm.default_discount_hours" 
                                                        type="number" min="1" 
                                                        class="mt-1 block w-full rounded-md shadow-sm text-sm border"
                                                        :class="pricingErrors.default_discount_hours ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300'"
                                                    />
                                                    <p v-if="pricingErrors.default_discount_hours" class="mt-1 text-xxs text-red-600">{{ pricingErrors.default_discount_hours }}</p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700">Discount (%)</label>
                                                    <input 
                                                        v-model.number="pricingForm.default_discount_percentage" 
                                                        type="number" min="0" max="100" step="0.01" 
                                                        class="mt-1 block w-full rounded-md shadow-sm text-sm border"
                                                        :class="pricingErrors.default_discount_percentage ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300'"
                                                    />
                                                    <p v-if="pricingErrors.default_discount_percentage" class="mt-1 text-xxs text-red-600">{{ pricingErrors.default_discount_percentage }}</p>
                                                </div>
                                            </div>
                                            <p class="text-xxs text-gray-500">Applies to future reservations. Ongoing assignments retain their current rate.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button @click="savePricingModal" :disabled="updatingPricing === showPricingModalId" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                                    {{ updatingPricing === showPricingModalId ? 'Saving…' : 'Save' }}
                                </button>
                                <button @click="closePricingModal" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toasts -->
                <div v-if="toast.show" class="fixed top-4 right-4 z-[60]">
                    <div 
                        class="px-4 py-3 rounded shadow border text-sm"
                        :class="toast.type === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200'"
                    >
                        {{ toast.message }}
                    </div>
                </div>
                
                <!-- Payment Modal -->
                <PaymentModal
                    :show="showPaymentModal"
                    :reservation="selectedPaymentSpace"
                    @close="closePaymentModal"
                    @paid="router.reload()"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
