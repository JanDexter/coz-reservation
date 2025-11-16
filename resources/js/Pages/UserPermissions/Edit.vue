<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Manage Permissions: {{ user.name }}
                </h2>
                <Link :href="route('user-management.index')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Users
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- User Info -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <p class="mt-1 text-gray-900">{{ user.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <p class="mt-1 text-gray-900">{{ user.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">User Type</label>
                                <span class="mt-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                      :class="{
                                          'bg-purple-100 text-purple-800': userProfile.type === 'admin',
                                          'bg-green-100 text-green-800': userProfile.type === 'staff',
                                          'bg-blue-100 text-blue-800': userProfile.type === 'customer'
                                      }">
                                    {{ userProfile.type }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role Assignment -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Role Assignment</h3>
                        
                        <div class="flex items-end space-x-4">
                            <div class="flex-1">
                                <label for="role" class="block text-sm font-medium text-gray-700">Assign Role</label>
                                <select id="role" 
                                        v-model="selectedRole" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option :value="null">No Role (Individual Permissions Only)</option>
                                    <option v-for="role in availableRoles" :key="role.id" :value="role.id">
                                        {{ role.name }} ({{ role.permissions ? role.permissions.length : 0 }} permissions)
                                    </option>
                                </select>
                            </div>
                            <button @click="assignRole" 
                                    :disabled="assigningRole"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded disabled:opacity-50">
                                {{ assigningRole ? 'Assigning...' : 'Assign Role' }}
                            </button>
                        </div>
                        
                        <div v-if="currentRole" class="mt-4 p-4 bg-blue-50 rounded">
                            <p class="text-sm text-blue-800">
                                <strong>Current Role:</strong> {{ currentRole.name }}
                            </p>
                            <p class="text-xs text-blue-600 mt-1">
                                Individual permissions below will override or extend role permissions.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Individual Permissions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Individual Permissions</h3>
                            <div class="flex space-x-2">
                                <button @click="applyPreset('all')" 
                                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                    Grant All
                                </button>
                                <button @click="applyPreset('staff')" 
                                        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm">
                                    Staff Default
                                </button>
                                <button @click="applyPreset('readonly')" 
                                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm">
                                    Read Only
                                </button>
                                <button @click="clearAllPermissions" 
                                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                                    Clear All
                                </button>
                            </div>
                        </div>

                        <div class="mb-4 p-4 bg-gray-50 rounded">
                            <p class="text-sm text-gray-700">
                                <strong>Total Permissions:</strong> {{ totalPermissionsCount }}
                                <span v-if="userProfile.role" class="ml-4">
                                    ({{ rolePermissionsCount }} from role + {{ individualPermissionsCount }} individual)
                                </span>
                            </p>
                        </div>
                        
                        <div v-for="(perms, category) in groupedPermissions" :key="category" class="mb-6">
                            <h4 class="text-md font-semibold text-gray-700 mb-3 capitalize border-b pb-2">
                                {{ category.replace(/_/g, ' ') }}
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                <label v-for="permission in perms" 
                                       :key="permission" 
                                       class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded"
                                       :class="{
                                           'bg-blue-50': isPermissionFromRole(permission),
                                           'bg-green-50': hasIndividualPermission(permission) && !isPermissionFromRole(permission)
                                       }">
                                    <input type="checkbox" 
                                           :checked="hasPermission(permission)"
                                           @change="togglePermission(permission)"
                                           :disabled="togglingPermissions[permission]"
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">
                                        {{ formatPermission(permission) }}
                                        <span v-if="isPermissionFromRole(permission)" class="text-xs text-blue-600">(from role)</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true
    },
    currentPermissions: {
        type: Array,
        default: () => []
    },
    currentRole: {
        type: Object,
        default: null
    },
    allPermissions: {
        type: Object,
        required: true
    },
    availableRoles: {
        type: Array,
        default: () => []
    }
});

const selectedRole = ref(props.currentRole ? props.currentRole.id : null);
const assigningRole = ref(false);
const togglingPermissions = ref({});

// Convert nested permissions to flat array for easier handling
const allPermissionKeys = computed(() => {
    const keys = [];
    Object.keys(props.allPermissions).forEach(category => {
        Object.keys(props.allPermissions[category]).forEach(permKey => {
            keys.push(category + '.' + permKey);
        });
    });
    return keys;
});

const groupedPermissions = computed(() => {
    const groups = {};
    Object.keys(props.allPermissions).forEach(category => {
        groups[category] = [];
        Object.keys(props.allPermissions[category]).forEach(permKey => {
            groups[category].push(category + '.' + permKey);
        });
    });
    return groups;
});

const rolePermissionsCount = computed(() => {
    if (!currentRole.value) return 0;
    const role = availableRoles.value.find(r => r.id === currentRole.value.id);
    return role && role.permissions ? role.permissions.length : 0;
});

const individualPermissionsCount = computed(() => {
    return currentPermissions.value ? currentPermissions.value.length : 0;
});

const totalPermissionsCount = computed(() => {
    return currentPermissions.value ? currentPermissions.value.length : 0;
});

const hasPermission = (permission) => {
    return currentPermissions.value.includes(permission);
};

const hasIndividualPermission = (permission) => {
    // This needs to be tracked separately - for now return false
    // We'll need to fetch this from the backend
    return false;
};

const isPermissionFromRole = (permission) => {
    if (!currentRole.value) return false;
    const role = availableRoles.value.find(r => r.id === currentRole.value.id);
    return role && role.permissions && role.permissions.includes(permission);
};

const formatPermission = (permission) => {
    return permission.split('.').slice(1).join(' ').replace(/-/g, ' ');
};

const togglePermission = (permission) => {
    togglingPermissions.value[permission] = true;
    
    router.post(route('permissions.users.toggle', props.user.id), {
        permission: permission
    }, {
        preserveScroll: true,
        onFinish: () => {
            togglingPermissions.value[permission] = false;
        }
    });
};

const applyPreset = (preset) => {
    let selectedPermissions = [];
    switch (preset) {
        case 'all':
            selectedPermissions = [...allPermissionKeys.value];
            break;
        case 'staff':
            selectedPermissions = allPermissionKeys.value.filter(p => 
                !p.includes('user_management') && 
                !p.includes('settings')
            );
            break;
        case 'readonly':
            selectedPermissions = allPermissionKeys.value.filter(p => 
                p.includes('view_')
            );
            break;
    }
    
    router.post(route('permissions.users.preset', props.user.id), {
        preset: preset,
        permissions: selectedPermissions
    }, {
        preserveScroll: true
    });
};

const clearAllPermissions = () => {
    if (confirm('Are you sure you want to remove all individual permissions from this user?')) {
        router.put(route('permissions.users.update', props.user.id), {
            permissions: []
        }, {
            preserveScroll: true
        });
    }
};

const assignRole = () => {
    assigningRole.value = true;
    
    router.post(route('permissions.users.assign-role', props.user.id), {
        role_id: selectedRole.value
    }, {
        preserveScroll: true,
        onFinish: () => {
            assigningRole.value = false;
        }
    });
};
</script>
