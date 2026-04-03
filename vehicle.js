/* ============================================
   CARU CARS — Vehicle Detail Page Logic
   Photo gallery + full specs from DealerCenter
   ============================================ */

(function() {
    const layout = document.getElementById('vehicleLayout');
    if (!layout || typeof INVENTORY === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const stock = params.get('stock');

    if (!stock) { window.location.href = 'inventory.html'; return; }

    const car = INVENTORY.find(c => c.stock === stock);
    if (!car) { window.location.href = 'inventory.html'; return; }

    // Update page title
    document.title = `${car.year} ${car.make} ${car.model} | Caru Cars`;

    const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';

    function formatPrice(p) {
        return '$' + p.toLocaleString('en-US');
    }

    function formatMiles(m) {
        return m.toLocaleString('en-US');
    }

    function getColorDot(color) {
        const map = {
            'Black': '#222', 'White': '#f0f0f0', 'Gray': '#888', 'Grey': '#888',
            'Silver': '#c0c0c0', 'Blue': '#2563eb', 'Red': '#dc2626', 'Brown': '#92400e',
            'Orange': '#ea580c', 'Beige': '#d4a574', 'Green': '#16a34a', 'Gold': '#b8860b',
            'Maroon': '#800000', 'Burgundy': '#722F37', 'Tan': '#d2b48c'
        };
        return map[color] || '#ccc';
    }

    const hasPhotos = car.photos && car.photos.length > 0;
    const photoCount = hasPhotos ? car.photos.length : 0;

    // Build gallery HTML
    let galleryHTML = '';
    if (hasPhotos) {
        galleryHTML = `
            <div class="vehicle-gallery">
                <div class="gallery-main">
                    <img src="${car.photos[0]}" alt="${car.year} ${car.make} ${car.model}" class="vehicle-main-img" id="galleryMainImg">
                    <div class="gallery-counter" id="galleryCounter">1 / ${photoCount}</div>
                    ${photoCount > 1 ? `
                    <button class="gallery-nav gallery-prev" id="galleryPrev" aria-label="Previous photo">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="gallery-nav gallery-next" id="galleryNext" aria-label="Next photo">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </button>` : ''}
                </div>
                ${photoCount > 1 ? `
                <div class="gallery-thumbs" id="galleryThumbs">
                    ${car.photos.map((p, i) => `<img src="${p}" alt="Photo ${i+1}" class="gallery-thumb${i === 0 ? ' active' : ''}" data-index="${i}" loading="lazy">`).join('')}
                </div>` : ''}
            </div>`;
    } else {
        galleryHTML = `
            <div class="vehicle-gallery">
                <div class="vehicle-img-placeholder">
                    <div class="vehicle-placeholder-inner">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M7 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"/><path d="M17 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"/><path d="M5 17H3v-6l2-5h9l4 5h1a2 2 0 012 2v4h-2"/><path d="M9 17h6"/></svg>
                        <span class="vehicle-placeholder-text" data-en="Photo Coming Soon" data-es="Foto Pr&oacute;ximamente">${lang === 'es' ? 'Foto Pr\u00f3ximamente' : 'Photo Coming Soon'}</span>
                    </div>
                </div>
            </div>`;
    }

    // Build equipment list
    let equipmentHTML = '';
    if (car.equipment && car.equipment.length > 0) {
        equipmentHTML = `
            <div class="vehicle-equipment">
                <h3 class="vehicle-section-title" data-en="Features & Equipment" data-es="Caracter&iacute;sticas y Equipamiento">${lang === 'es' ? 'Caracter\u00edsticas y Equipamiento' : 'Features & Equipment'}</h3>
                <div class="equipment-grid">
                    ${car.equipment.map(e => `<span class="equipment-tag"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg> ${e}</span>`).join('')}
                </div>
            </div>`;
    }

    layout.innerHTML = `
        ${galleryHTML}
        <div class="vehicle-info">
            <div class="vehicle-title-block">
                <span class="vehicle-year-label">${car.year}</span>
                <h1 class="vehicle-title">${car.make} ${car.model}</h1>
                <p class="vehicle-trim">${car.transmission || car.type}</p>
            </div>

            <div class="vehicle-price-block">
                <span class="vehicle-price">${formatPrice(car.price)}</span>
                <a href="tel:7864284008" class="vehicle-call-btn" data-en="Call Now &mdash; (786) 428-4008" data-es="Llama Ya &mdash; (786) 428-4008">Call Now &mdash; (786) 428-4008</a>
            </div>

            <div class="vehicle-specs-grid">
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Mileage" data-es="Millaje">Mileage</span>
                    <span class="vehicle-spec-value">${formatMiles(car.miles)} mi</span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Transmission" data-es="Transmisi&oacute;n">Transmission</span>
                    <span class="vehicle-spec-value">${car.transmission || 'N/A'}</span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Exterior Color" data-es="Color Exterior">Exterior Color</span>
                    <span class="vehicle-spec-value">
                        <span class="color-dot" style="background:${getColorDot(car.extColor)};${car.extColor === 'White' ? 'border:1px solid #ccc;' : ''}"></span>
                        ${car.extColor}
                    </span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Interior Color" data-es="Color Interior">Interior Color</span>
                    <span class="vehicle-spec-value">
                        <span class="color-dot" style="background:${getColorDot(car.intColor)};${car.intColor === 'White' ? 'border:1px solid #ccc;' : ''}"></span>
                        ${car.intColor}
                    </span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label">VIN</span>
                    <span class="vehicle-spec-value vehicle-vin">${car.vin}</span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Stock #" data-es="Stock #">Stock #</span>
                    <span class="vehicle-spec-value">${car.stock}</span>
                </div>
            </div>

            ${equipmentHTML}

            <div class="vehicle-financing-note">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <div>
                    <strong data-en="Buy Here Pay Here Financing" data-es="Financiamiento Compra Aqu&iacute; Paga Aqu&iacute;">Buy Here Pay Here Financing</strong>
                    <p data-en="No credit check needed. Flexible weekly payments. Drive today!" data-es="Sin chequeo de cr&eacute;dito. Pagos semanales flexibles. &iexcl;Maneja hoy!">No credit check needed. Flexible weekly payments. Drive today!</p>
                </div>
            </div>

            <div class="vehicle-actions">
                <a href="tel:7864284008" class="btn btn-primary vehicle-action-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    <span data-en="Call to Schedule Test Drive" data-es="Llama para Prueba de Manejo">Call to Schedule Test Drive</span>
                </a>
                <a href="financing.html" class="btn btn-outline vehicle-action-btn" data-en="Apply for Financing" data-es="Aplica para Financiamiento">Apply for Financing</a>
            </div>
        </div>
    `;

    // ---- Gallery Logic ----
    if (hasPhotos && photoCount > 1) {
        const mainImg = document.getElementById('galleryMainImg');
        const counter = document.getElementById('galleryCounter');
        const thumbs = document.querySelectorAll('.gallery-thumb');
        const prevBtn = document.getElementById('galleryPrev');
        const nextBtn = document.getElementById('galleryNext');
        let currentIndex = 0;

        function showPhoto(index) {
            if (index < 0) index = photoCount - 1;
            if (index >= photoCount) index = 0;
            currentIndex = index;

            mainImg.src = car.photos[index];
            counter.textContent = `${index + 1} / ${photoCount}`;

            thumbs.forEach((t, i) => t.classList.toggle('active', i === index));

            // Scroll active thumb into view
            const activeThumb = document.querySelector('.gallery-thumb.active');
            if (activeThumb) activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        prevBtn.addEventListener('click', () => showPhoto(currentIndex - 1));
        nextBtn.addEventListener('click', () => showPhoto(currentIndex + 1));

        thumbs.forEach(thumb => {
            thumb.addEventListener('click', () => showPhoto(parseInt(thumb.dataset.index)));
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') showPhoto(currentIndex - 1);
            if (e.key === 'ArrowRight') showPhoto(currentIndex + 1);
        });

        // Swipe support for mobile
        let touchStartX = 0;
        const galleryMain = document.querySelector('.gallery-main');
        galleryMain.addEventListener('touchstart', (e) => { touchStartX = e.changedTouches[0].screenX; }, { passive: true });
        galleryMain.addEventListener('touchend', (e) => {
            const diff = e.changedTouches[0].screenX - touchStartX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) showPhoto(currentIndex - 1);
                else showPhoto(currentIndex + 1);
            }
        }, { passive: true });
    }

    // Re-apply language
    if (typeof setLanguage === 'function' && typeof currentLang !== 'undefined') {
        setLanguage(currentLang);
    }
})();
