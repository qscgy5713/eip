<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    width: {
        type: Number,
        default: 480,
    },
    height: {
        type: Number,
        default: 160,
    },
});

const emit = defineEmits(['update:modelValue', 'clear']);

const canvasRef = ref(null);
const isDrawing = ref(false);
const hasSignature = ref(false);
let ctx = null;

const initCanvas = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    ctx = canvas.getContext('2d');
    const ratio = window.devicePixelRatio || 1;

    // 依據 Retina 高解析度配置
    canvas.width = props.width * ratio;
    canvas.height = props.height * ratio;
    canvas.style.width = `${props.width}px`;
    canvas.style.height = `${props.height}px`;

    ctx.scale(ratio, ratio);
    ctx.strokeStyle = '#1e293b'; // 深深藍灰色筆觸
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    drawGuideline();
};

const drawGuideline = () => {
    if (!ctx) return;
    // 繪製底線參考線
    ctx.save();
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 4]);
    ctx.beginPath();
    ctx.moveTo(20, props.height - 35);
    ctx.lineTo(props.width - 20, props.height - 35);
    ctx.stroke();
    ctx.restore();
};

const getPos = (e) => {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
        x: clientX - rect.left,
        y: clientY - rect.top,
    };
};

const startDrawing = (e) => {
    e.preventDefault();
    isDrawing.value = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
};

const draw = (e) => {
    if (!isDrawing.value) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    hasSignature.value = true;
};

const stopDrawing = (e) => {
    if (!isDrawing.value) return;
    if (e) e.preventDefault();
    isDrawing.value = false;
    ctx.closePath();

    if (hasSignature.value) {
        emitDataUrl();
    }
};

const emitDataUrl = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const dataUrl = canvas.toDataURL('image/png');
    emit('update:modelValue', dataUrl);
};

const clear = () => {
    if (!ctx) return;
    const ratio = window.devicePixelRatio || 1;
    ctx.clearRect(0, 0, props.width * ratio, props.height * ratio);
    drawGuideline();
    hasSignature.value = false;
    emit('update:modelValue', '');
    emit('clear');
};

onMounted(() => {
    initCanvas();
});

watch(() => props.modelValue, (newVal) => {
    if (!newVal && hasSignature.value) {
        clear();
    }
});
</script>

<template>
    <div class="space-y-2">
        <div class="relative border-2 border-dashed border-gray-300 hover:border-indigo-400 bg-white rounded-xl overflow-hidden shadow-inner transition select-none flex items-center justify-center">
            <canvas
                ref="canvasRef"
                class="cursor-crosshair touch-none"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseleave="stopDrawing"
                @touchstart="startDrawing"
                @touchmove="draw"
                @touchend="stopDrawing"
            ></canvas>

            <!-- 水印提示 -->
            <div
                v-if="!hasSignature"
                class="absolute pointer-events-none text-center select-none text-gray-300 text-xs flex flex-col items-center justify-center"
            >
                <svg class="w-6 h-6 mb-1 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
                <span>請在此處以滑鼠或觸控筆手寫簽名</span>
            </div>
        </div>

        <div class="flex items-center justify-between text-xs">
            <span class="text-gray-400">
                {{ hasSignature ? '✓ 已感應手寫簽名' : '支援觸控、繪圖板或滑鼠筆跡' }}
            </span>
            <button
                type="button"
                @click="clear"
                class="px-2.5 py-1 text-2xs font-semibold text-gray-600 hover:text-red-600 bg-gray-100 hover:bg-gray-200 rounded-md transition"
            >
                清除重簽
            </button>
        </div>
    </div>
</template>
