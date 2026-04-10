/* ============================================
   CARU CARS — Vehicle Detail Page Logic
   Photo gallery + full specs from DealerCenter
   Mobile-first app-like experience
   ============================================ */

(function() {
    const layout = document.getElementById('vehicleLayout');
    if (!layout || typeof INVENTORY === 'undefined') return;

    function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s || '')); return d.innerHTML; }

    const params = new URLSearchParams(window.location.search);
    const stock = params.get('stock');

    if (!stock) { window.location.href = 'inventory.html'; return; }

    const car = INVENTORY.find(c => c.stock === stock);
    if (!car) { window.location.href = 'inventory.html'; return; }

    document.title = `${car.year} ${esc(car.make)} ${esc(car.model)} | Caru Cars`;

    const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
    const isMobile = window.innerWidth <= 768;

    function formatPrice(p) { return '$' + p.toLocaleString('en-US'); }
    function formatMiles(m) { return m.toLocaleString('en-US'); }

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

    // ---- Build Gallery HTML ----
    let galleryHTML = '';
    if (hasPhotos) {
        // Dot indicators for mobile, thumbs for desktop
        const dotsHTML = photoCount > 1 ? `<div class="gallery-dots" id="galleryDots">${car.photos.map((_, i) => `<span class="gallery-dot${i === 0 ? ' active' : ''}" data-index="${i}"></span>`).join('')}</div>` : '';
        const thumbsHTML = photoCount > 1 ? `<div class="gallery-thumbs" id="galleryThumbs">${car.photos.map((p, i) => `<img src="${p}" alt="Photo ${i+1}" class="gallery-thumb${i === 0 ? ' active' : ''}" data-index="${i}" loading="lazy">`).join('')}</div>` : '';

        galleryHTML = `
            <div class="vehicle-gallery">
                <div class="gallery-main">
                    <img src="${car.photos[0]}" alt="${car.year} ${esc(car.make)} ${esc(car.model)}" class="vehicle-main-img" id="galleryMainImg">
                    <div class="gallery-counter" id="galleryCounter">1 / ${photoCount}</div>
                    ${photoCount > 1 ? `
                    <button class="gallery-nav gallery-prev" id="galleryPrev" aria-label="Previous photo">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="gallery-nav gallery-next" id="galleryNext" aria-label="Next photo">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </button>` : ''}
                    ${dotsHTML}
                </div>
                ${thumbsHTML}
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

    // ---- Build Equipment (collapsible on mobile) ----
    let equipmentHTML = '';
    if (car.equipment && car.equipment.length > 0) {
        equipmentHTML = `
            <div class="vehicle-equipment">
                <button class="equipment-toggle" id="equipToggle">
                    <span class="vehicle-section-title" data-en="Features & Equipment" data-es="Caracter&iacute;sticas y Equipamiento">${lang === 'es' ? 'Caracter\u00edsticas y Equipamiento' : 'Features & Equipment'}</span>
                    <span class="equipment-count">${car.equipment.length}</span>
                    <svg class="equipment-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="equipment-grid" id="equipGrid" style="display:none;">
                    ${car.equipment.map(e => `<span class="equipment-tag"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg> ${esc(e)}</span>`).join('')}
                </div>
            </div>`;
    }

    // ---- Main Layout ----
    layout.innerHTML = `
        ${galleryHTML}
        <div class="vehicle-info">
            <div class="vehicle-header-row">
                <div class="vehicle-title-block">
                    <span class="vehicle-year-label">${car.year}</span>
                    <h1 class="vehicle-title">${esc(car.make)} ${esc(car.model)}</h1>
                    <p class="vehicle-trim">${esc(car.transmission) || esc(car.type)}</p>
                </div>
                <div class="vehicle-price-tag">
                    <span class="vehicle-price-original">${formatPrice(car.price)}</span>
                    <span class="vehicle-price">${formatPrice(car.price - 4000)}</span>
                    <span class="vehicle-price-down" data-en="w/ $4,000 down" data-es="c/ $4,000 inicial">w/ $4,000 down</span>
                </div>
            </div>

            <div class="bhph-badge" data-en="BUY HERE PAY HERE" data-es="COMPRA AQU&Iacute; PAGA AQU&Iacute;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span data-en="BUY HERE PAY HERE &mdash; No Credit Check &bull; Drive Today" data-es="COMPRA AQU&Iacute; PAGA AQU&Iacute; &mdash; Sin Chequeo de Cr&eacute;dito &bull; Maneja Hoy">BUY HERE PAY HERE &mdash; No Credit Check &bull; Drive Today</span>
            </div>

            <div class="vehicle-quick-specs">
                <div class="quick-spec">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <span>${formatMiles(car.miles)} mi</span>
                </div>
                <div class="quick-spec">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4"/><path d="M14 12h4"/></svg>
                    <span>${car.transmission ? esc(car.transmission.split(' ').slice(0,2).join(' ')) : esc(car.type)}</span>
                </div>
                <div class="quick-spec">
                    <span class="color-dot" style="background:${getColorDot(car.extColor)};${car.extColor === 'White' ? 'border:1px solid #ccc;' : ''}"></span>
                    <span>${esc(car.extColor)}</span>
                </div>
            </div>

            <div class="vehicle-price-block desktop-only">
                <span class="vehicle-price-original">${formatPrice(car.price)}</span>
                <span class="vehicle-price">${formatPrice(car.price - 4000)}</span>
                <span class="vehicle-price-down" data-en="w/ $4,000 down" data-es="c/ $4,000 inicial">w/ $4,000 down</span>
                <a href="tel:7864284008" class="vehicle-call-btn" data-en="Call Now &mdash; (786) 428-4008" data-es="Llama Ya &mdash; (786) 428-4008">Call Now &mdash; (786) 428-4008</a>
            </div>

            <div class="vehicle-specs-grid">
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Mileage" data-es="Millaje">Mileage</span>
                    <span class="vehicle-spec-value">${formatMiles(car.miles)} mi</span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Transmission" data-es="Transmisi&oacute;n">Transmission</span>
                    <span class="vehicle-spec-value">${esc(car.transmission) || 'N/A'}</span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Exterior Color" data-es="Color Exterior">Exterior Color</span>
                    <span class="vehicle-spec-value">
                        <span class="color-dot" style="background:${getColorDot(car.extColor)};${car.extColor === 'White' ? 'border:1px solid #ccc;' : ''}"></span>
                        ${esc(car.extColor)}
                    </span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Interior Color" data-es="Color Interior">Interior Color</span>
                    <span class="vehicle-spec-value">
                        <span class="color-dot" style="background:${getColorDot(car.intColor)};${car.intColor === 'White' ? 'border:1px solid #ccc;' : ''}"></span>
                        ${esc(car.intColor)}
                    </span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label">VIN</span>
                    <span class="vehicle-spec-value vehicle-vin">${esc(car.vin)}</span>
                </div>
                <div class="vehicle-spec-item">
                    <span class="vehicle-spec-label" data-en="Stock #" data-es="Stock #">Stock #</span>
                    <span class="vehicle-spec-value">${esc(car.stock)}</span>
                </div>
            </div>

            ${equipmentHTML}

            <a href="apply.html" class="vehicle-apply-banner">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span data-en="No Credit Check &bull; Apply for Financing" data-es="Sin Chequeo de Cr&eacute;dito &bull; Aplica para Financiamiento">No Credit Check &bull; Apply for Financing</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
            </a>

            <div class="vehicle-actions desktop-only">
                <a href="tel:7864284008" class="btn btn-primary vehicle-action-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    <span data-en="Call to Schedule Test Drive" data-es="Llama para Prueba de Manejo">Call to Schedule Test Drive</span>
                </a>
                <a href="financing.html" class="btn btn-outline vehicle-action-btn" data-en="Apply for Financing" data-es="Aplica para Financiamiento">Apply for Financing</a>
            </div>
        </div>
    `;

    // ---- Sticky Call Bar (mobile only) ----
    if (window.innerWidth <= 768) {
        var stickyBar = document.createElement('div');
        stickyBar.className = 'vehicle-sticky-bar';
        stickyBar.innerHTML = `
            <a href="tel:7864284008" class="sticky-call-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                <span data-en="Call Now &mdash; (786) 428-4008" data-es="Llama Ya &mdash; (786) 428-4008">Call Now &mdash; (786) 428-4008</span>
            </a>
            <a href="apply.html" class="sticky-apply-btn" data-en="Apply" data-es="Aplica">Apply</a>
        `;
        document.body.appendChild(stickyBar);
    }

    // ---- Equipment Toggle ----
    var equipToggle = document.getElementById('equipToggle');
    var equipGrid = document.getElementById('equipGrid');
    if (equipToggle && equipGrid) {
        equipToggle.addEventListener('click', function() {
            var open = equipGrid.style.display !== 'none';
            equipGrid.style.display = open ? 'none' : 'flex';
            equipToggle.classList.toggle('open', !open);
        });
    }

    // ---- Gallery Logic ----
    if (hasPhotos && photoCount > 1) {
        const mainImg = document.getElementById('galleryMainImg');
        const counter = document.getElementById('galleryCounter');
        const thumbs = document.querySelectorAll('.gallery-thumb');
        const dots = document.querySelectorAll('.gallery-dot');
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
            dots.forEach((d, i) => d.classList.toggle('active', i === index));

            var activeThumb = document.querySelector('.gallery-thumb.active');
            if (activeThumb) activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        prevBtn.addEventListener('click', () => showPhoto(currentIndex - 1));
        nextBtn.addEventListener('click', () => showPhoto(currentIndex + 1));

        thumbs.forEach(thumb => {
            thumb.addEventListener('click', () => showPhoto(parseInt(thumb.dataset.index)));
        });

        dots.forEach(dot => {
            dot.addEventListener('click', () => showPhoto(parseInt(dot.dataset.index)));
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') showPhoto(currentIndex - 1);
            if (e.key === 'ArrowRight') showPhoto(currentIndex + 1);
        });

        // Swipe
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

    // ---- Fullscreen Lightbox ----
    if (hasPhotos) {
        var lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `
            <button class="lightbox-close" aria-label="Close">&times;</button>
            <div class="lightbox-counter" id="lightboxCounter">1 / ${photoCount}</div>
            <img class="lightbox-img" id="lightboxImg" src="" alt="">
            ${photoCount > 1 ? `
            <button class="lightbox-nav lightbox-prev" id="lightboxPrev" aria-label="Previous">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button class="lightbox-nav lightbox-next" id="lightboxNext" aria-label="Next">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </button>` : ''}
        `;
        document.body.appendChild(lightbox);

        var lbImg = document.getElementById('lightboxImg');
        var lbCounter = document.getElementById('lightboxCounter');
        var lbIndex = 0;

        function openLightbox(index) {
            lbIndex = index || 0;
            lbImg.src = car.photos[lbIndex];
            lbCounter.textContent = (lbIndex + 1) + ' / ' + photoCount;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }

        function lbShow(index) {
            if (index < 0) index = photoCount - 1;
            if (index >= photoCount) index = 0;
            lbIndex = index;
            lbImg.src = car.photos[lbIndex];
            lbCounter.textContent = (lbIndex + 1) + ' / ' + photoCount;
        }

        // Click main image to open lightbox
        var mainImg = document.getElementById('galleryMainImg');
        if (mainImg) {
            mainImg.style.cursor = 'zoom-in';
            mainImg.addEventListener('click', function() {
                var idx = 0;
                var currentSrc = mainImg.src;
                car.photos.forEach(function(p, i) { if (currentSrc.indexOf(p) !== -1) idx = i; });
                openLightbox(idx);
            });
        }

        // Close
        lightbox.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) closeLightbox();
        });

        // Nav
        if (photoCount > 1) {
            document.getElementById('lightboxPrev').addEventListener('click', function(e) { e.stopPropagation(); lbShow(lbIndex - 1); });
            document.getElementById('lightboxNext').addEventListener('click', function(e) { e.stopPropagation(); lbShow(lbIndex + 1); });
        }

        // Keyboard
        document.addEventListener('keydown', function(e) {
            if (!lightbox.classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') lbShow(lbIndex - 1);
            if (e.key === 'ArrowRight') lbShow(lbIndex + 1);
        });

        // Swipe in lightbox
        var lbTouchStart = 0;
        lightbox.addEventListener('touchstart', function(e) { lbTouchStart = e.changedTouches[0].screenX; }, { passive: true });
        lightbox.addEventListener('touchend', function(e) {
            var diff = e.changedTouches[0].screenX - lbTouchStart;
            if (Math.abs(diff) > 50) { diff > 0 ? lbShow(lbIndex - 1) : lbShow(lbIndex + 1); }
        }, { passive: true });
    }

    // Re-apply language
    if (typeof setLanguage === 'function' && typeof currentLang !== 'undefined') {
        setLanguage(currentLang);
    }
})();
