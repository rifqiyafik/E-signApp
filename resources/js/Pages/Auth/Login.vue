<script setup>
import TextInput from "@/Components/Atoms/TextInput.vue";
import PrimaryButton from "@/Components/Atoms/PrimaryButton.vue";
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Log in" />
    <div
        class="flex flex-col justify-center items-center bg-[#13087d] min-h-screen"
    >
        <div
            class="bg-white rounded-2xl max-w-xl w-full p-12 drop-shadow-2xl flex flex-col gap-6"
        >
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 bg-[#13087d] rounded-lg flex items-center justify-center text-white font-bold text-xl transition-transform"
                    >
                        E
                    </div>
                    <span
                        class="text-2xl font-extrabold text-[#13087d] tracking-tight"
                        >E-Sign</span
                    >
                </div>
                <div class="flex flex-col gap-0.5">
                    <p class="text-gray-900 font-medium text-lg">
                        Halo! Senang sekali bertemu denganmu lagi!
                    </p>
                    <p class="text-gray-600 font-normal">
                        Kami sangat senang bisa menyambutmu kembali.
                    </p>
                </div>
            </div>
            <form>
                <div class="flex flex-col gap-4">
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        labelValue="Email"
                    />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        labelValue="Password"
                    />

                    <PrimaryButton
                        class="w-full justify-center py-3 text-xs mt-3"
                    >
                        Masuk
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
