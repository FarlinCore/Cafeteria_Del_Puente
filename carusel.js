// FRAGMENTO DE TAILDWIND

document.addEventListener('DOMContentLoaded', () => {
    const track = document.getElementById('track');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const dotsContainer = document.getElementById('indicators');
    const container = document.getElementById('carousel-container');

    let index = 0;
    const items = track.children;
    const itemsPerView = window.innerWidth >= 768 ? 3 : 1;
    const maxIndex = items.length - itemsPerView;
    let autoPlayInterval;

    // Generar Puntos 
    for (let i = 0; i <= maxIndex; i++) {
        const dot = document.createElement('button');
        dot.className = `h-3 rounded-full transition-all duration-300 ${i === 0 ? 'bg-[#F47E24] w-8' : 'bg-gray-300 w-3'}`;
        dot.addEventListener('click', () => { index = i; updateCarousel(); });
        dotsContainer.appendChild(dot);
    }
    const dots = dotsContainer.children;

    function updateCarousel() {

        const cardWidth = items[0].getBoundingClientRect().width;


        track.style.transform = `translateX(-${index * cardWidth}px)`;


        Array.from(dots).forEach((dot, i) => {
            dot.className = `h-3 rounded-full transition-all duration-300 ${i === index ? 'bg-[#F47E24] w-8' : 'bg-gray-300 w-3'}`;
        });
    }

    function nextSlide() {
        index = index < maxIndex ? index + 1 : 0;
        updateCarousel();
    }

    function prevSlide() {
        index = index > 0 ? index - 1 : maxIndex;
        updateCarousel();
    }

    // Eventos
    nextBtn.addEventListener('click', () => { nextSlide(); resetTimer(); });
    prevBtn.addEventListener('click', () => { prevSlide(); resetTimer(); });

    // Autoplay y Pausa
    function startTimer() { autoPlayInterval = setInterval(nextSlide, 4000); }
    function resetTimer() { clearInterval(autoPlayInterval); startTimer(); }
    container.addEventListener('mouseenter', () => clearInterval(autoPlayInterval));
    container.addEventListener('mouseleave', startTimer);

    startTimer();
    window.addEventListener('resize', () => location.reload());
});