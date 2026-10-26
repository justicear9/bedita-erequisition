<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const emit = defineEmits(['update:modelValue']);

const canvasRef = ref(null);
const drawing = ref(false);
let ctx = null;

const resize = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx = canvas.getContext('2d');
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1D3A6C';
};

const pos = (e) => {
    const rect = canvasRef.value.getBoundingClientRect();
    const point = e.touches?.[0] ?? e;
    return { x: point.clientX - rect.left, y: point.clientY - rect.top };
};

const start = (e) => {
    e.preventDefault();
    drawing.value = true;
    const { x, y } = pos(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
};

const move = (e) => {
    if (!drawing.value) return;
    e.preventDefault();
    const { x, y } = pos(e);
    ctx.lineTo(x, y);
    ctx.stroke();
};

const end = () => {
    if (!drawing.value) return;
    drawing.value = false;
    emit('update:modelValue', canvasRef.value.toDataURL('image/png'));
};

const clear = () => {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);
    emit('update:modelValue', '');
};

onMounted(() => {
    resize();
    window.addEventListener('resize', resize);
});

onUnmounted(() => window.removeEventListener('resize', resize));

defineExpose({ clear });
</script>

<template>
    <div>
        <div class="overflow-hidden rounded-xl border border-dashed border-bedita-sky/40 bg-white">
            <canvas
                ref="canvasRef"
                class="h-40 w-full touch-none cursor-crosshair"
                @mousedown="start"
                @mousemove="move"
                @mouseup="end"
                @mouseleave="end"
                @touchstart="start"
                @touchmove="move"
                @touchend="end"
            />
        </div>
        <div class="mt-2 flex justify-end">
            <button
                type="button"
                class="text-sm font-medium text-bedita-navy hover:text-bedita-red"
                @click="clear"
            >
                Clear
            </button>
        </div>
    </div>
</template>
