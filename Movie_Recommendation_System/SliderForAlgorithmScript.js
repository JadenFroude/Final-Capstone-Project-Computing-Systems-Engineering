// Gets all slider cards on the page
const sliderCards = document.querySelectorAll(".slider-card");

sliderCards.forEach(card => {

    const slider = card.querySelector("input[type='range']");
    const value = card.querySelector("span");

    // Set the initial value
    value.textContent = slider.value + "%";

    // Update while dragging
    slider.addEventListener("input", () => {
        value.textContent = slider.value + "%";
    });

});