/**
 * Định dạng tiền tệ và ngày tháng theo locale Việt Nam.
 * Tiền: 1.250.000 ₫ — Ngày: dd/MM/yyyy (mục 7.2 của thiết kế).
 */
export function useFormat() {
    const money = (value, currency = 'VND') => {
        if (value === null || value === undefined || value === '') return '—';
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency,
            maximumFractionDigits: currency === 'VND' ? 0 : 2,
        }).format(value);
    };

    const number = (value, decimals = 0) => {
        if (value === null || value === undefined || value === '') return '—';
        return new Intl.NumberFormat('vi-VN', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value);
    };

    const date = (value) => {
        if (!value) return '—';
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(value));
    };

    const datetime = (value) => {
        if (!value) return '—';
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    };

    return { money, number, date, datetime };
}
