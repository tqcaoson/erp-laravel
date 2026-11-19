<!--
    Ô nhập số hiểu dấu phân cách nghìn kiểu Việt Nam.

    Vấn đề nó giải quyết: <input type="number"> của trình duyệt không hiểu
    "20,000" (trả về rỗng → submit fail im lặng) và hiểu "20.000" thành 20
    (sai lặng 1000 lần). Component này dùng <input type="text"> rồi tự parse.

    QUY TẮC PARSE (parseNumber):
    - Bỏ hết khoảng trắng (kể cả khoảng trắng không ngắt dòng): "20 000" → 20000.
    - Tiền VND (decimals = 0, mặc định): bỏ hết dấu "." và "," — mọi dấu đều
      là phân cách nghìn, không có thập phân. "20.000" / "20,000" → 20000.
    - Số lượng / số thập phân (decimals > 0, vd 4 khớp cột decimal(18,4)):
      + Có cả "." và "," → dấu xuất hiện SAU CÙNG là thập phân, dấu còn lại
        là phân cách nghìn: "1.234,56" → 1234.56; "1,234.56" → 1234.56.
      + Chỉ 1 loại dấu, xuất hiện nhiều lần → tất cả là phân cách nghìn:
        "1.234.567" → 1234567.
      + Chỉ 1 dấu duy nhất: phần sau dấu có đúng 3 chữ số → phân cách nghìn
        ("20.000" → 20000, theo quy ước VN); ngược lại là thập phân
        ("20.5" → 20.5; "0.0001" → 0.0001). Phần thập phân cắt tối đa
        `decimals` chữ số.
    - Khi blur, hiển thị lại theo định dạng vi-VN ("20.000", "19.500,5").
    - Giá trị nạp sẵn từ ngoài (vd fillPrice gán average_cost 19500.5) vẫn
      hiển thị đúng phần thập phân; chỉ khi người dùng sửa thì parse mới
      áp quy tắc trên (tiền ép số nguyên).
    - Giá trị rỗng / không parse được → emit null để backend validate bắt lỗi.

    Props:
    - modelValue: Number | null — v-model, emit số thuần (tương thích v-model
      hiện tại của các form, chỉ cần đổi v-model.number thành v-model).
    - decimals: số chữ số thập phân tối đa giữ lại (0 = tiền VND ép nguyên).
    - min / max: chặn dưới / chặn trên, áp khi blur (vd qty min 0.0001
      khớp backend, tỉ lệ % max 100).
    Các thuộc tính còn lại (class, placeholder, required, disabled...) được
    truyền thẳng xuống <input> nên dùng như input thường.
-->
<template>
    <input
        type="text"
        inputmode="decimal"
        autocomplete="off"
        :value="text"
        @input="onInput"
        @blur="onBlur"
    />
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Number, default: null },
    decimals: { type: Number, default: 0 },
    min: { type: Number, default: null },
    max: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue']);

/** Chuỗi đang hiển thị trong ô (người dùng gõ gì hiện nấy, chỉ format khi blur). */
const text = ref('');
/** Người dùng đã sửa ô từ lần format cuối chưa. */
const dirty = ref(false);

/**
 * Parse chuỗi nhập thành số theo quy tắc ở comment đầu file.
 * Trả về null khi rỗng hoặc không parse được.
 */
function parseNumber(raw, decimals) {
    if (raw === null || raw === undefined) return null;
    let s = String(raw).trim().replace(/[\s\u00A0\u202F]/g, '');
    if (s === '' || s === '-' || s === '+') return null;

    let negative = false;
    if (s.startsWith('-')) {
        negative = true;
        s = s.slice(1);
    } else if (s.startsWith('+')) {
        s = s.slice(1);
    }
    if (s === '') return null;

    const hasDot = s.includes('.');
    const hasComma = s.includes(',');
    let intPart = s;
    let fracPart = '';

    if (hasDot && hasComma) {
        const lastDot = s.lastIndexOf('.');
        const lastComma = s.lastIndexOf(',');
        if (lastDot > lastComma) {
            intPart = s.slice(0, lastDot).replace(/,/g, '');
            fracPart = s.slice(lastDot + 1);
        } else {
            intPart = s.slice(0, lastComma).replace(/\./g, '');
            fracPart = s.slice(lastComma + 1);
        }
    } else if (hasDot || hasComma) {
        const sep = hasDot ? '.' : ',';
        const parts = s.split(sep);
        if (parts.length > 2) {
            intPart = parts.join('');
        } else if (decimals > 0 && parts[1].length > 0 && parts[1].length !== 3) {
            intPart = parts[0];
            fracPart = parts[1];
        } else {
            // 1 dấu duy nhất, phần sau đúng 3 chữ số (hoặc decimals = 0):
            // dấu phân cách nghìn, bỏ đi.
            intPart = parts.join('');
        }
    }

    if (!/^\d*$/.test(intPart) || !/^\d*$/.test(fracPart)) return null;

    // Tiền VND (decimals = 0) không giữ thập phân; số lượng cắt tối đa
    // `decimals` chữ số (tránh lỗi làm tròn float khi nhập nhiều số 0).
    if (decimals === 0) {
        fracPart = '';
    } else if (fracPart.length > decimals) {
        fracPart = fracPart.slice(0, decimals);
    }

    const rawValue = fracPart === '' ? intPart : `${intPart || '0'}.${fracPart}`;
    if (rawValue === '') return null;
    const value = Number(rawValue);
    if (Number.isNaN(value)) return null;
    return negative ? -value : value;
}

/** Áp min/max (nếu có) sau khi parse. */
function clamp(value) {
    if (value === null) return null;
    if (props.min !== null && value < props.min) return props.min;
    if (props.max !== null && value > props.max) return props.max;
    return value;
}

/** Định dạng số để hiển thị (vi-VN). Giá trị nạp sẵn có thập phân thì hiện đủ. */
function formatValue(value) {
    if (value === null || value === undefined || value === '') return '';
    const n = Number(value);
    if (Number.isNaN(n)) return '';
    let shownDecimals = props.decimals;
    if (!Number.isInteger(n)) {
        // Giữ phần thập phân của giá trị nạp sẵn (vd average_cost 19500.5),
        // tối đa 4 chữ số khớp cột decimal(18,4).
        const fracLen = String(n).split('.')[1]?.length ?? 0;
        shownDecimals = Math.max(props.decimals, Math.min(4, fracLen));
    }
    return new Intl.NumberFormat('vi-VN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: shownDecimals,
    }).format(n);
}

// Đồng bộ hiển thị khi giá trị từ ngoài thay đổi (nạp sẵn, fillPrice...).
// Khi người dùng đang gõ (dirty) thì KHÔNG format lại: nếu không, gõ "20."
// sẽ bị format thành "20" ngay lập tức, nuốt dấu chấm và "20.5" thành "205".
watch(
    () => props.modelValue,
    (value) => {
        if (dirty.value) return;
        text.value = formatValue(value);
    },
    { immediate: true },
);

function onInput(event) {
    text.value = event.target.value;
    dirty.value = true;
    // Emit ngay để tổng tiền / thành tiền tính lại theo từng ký tự gõ.
    emit('update:modelValue', clamp(parseNumber(text.value, props.decimals)));
}

function onBlur() {
    if (!dirty.value) {
        text.value = formatValue(props.modelValue);
        return;
    }
    const value = clamp(parseNumber(text.value, props.decimals));
    emit('update:modelValue', value);
    text.value = formatValue(value);
    dirty.value = false;
}
</script>
