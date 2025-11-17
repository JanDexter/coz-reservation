<script setup>
import { Head, Link } from '@inertiajs/vue3';
import logo from '../../../img/logo.png';

defineProps({
    status: {
        type: Number,
        default: 404,
    },
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-8">
        <Head title="404 - Page Not Found" />
        
        <div class="max-w-lg w-full text-center">
            <!-- Logo -->
            <div class="flex justify-center mb-6">
                <img :src="logo" alt="CO-Z Logo" class="h-16 w-auto" />
            </div>

            <!-- Error Message -->
            <div>
                <h1 class="text-7xl font-bold text-[#2f4686] mb-3">404</h1>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Well, This Is Awkward...</h2>
                <p class="text-gray-600 text-sm mb-1">
                    🤔 Looks like this page is playing hide and seek with us!
                </p>
                <p class="text-gray-500 text-xs mb-6">
                    Maybe it's booking a private space or taking a study break.
                </p>

                <!-- Action Button -->
                <div class="mb-6">
                    <Link
                        :href="route('customer.view')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#2f4686] hover:bg-[#3956a3] text-white text-sm font-medium rounded-lg transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                        </svg>
                        Back to Home
                    </Link>
                </div>

                <!-- Help Text -->
                <p class="text-xs text-gray-500">
                    Need help? 
                    <a href="https://www.facebook.com/COZeeNarra" target="_blank" rel="noopener noreferrer" class="text-[#2f4686] hover:text-[#3956a3] font-medium underline">
                        Contact us
                    </a>
                </p>
            </div>
        </div>
    </div>
</template>
