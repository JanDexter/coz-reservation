<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Role Management
                </h2>
                <Link :href="route('roles.create')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Create New Role
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="roles.data.length === 0" class="text-center py-8 text-gray-500">
                            No roles found. Create your first role to get started.
                        </div>
                        
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Permissions
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Users
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="role in roles.data" :key="role.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ role.name }}
                                                    </div>
                                                    <div v-if="role.is_system_role" class="text-xs text-blue-600">
                                                        System Role
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="{
                                                      'bg-purple-100 text-purple-800': role.type === 'admin',
                                                      'bg-green-100 text-green-800': role.type === 'staff',
                                                      'bg-gray-100 text-gray-800': role.type === 'custom'
                                                  }">
                                                {{ role.type }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ role.permissions ? role.permissions.length : 0 }} permissions
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ role.users_count || 0 }} users
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('roles.show', role.id)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                View
                                            </Link>
                                            <Link v-if="!role.is_system_role" :href="route('roles.edit', role.id)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                Edit
                                            </Link>
                                            <button v-if="!role.is_system_role" 
                                                    @click="deleteRole(role)" 
                                                    class="text-red-600 hover:text-red-900"
                                                    :disabled="role.users_count > 0">
                                                Delete
                                            </button>
                                            <span v-if="role.is_system_role" class="text-gray-400 text-xs">
                                                Protected
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="roles.links && roles.links.length > 3" class="mt-6">
                            <div class="flex flex-wrap -mb-1">
                                <template v-for="(link, key) in roles.links" :key="key">
                                    <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                                    <Link v-else class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" 
                                          :class="{ 'bg-blue-700 text-white': link.active }" 
                                          :href="link.url" 
                                          v-html="link.label" />
                                </template>
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

defineProps({
    roles: {
        type: Object,
        required: true
    }
});

const deleteRole = (role) => {
    if (role.users_count > 0) {
        alert('Cannot delete a role that has users assigned to it. Please reassign the users first.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete the role "${role.name}"? This action cannot be undone.`)) {
        router.delete(route('roles.destroy', role.id));
    }
};
</script>
