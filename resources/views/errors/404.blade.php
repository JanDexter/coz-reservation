<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#eef3ff] to-[#d4e3ff] px-4">
    <div class="max-w-2xl w-full text-center">
        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <img src="{{ asset('img/logo.png') }}" alt="CO-Z Logo" class="h-20 w-auto" />
        </div>

        <!-- Error Illustration -->
        <div class="mb-8">
            <svg class="w-64 h-64 mx-auto text-[#2f4686] opacity-20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
        </div>

        <!-- Error Message -->
        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12">
            <h1 class="text-6xl md:text-8xl font-bold text-[#2f4686] mb-4">404</h1>
            <h2 class="text-2xl md:text-3xl font-semibold text-gray-800 mb-4">Well, This Is Awkward...</h2>
            <p class="text-gray-600 text-lg mb-4 max-w-md mx-auto">
                🤔 Looks like this page is playing hide and seek with us!
            </p>
            <p class="text-gray-500 text-base mb-8 max-w-lg mx-auto italic">
                Maybe it's booking a private space, grabbing a coffee, or just taking a study break. 
                Either way, we can't seem to find it anywhere. Let's get you back to somewhere that actually exists!
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a
                    href="{{ route('customer.view') }}"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-[#2f4686] hover:bg-[#3956a3] text-white font-semibold rounded-lg transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Back to Home
                </a>

                <button
                    onclick="window.history.back()"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-white hover:bg-gray-50 text-[#2f4686] font-semibold rounded-lg border-2 border-[#2f4686] transition-all duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Go Back
                </button>
            </div>

            <!-- Help Text -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    Need help? 
                    <a href="https://www.facebook.com/COZeeNarra" target="_blank" rel="noopener noreferrer" class="text-[#2f4686] hover:text-[#3956a3] font-medium underline">
                        Contact us
                    </a>
                    or visit our homepage.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <p class="mt-8 text-sm text-gray-600">
            © {{ date('Y') }} CO-Z Co-Workspace & Study Hub. All rights reserved.
        </p>
    </div>
</body>
</html>
