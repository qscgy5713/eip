<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    twoFactor: { type: Object, required: true },
});

const enableForm = useForm({ password: '' });
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });
const regenForm = useForm({ password: '' });
const qrDataUrl = ref('');
const showDisable = ref(false);
const showRegen = ref(false);

const renderQr = async () => {
    qrDataUrl.value = props.twoFactor.pending
        ? await QRCode.toDataURL(props.twoFactor.pending.uri, { margin: 1, width: 200 })
        : '';
};
onMounted(renderQr);
watch(() => props.twoFactor.pending?.uri, renderQr);

const enable = () => enableForm.post(route('two-factor.enable'), { preserveScroll: true, onSuccess: () => enableForm.reset() });
const confirm = () => confirmForm.post(route('two-factor.confirm'), { preserveScroll: true, onSuccess: () => confirmForm.reset() });
const disable = () => disableForm.post(route('two-factor.disable'), {
    preserveScroll: true,
    onSuccess: () => { disableForm.reset(); showDisable.value = false; },
});
const regen = () => regenForm.post(route('two-factor.recovery-codes'), {
    preserveScroll: true,
    onSuccess: () => { regenForm.reset(); showRegen.value = false; },
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">雙因素驗證 (2FA)</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                登入時除了密碼,還要輸入手機驗證器 App(Google Authenticator、Microsoft Authenticator、1Password 等)產生的 6 位數驗證碼,即使密碼外洩也無法登入。
            </p>
        </header>

        <p v-if="twoFactor.required" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-100">
            依公司資安政策,您的角色必須啟用雙因素驗證。
        </p>

        <!-- 一次性復原碼(僅在剛啟用或重新產生後顯示一次) -->
        <div v-if="twoFactor.recoveryCodes" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-700/50 dark:bg-emerald-900/20">
            <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">請立即抄下或存放這些復原碼,離開此頁後不會再顯示。</p>
            <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-200">手機遺失時,每組復原碼可代替驗證碼登入一次。</p>
            <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-gray-900 dark:text-gray-100">
                <li v-for="c in twoFactor.recoveryCodes" :key="c" class="rounded bg-white px-2 py-1 dark:bg-gray-900">{{ c }}</li>
            </ul>
        </div>

        <!-- 狀態 1:未啟用 -->
        <form v-if="!twoFactor.enabled && !twoFactor.pending" @submit.prevent="enable" class="mt-4 max-w-xl space-y-3">
            <p class="text-sm text-gray-700 dark:text-gray-300">目前狀態:<span class="font-semibold">未啟用</span></p>
            <div>
                <InputLabel for="tf_enable_pw" value="輸入目前密碼以開始設定" />
                <TextInput id="tf_enable_pw" type="password" class="mt-1 block w-full" v-model="enableForm.password" autocomplete="current-password" required />
                <InputError class="mt-2" :message="enableForm.errors.password" />
            </div>
            <PrimaryButton :disabled="enableForm.processing">開始設定</PrimaryButton>
        </form>

        <!-- 狀態 2:已產生密鑰,等待確認 -->
        <div v-else-if="twoFactor.pending" class="mt-4 max-w-xl space-y-3">
            <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                <li>在手機驗證器 App 掃描下方 QR Code(或手動輸入密鑰)。</li>
                <li>輸入 App 顯示的 6 位數驗證碼並按「確認啟用」。</li>
            </ol>
            <div class="flex flex-wrap items-center gap-4">
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="2FA QR Code" class="h-40 w-40 rounded bg-white p-1" />
                <div class="text-xs text-gray-600 dark:text-gray-400">
                    手動輸入密鑰:
                    <div class="mt-1 select-all break-all rounded bg-gray-100 px-2 py-1 font-mono text-sm text-gray-900 dark:bg-gray-900 dark:text-gray-100">{{ twoFactor.pending.secret }}</div>
                </div>
            </div>
            <form @submit.prevent="confirm" class="space-y-2">
                <InputLabel for="tf_code" value="6 位數驗證碼" />
                <TextInput id="tf_code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="8" class="block w-40" v-model="confirmForm.code" required />
                <InputError :message="confirmForm.errors.code" />
                <PrimaryButton :disabled="confirmForm.processing">確認啟用</PrimaryButton>
            </form>
        </div>

        <!-- 狀態 3:已啟用 -->
        <div v-else class="mt-4 max-w-xl space-y-4">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                目前狀態:<span class="font-semibold text-emerald-600 dark:text-emerald-400">已啟用</span>
                <span class="ml-2 text-xs text-gray-500">(剩餘復原碼 {{ twoFactor.recoveryRemaining }} 組)</span>
            </p>

            <div class="flex flex-wrap gap-2">
                <SecondaryButton type="button" @click="showRegen = !showRegen; showDisable = false">重新產生復原碼</SecondaryButton>
                <DangerButton v-if="!twoFactor.required" type="button" @click="showDisable = !showDisable; showRegen = false">停用雙因素驗證</DangerButton>
            </div>

            <form v-if="showRegen" @submit.prevent="regen" class="space-y-2">
                <InputLabel for="tf_regen_pw" value="輸入目前密碼(舊復原碼將全部失效)" />
                <TextInput id="tf_regen_pw" type="password" class="block w-full" v-model="regenForm.password" autocomplete="current-password" required />
                <InputError :message="regenForm.errors.password" />
                <PrimaryButton :disabled="regenForm.processing">產生新復原碼</PrimaryButton>
            </form>

            <form v-if="showDisable" @submit.prevent="disable" class="space-y-2">
                <InputLabel for="tf_disable_pw" value="輸入目前密碼以停用" />
                <TextInput id="tf_disable_pw" type="password" class="block w-full" v-model="disableForm.password" autocomplete="current-password" required />
                <InputError :message="disableForm.errors.password" />
                <DangerButton :disabled="disableForm.processing">確認停用</DangerButton>
            </form>
        </div>
    </section>
</template>
