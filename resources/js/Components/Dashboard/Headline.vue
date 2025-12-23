<script setup>
import { Link } from "@inertiajs/vue3";

const scrollToFeatures = () => {
    const target = document.getElementById("features");
    if (!target) {
        return;
    }

    target.scrollIntoView({ behavior: "smooth", block: "start" });
};
</script>

<template>
    <section
        id="headline"
        class="relative max-w-7xl mx-auto px-4 lg:px-0 grid grid-cols-1 lg:grid-cols-2 gap-8 items-center overflow-hidden"
    >
        <div class="flex flex-col gap-4 z-10">
            <h1
                class="text-4xl lg:text-5xl font-extrabold text-[#13087d] leading-tight tracking-tight"
            >
                Tanda Tangan Digital yang
                <span class="text-blue-500">Cepat</span>,
                <span class="text-blue-500">Aman</span>, dan
                <span class="text-blue-500">Legal</span>
            </h1>
            <p class="text-lg text-gray-600 max-w-lg leading-relaxed">
                Platform e-Sign terpercaya untuk mempercepat proses bisnis Anda.
                Tandatangani dokumen penting kapan saja, di mana saja, dengan
                keamanan tingkat tinggi.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 mt-4">
                <Link
                    href="/sign"
                    class="inline-flex justify-center items-center bg-[#13087d] hover:bg-[#190b9f] text-white rounded-full shadow-lg hover:shadow-xl px-8 py-3 text-base font-bold transition-all duration-300 transform hover:-translate-y-1"
                >
                    Mulai Sekarang
                </Link>
                <button
                    type="button"
                    @click="scrollToFeatures"
                    class="inline-flex justify-center items-center bg-white border-2 border-[#13087d] text-[#13087d] hover:bg-gray-50 rounded-full shadow-md hover:shadow-lg px-8 py-3 text-base font-bold transition-all duration-300 transform hover:-translate-y-1"
                >
                    Pelajari Fitur
                </button>
            </div>

            <div
                class="pt-4 flex items-center gap-4 text-sm text-gray-600 font-medium"
            >
                <div class="flex items-center gap-1">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-green-500"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <span>Tersertifikasi</span>
                </div>
                <div class="flex items-center gap-1">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-green-500"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <span>Enkripsi</span>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-center relative lg:justify-end">
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[120%] bg-blue-100/50 rounded-full blur-3xl -z-10 dark:bg-[#13087d]/20"
            ></div>

            <img
                src="/assets/e-Sign-illustration.png"
                alt="Digital Signature Illustration"
                class="relative w-full max-w-md lg:max-w-lg object-contain drop-shadow-2xl transition-transform duration-500 hover:scale-[1.02]"
            />
        </div>
    </section>
</template>
