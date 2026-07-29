document.addEventListener("DOMContentLoaded", () => {
    const sliders = document.querySelectorAll(
        '.slider-card input[type="range"]'
    );

    sliders.forEach((slider) => {
        const valueDisplay = slider
            .closest(".slider-card")
            .querySelector("label span");

        function updateSlider() {
            valueDisplay.textContent = `${slider.value}%`;

            slider.style.background = `linear-gradient(
                to right,
                #38bdf8 0%,
                #38bdf8 ${slider.value}%,
                #334155 ${slider.value}%,
                #334155 100%
            )`;
        }

        updateSlider();

        slider.addEventListener("input", updateSlider);
    });
});