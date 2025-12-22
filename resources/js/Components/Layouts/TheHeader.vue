<script setup>
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <nav
        class="bg-white/80 backdrop-blur-md border-b border-gray-100 top-0 z-50 transition-all duration-300 fixed w-full"
    >
        <div
            class="max-w-7xl flex items-center justify-between mx-auto px-4 lg:px-0 py-4"
        >
            <div class="flex items-center gap-12">
                <Link href="/" class="flex items-center gap-2 group">
                    <div
                        class="w-8 h-8 bg-[#13087d] rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-md group-hover:scale-110 transition-transform"
                    >
                        E
                    </div>
                    <span
                        class="text-2xl font-extrabold text-[#13087d] tracking-tight"
                        >E-Sign</span
                    >
                </Link>

                <div class="hidden lg:flex items-center gap-8">
                    <Link
                        href="#"
                        class="text-sm font-medium text-gray-600 hover:text-[#13087d] transition-colors"
                        >Fitur</Link
                    >
                    <Link
                        href="#"
                        class="text-sm font-medium text-gray-600 hover:text-[#13087d] transition-colors"
                        >Tentang Kami</Link
                    >
                    <Link
                        href="#"
                        class="text-sm font-medium text-gray-600 hover:text-[#13087d] transition-colors"
                        >Kontak</Link
                    >
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Link
                    href="#"
                    class="hidden sm:inline-block text-sm font-semibold text-[#13087d] hover:text-[#190b9f] transition-colors"
                >
                    Masuk
                </Link>
                <Link
                    href="#"
                    class="bg-[#13087d] hover:bg-[#190b9f] text-white rounded-full shadow-md hover:shadow-lg px-6 py-2.5 text-sm font-bold duration-300 transition-all transform hover:-translate-y-0.5"
                >
                    Daftar Gratis
                </Link>
            </div>
        </div>
    </nav>
</template>
