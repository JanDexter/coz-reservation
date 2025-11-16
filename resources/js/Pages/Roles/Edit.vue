<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Edit Role: {{ role.name }}
                </h2>
                <Link :href="route('roles.index')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Roles
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="role.is_system_role" class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
                    <p class="font-bold">System Role</p>
                    <p>This is a system role. Some modifications may be restricted.</p>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <!-- Name -->
                            <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-gray-700">Role Name *</label>
                                <input type="text" 
                                       id="name" 
                                       v-model="form.name" 
                                       :disabled="role.is_system_role"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                                       required>
                                <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</div>
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="description" 
                                          v-model="form.description" 
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                <div v-if="form.errors.description" class="text-red-600 text-sm mt-1">{{ form.errors.description }}</div>
                            </div>

                            <!-- Type -->
                            <div class="mb-6">
                                <label for="type" class="block text-sm font-medium text-gray-700">Role Type *</label>
                                <select id="type" 
                                        v-model="form.type" 
                                        :disabled="role.is_system_role"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                                        required>
                                    <option value="admin">Admin</option>
                                    <option value="staff">Staff</option>
                                    <option value="custom">Custom</option>
                                </select>
                                <div v-if="form.errors.type" class="text-red-600 text-sm mt-1">{{ form.errors.type }}</div>
                            </div>

                            <!-- Permission Presets -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Quick Permission Presets</label>
                                <div class="flex space-x-2">
                                    <button type="button" 
                                            @click="applyPreset('all')" 
                                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                        All Permissions
                                    </button>
                                    <button type="button" 
                                            @click="applyPreset('staff')" 
                                            class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                        Staff Default
                                    </button>
                                    <button type="button" 
                                            @click="applyPreset('readonly')" 
                                            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                        Read Only
                                    </button>
                                    <button type="button" 
                                            @click="form.permissions = []" 
                                            class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                                        Clear All
                                    </button>
                                </div>
                            </div>

                            <!-- Permissions -->
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Permissions</h3>
                                
                                <div v-for="(perms, category) in groupedPermissions" :key="category" class="mb-6">
                                    <h4 class="text-md font-semibold text-gray-700 mb-2 capitalize">
                                        {{ category.replace(/_/g, ' ') }}
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                        <label v-for="permission in perms" 
                                               :key="permission" 
                                               class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded">
                                            <input type="checkbox" 
                                                   :value="permission" 
                                                   v-model="form.permissions"
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="text-sm text-gray-700">{{ formatPermission(permission) }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="form.errors.permissions" class="text-red-600 text-sm mt-1">{{ form.errors.permissions }}</div>
                            </div>

                            <!-- Users Count -->
                            <div v-if="role.users_count > 0" class="mb-6 p-4 bg-blue-50 rounded">
                                <p class="text-sm text-blue-800">
                                    <strong>{{ role.users_count }}</strong> user(s) are currently assigned to this role.
                                    Changes to permissions will affect all these users.
                                </p>
                            </div>

                            <!-- Submit -->
                            <div class="flex items-center justify-end space-x-4">
                                <Link :href="route('roles.index')" class="text-gray-600 hover:text-gray-900">
                                    Cancel
                                </Link>
                                <button type="submit" 
                                        :disabled="form.processing"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded disabled:opacity-50">
                                    {{ form.processing ? 'Updating...' : 'Update Role' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    role: {
        type: Object,
        required: true
    },
    allPermissions: {
        type: Object,
        required: true
    }
});

const form = useForm({
    name: props.role.name,
    description: props.role.description || '',
    type: props.role.type,
    permissions: props.role.permissions || []
});

const groupedPermissions = computed(() => {
    // Convert nested permission structure to flat grouped format
    const groups = {};
    Object.keys(props.allPermissions).forEach(category => {
        groups[category] = [];
        Object.keys(props.allPermissions[category]).forEach(permKey => {
            groups[category].push(category + '.' + permKey);
        });
    });
    return groups;
});

const allPermissionKeys = computed(() => {
    // Get all permission keys as flat array
    const keys = [];
    Object.keys(props.allPermissions).forEach(category => {
        Object.keys(props.allPermissions[category]).forEach(permKey => {
            keys.push(category + '.' + permKey);
        });
    });
    return keys;
});

const formatPermission = (permission) => {
    return permission.split('.').slice(1).join(' ').replace(/-/g, ' ');
};

const applyPreset = (preset) => {
    switch (preset) {
        case 'all':
            form.permissions = [...allPermissionKeys.value];
            break;
        case 'staff':
            form.permissions = allPermissionKeys.value.filter(p => 
                !p.includes('user_management') && 
                !p.includes('settings')
            );
            break;
        case 'readonly':
            form.permissions = allPermissionKeys.value.filter(p => 
                p.includes('view_')
            );
            break;
    }
};

const submit = () => {
    form.put(route('roles.update', props.role.id));
};
</script>
