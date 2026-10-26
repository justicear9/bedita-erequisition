<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const canvasRef = ref(null);
const drawing = ref(false);
let ctx = null;
let snapshot = '';

const setupContext = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    ctx = canvas.getContext('2d');
    const ratio = window.devicePixelRatio || 1;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1D3A6C';
};

const restoreSnapshot = (dataUrl) => {
    if (!dataUrl || !canvasRef.value || !ctx) return;
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    const img = new Image();
    img.onload = () => {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setupContext();
        ctx.drawImage(img, 0, 0, rect.width, rect.height);
    };
    img.src = dataUrl;
};

const resize = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const nextWidth = Math.max(1, Math.round(rect.width * ratio));
    const nextHeight = Math.max(1, Math.round(rect.height * ratio));

    // Mobile browsers fire resize when the URL bar shows/hides on scroll.
    // Skip if the drawable size did not meaningfully change.
    if (canvas.width === nextWidth && canvas.height === nextHeight) {
        return;
    }

    const preserve = snapshot || props.modelValue || '';

    canvas.width = nextWidth;
    canvas.height = nextHeight;
    setupContext();

    if (preserve) {
        restoreSnapshot(preserve);
    }
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
    snapshot = canvasRef.value.toDataURL('image/png');
    emit('update:modelValue', snapshot);
};

const clear = () => {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);
    snapshot = '';
    emit('update:modelValue', '');
};

watch(
    () => props.modelValue,
    (value) => {
        if (!value) {
            snapshot = '';
        } else if (value !== snapshot) {
            snapshot = value;
            restoreSnapshot(value);
        }
    },
);

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
