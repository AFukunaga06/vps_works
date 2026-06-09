document.addEventListener('DOMContentLoaded', function () {
    // Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach(el => new bootstrap.Tooltip(el));

    // 問診票: pain_scale スライダー表示
    const slider = document.getElementById('pain_scale');
    const sliderVal = document.getElementById('pain_scale_val');
    if (slider && sliderVal) {
        sliderVal.textContent = slider.value;
        slider.addEventListener('input', () => { sliderVal.textContent = slider.value; });
    }

    // 生年月日 → 年齢リアルタイム表示
    const bdInput = document.getElementById('birth_date');
    const ageSpan  = document.getElementById('age_display');
    if (bdInput && ageSpan) {
        const calcAge = () => {
            const v = bdInput.value;
            if (!v) { ageSpan.textContent = ''; return; }
            const b = new Date(v), n = new Date();
            let age = n.getFullYear() - b.getFullYear();
            const m = n.getMonth() - b.getMonth();
            if (m < 0 || (m === 0 && n.getDate() < b.getDate())) age--;
            ageSpan.textContent = `（${age}歳）`;
        };
        bdInput.addEventListener('change', calcAge);
        calcAge();
    }
});
