<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Role Details: {{ role.name }}
                </h2>
                <div class="flex space-x-2">
                    <Link v-if="!role.is_system_role" 
                          :href="route('roles.edit', role.id)" 
                          class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Edit Role
                    </Link>
                    <Link :href="route('roles.index')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        Back to Roles
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Role Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Role Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <p class="mt-1 text-gray-900">{{ role.name }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Type</label>
                                <span class="mt-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                      :class="{
                                          'bg-purple-100 text-purple-800': role.type === 'admin',
                                          'bg-green-100 text-green-800': role.type === 'staff',
                                          'bg-gray-100 text-gray-800': role.type === 'custom'
                                      }">
                                    {{ role.type }}
                                </span>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <p class="mt-1 text-gray-900">{{ role.description || 'No description provided' }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <div class="mt-1 flex items-center space-x-2">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                    <span v-if="role.is_system_role" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        System Role
                                    </span>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Users</label>
                                <p class="mt-1 text-gray-900">{{ role.users_count || 0 }} user(s) assigned</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Created</label>
                                <p class="mt-1 text-gray-900">{{ formatDate(role.created_at) }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Last Updated</label>
                                <p class="mt-1 text-gray-900">{{ formatDate(role.updated_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">
                            Permissions ({{ role.permissions ? role.permissions.length : 0 }})
                        </h3>
                        
                        <div v-if="!role.permissions || role.permissions.length === 0" class="text-center py-8 text-gray-500">
                            No permissions assigned to this role.
                        </div>
                        
                        <div v-else v-for="(perms, category) in groupedPermissions" :key="category" class="mb-6">
                            <h4 class="text-md font-semibold text-gray-700 mb-3 capitalize border-b pb-2">
                                {{ category.replace(/_/g, ' ') }}
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                <div v-for="permission in perms" 
                                     :key="permission" 
                                     class="flex items-center space-x-2 p-2 bg-green-50 rounded">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="text-sm text-gray-700">{{ formatPermission(permission) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users with this Role -->
                <div v-if="users && users.length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Users with this Role</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Email
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users" :key="user.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ user.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ user.email }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ user.user_type }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('user-management.show', user.id)" class="text-indigo-600 hover:text-indigo-900">
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    role: {
        type: Object,
        required: true
    },
    users: {
        type: Array,
        default: () => []
    }
});

const groupedPermissions = computed(() => {
    if (!props.role.permissions) return {};
    
    const groups = {};
    props.role.permissions.forEach(permission => {
        const category = permission.split('.')[0];
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(permission);
    });
    return groups;
});

const formatPermission = (permission) => {
    return permission.split('.').slice(1).join(' ').replace(/-/g, ' ');
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>
