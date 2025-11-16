<template>
    <div class="fixed bottom-20 right-4 z-40 max-w-md">
        <button 
            @click="toggleDiagnostic"
            class="bg-gray-800 text-white px-3 py-2 rounded-lg shadow-lg text-sm font-medium hover:bg-gray-700 transition-colors mb-2"
        >
            {{ showDiagnostic ? 'Hide' : 'Show' }} PWA Status
        </button>

        <div v-if="showDiagnostic" class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-4 border border-gray-200 dark:border-gray-700 max-h-96 overflow-y-auto">
            <h3 class="font-bold text-lg mb-3 text-gray-900 dark:text-white">PWA Installation Status</h3>
            
            <!-- Basic Status -->
            <div class="space-y-2 mb-4">
                <div class="flex items-center gap-2">
                    <span :class="isSecure ? 'text-green-600' : 'text-red-600'">
                        {{ isSecure ? '✅' : '❌' }}
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        HTTPS/Localhost: <strong>{{ isSecure ? 'Yes' : 'No' }}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span :class="serviceWorkerStatus === 'activated' ? 'text-green-600' : 'text-orange-600'">
                        {{ serviceWorkerStatus === 'activated' ? '✅' : '⚠️' }}
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Service Worker: <strong>{{ serviceWorkerStatus }}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span :class="manifestExists ? 'text-green-600' : 'text-red-600'">
                        {{ manifestExists ? '✅' : '❌' }}
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Manifest: <strong>{{ manifestExists ? 'Found' : 'Missing' }}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span :class="isInstallable ? 'text-green-600' : 'text-orange-600'">
                        {{ isInstallable ? '✅' : '⚠️' }}
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Installable: <strong>{{ isInstallable ? 'Yes' : 'No' }}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span :class="isInstalled ? 'text-green-600' : 'text-gray-400'">
                        {{ isInstalled ? '✅' : '⚪' }}
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Installed: <strong>{{ isInstalled ? 'Yes' : 'No' }}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span :class="isOnline ? 'text-green-600' : 'text-orange-600'">
                        {{ isOnline ? '✅' : '⚠️' }}
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Online: <strong>{{ isOnline ? 'Yes' : 'No' }}</strong>
                    </span>
                </div>
            </div>

            <!-- Detailed Info -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                <details class="text-xs">
                    <summary class="cursor-pointer font-semibold text-gray-900 dark:text-white mb-2">
                        Detailed Information
                    </summary>
                    <div class="space-y-2 pl-2">
                        <div>
                            <strong class="text-gray-700 dark:text-gray-300">Current URL:</strong>
                            <div class="text-gray-600 dark:text-gray-400 break-all">{{ currentUrl }}</div>
                        </div>
                        <div>
                            <strong class="text-gray-700 dark:text-gray-300">Display Mode:</strong>
                            <div class="text-gray-600 dark:text-gray-400">{{ displayMode }}</div>
                        </div>
                        <div>
                            <strong class="text-gray-700 dark:text-gray-300">User Agent:</strong>
                            <div class="text-gray-600 dark:text-gray-400 break-all">{{ userAgent }}</div>
                        </div>
                    </div>
                </details>
            </div>

            <!-- Issues & Solutions -->
            <div v-if="issues.length > 0" class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                <h4 class="font-semibold text-sm text-red-600 dark:text-red-400 mb-2">Issues Found:</h4>
                <ul class="space-y-2 text-xs">
                    <li v-for="(issue, index) in issues" :key="index" class="bg-red-50 dark:bg-red-900/20 p-2 rounded">
                        <div class="font-semibold text-red-700 dark:text-red-300">{{ issue.title }}</div>
                        <div class="text-red-600 dark:text-red-400 mt-1">{{ issue.solution }}</div>
                    </li>
                </ul>
            </div>

            <!-- Success Message -->
            <div v-else class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded text-xs">
                    <div class="font-semibold text-green-700 dark:text-green-300">✅ All requirements met!</div>
                    <div v-if="!isInstallable && !isInstalled" class="text-green-600 dark:text-green-400 mt-1">
                        The install prompt should appear after a few page visits. Try refreshing or visiting a few more pages.
                    </div>
                </div>
            </div>

            <!-- Test Install Button -->
            <button 
                v-if="isInstallable && !isInstalled"
                @click="testInstall"
                class="w-full mt-3 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
            >
                Test Install Now
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePWA } from '../composables/usePWA';

const { isOnline, isInstallable, isInstalled, installPWA } = usePWA();

const showDiagnostic = ref(false);
const isSecure = ref(false);
const serviceWorkerStatus = ref('checking...');
const manifestExists = ref(false);
const currentUrl = ref('');
const displayMode = ref('');
const userAgent = ref('');

const toggleDiagnostic = () => {
    showDiagnostic.value = !showDiagnostic.value;
};

const checkSecureContext = () => {
    isSecure.value = window.isSecureContext || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
};

const checkServiceWorker = async () => {
    if ('serviceWorker' in navigator) {
        try {
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration) {
                if (registration.active) {
                    serviceWorkerStatus.value = 'activated';
                } else if (registration.installing) {
                    serviceWorkerStatus.value = 'installing';
                } else if (registration.waiting) {
                    serviceWorkerStatus.value = 'waiting';
                } else {
                    serviceWorkerStatus.value = 'registered';
                }
            } else {
                serviceWorkerStatus.value = 'not registered';
            }
        } catch (error) {
            serviceWorkerStatus.value = 'error: ' + error.message;
        }
    } else {
        serviceWorkerStatus.value = 'not supported';
    }
};

const checkManifest = async () => {
    try {
        const response = await fetch('/manifest.json');
        manifestExists.value = response.ok;
    } catch (error) {
        manifestExists.value = false;
    }
};

const checkDisplayMode = () => {
    if (window.matchMedia('(display-mode: standalone)').matches) {
        displayMode.value = 'standalone (PWA)';
    } else if (window.matchMedia('(display-mode: fullscreen)').matches) {
        displayMode.value = 'fullscreen';
    } else if (window.matchMedia('(display-mode: minimal-ui)').matches) {
        displayMode.value = 'minimal-ui';
    } else {
        displayMode.value = 'browser';
    }
};

const issues = computed(() => {
    const problems = [];

    if (!isSecure.value) {
        problems.push({
            title: 'Not served over HTTPS',
            solution: 'PWA requires HTTPS. Use localhost for testing or deploy to a domain with SSL certificate.'
        });
    }

    if (serviceWorkerStatus.value === 'not registered') {
        problems.push({
            title: 'Service Worker not registered',
            solution: 'Check browser console for service worker registration errors. Ensure /sw.js exists and is accessible.'
        });
    }

    if (!manifestExists.value) {
        problems.push({
            title: 'Manifest not found',
            solution: 'Ensure /manifest.json exists and is properly linked in the HTML head.'
        });
    }

    if (!isInstallable.value && !isInstalled.value && isSecure.value && serviceWorkerStatus.value === 'activated' && manifestExists.value) {
        problems.push({
            title: 'Install criteria not met',
            solution: 'Chrome requires visiting the site a few times before showing the install prompt. Try refreshing or visiting more pages.'
        });
    }

    return problems;
});

const testInstall = async () => {
    try {
        await installPWA();
        showDiagnostic.value = false;
    } catch (error) {
        console.error('Test install failed:', error);
        alert('Install failed: ' + error.message);
    }
};

onMounted(() => {
    checkSecureContext();
    checkServiceWorker();
    checkManifest();
    checkDisplayMode();
    
    currentUrl.value = window.location.href;
    userAgent.value = navigator.userAgent;

    // Re-check service worker status periodically
    setInterval(checkServiceWorker, 5000);
});
</script>
