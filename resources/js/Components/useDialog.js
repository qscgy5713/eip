import { reactive } from 'vue';

// 全域對話框狀態:取代瀏覽器原生 confirm / alert / prompt,由 DialogHost 負責渲染
export const dialogState = reactive({
    open: false,
    type: 'confirm', // confirm | alert | prompt
    message: '',
    title: '',
    defaultValue: '',
    danger: false,
    resolve: null,
});

const show = (type, message, options = {}) =>
    new Promise((resolve) => {
        // 若已有未關閉的對話框,先以取消結果收掉
        if (dialogState.open && dialogState.resolve) {
            dialogState.resolve(type === 'prompt' ? null : false);
        }
        Object.assign(dialogState, {
            open: true,
            type,
            message,
            title: options.title ?? (type === 'alert' ? '提示' : type === 'prompt' ? '請輸入' : '請確認'),
            defaultValue: options.defaultValue ?? '',
            danger: options.danger ?? /刪除|移除|取消|停權|駁回|不可逆|無法復原/.test(message),
            resolve,
        });
    });

export const confirmDialog = (message, options) => show('confirm', message, options);
export const alertDialog = (message, options) => show('alert', message, options).then(() => undefined);
export const promptDialog = (message, defaultValue = '', options = {}) =>
    show('prompt', message, { ...options, defaultValue });
