<script setup>
import { onMounted, ref } from "vue";

defineOptions({
    inheritAttrs: false,
});

defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    labelValue: {
        type: String,
        required: true,
    },
});

defineEmits(["update:modelValue"]);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute("autofocus")) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div class="flex flex-col gap-0.5">
        <label class="block font-semibold text-sm text-gray-900" :for="$attrs.id">
            <span v-if="labelValue">{{ labelValue }}</span>
            <span v-else><slot /></span>
            <span
                v-if="$attrs.required !== undefined"
                class="ml-1 text-red-500"
                aria-hidden="true"
            >
                *
            </span>
        </label>

        <input
            ref="input"
            class="border-[#13087d] border-2 text-gray-900 rounded-xl px-3 py-1.5 text-base shadow-sm focus:outline-none"
            v-bind="$attrs"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>
