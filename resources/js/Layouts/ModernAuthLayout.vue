<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import logo from '../../img/logo.png';
import slide1 from '../../img/slideshow/Shared Space.jpg';
import slide2 from '../../img/slideshow/Private Space.jpg';
import slide3 from '../../img/slideshow/Exclusive Space.jpg';
import slide4 from '../../img/slideshow/Conference Room.png';

const slides = [
    {
        image: slide1,
        title: 'Modern Co-Working Space',
        description: 'Discover your perfect workspace in a professional environment'
    },
    {
        image: slide2,
        title: 'Private Workspaces',
        description: 'Focus and productivity in your own dedicated space'
    },
    {
        image: slide3,
        title: 'Exclusive Workspace',
        description: 'Premium dedicated areas for your team or projects'
    },
    {
        image: slide4,
        title: 'Conference Room',
        description: 'Professional meeting space for collaboration and presentations'
    }
];

const currentSlide = ref(0);
let slideInterval = null;

const nextSlide = () => {
    currentSlide.value = (currentSlide.value + 1) % slides.length;
};

const setSlide = (index) => {
    currentSlide.value = index;
};

onMounted(() => {
    slideInterval = setInterval(nextSlide, 5000);
});

onUnmounted(() => {
    if (slideInterval) {
        clearInterval(slideInterval);
    }
});
</script>

<template>
    <div class="min-h-screen flex">
        <!-- Left Side - Slideshow -->
        <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-[#2f4686] to-[#1e2f5a] overflow-hidden">
            <!-- Logo Overlay - Clickable -->
            <Link href="/" class="absolute top-8 left-8 z-20 bg-white rounded-lg px-4 py-2 shadow-lg hover:shadow-xl transition-shadow duration-200">
                <img :src="logo" alt="CO-Z Logo" class="h-10 w-auto" />
            </Link>

            <!-- Home Button -->
            <Link 
                href="/" 
                class="absolute top-8 right-8 z-20 flex items-center gap-2 bg-white/10 backdrop-blur-sm hover:bg-white/20 text-white rounded-lg px-4 py-2 transition-all duration-200 border border-white/20 hover:border-white/40"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                <span class="font-medium">Home</span>
            </Link>

            <!-- Slideshow Container -->
            <div class="relative w-full h-full">
                <TransitionGroup name="slide">
                    <div
                        v-for="(slide, index) in slides"
                        v-show="currentSlide === index"
                        :key="index"
                        class="absolute inset-0"
                    >
                        <div class="absolute inset-0 bg-black/40 z-10"></div>
                        <img
                            :src="slide.image"
                            :alt="slide.title"
                            class="w-full h-full object-cover"
                        />
                        <div class="absolute inset-0 z-20 flex flex-col justify-end p-12">
                            <h2 class="text-4xl font-bold text-white mb-4">{{ slide.title }}</h2>
                            <p class="text-xl text-white/90 max-w-md">{{ slide.description }}</p>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Slide Indicators -->
                <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 z-30 flex gap-2">
                    <button
                        v-for="(slide, index) in slides"
                        :key="index"
                        @click="setSlide(index)"
                        class="w-2 h-2 rounded-full transition-all duration-300"
                        :class="currentSlide === index ? 'bg-white w-8' : 'bg-white/50 hover:bg-white/75'"
                        :aria-label="`Go to slide ${index + 1}`"
                    ></button>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-gray-50">
            <div class="w-full max-w-md">
                <!-- Mobile Logo - Clickable -->
                <div class="lg:hidden flex justify-center mb-8">
                    <Link :href="route('customer.view')">
                        <img :src="logo" alt="CO-Z Logo" class="h-16 w-auto hover:opacity-80 transition-opacity duration-200" />
                    </Link>
                </div>

                <!-- Mobile Home Button -->
                <Link 
                    :href="route('customer.view')" 
                    class="lg:hidden flex items-center justify-center gap-2 mb-6 bg-[#2f4686] hover:bg-[#1e2f5a] text-white rounded-lg px-4 py-2 transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    <span class="font-medium">Back to Home</span>
                </Link>

                <!-- Form Content -->
                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <slot />
                </div>

                <!-- Footer -->
                <p class="mt-8 text-center text-sm text-gray-600">
                    © {{ new Date().getFullYear() }} CO-Z Co-Workspace & Study Hub. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: opacity 1s ease;
}

.slide-enter-from {
    opacity: 0;
}

.slide-leave-to {
    opacity: 0;
}
</style>
