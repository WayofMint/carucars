/* ============================================
   CARU CARS — Vehicle Detail Page Logic
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
            'Black': '#222', 'White': '#f0f0f0', 'Gray': '#888', 'Silver': '#c0c0c0',
            'Blue': '#2563eb', 'Red': '#dc2626', 'Brown': '#92400e', 'Orange': '#ea580c',
            'Beige': '#d4a574', 'Green': '#16a34a'
        };
        return map[color] || '#ccc';
    }

    const hasImg = car.img && car.img.length > 0;

    layout.innerHTML = `
        <div class="vehicle-gallery">
            ${hasImg ?
                `<img src="${car.img}" alt="${car.year} ${car.make} ${car.model}" class="vehicle-main-img">` :
                `<div class="vehicle-img-placeholder">
                    <div class="vehicle-placeholder-inner">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M7 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"/><path d="M17 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"/><path d="M5 17H3v-6l2-5h9l4 5h1a2 2 0 012 2v4h-2"/><path d="M9 17h6"/></svg>
                        <span class="vehicle-placeholder-text" data-en="Photo Coming Soon" data-es="Foto Pr&oacute;ximamente">${lang === 'es' ? 'Foto Pr\u00f3ximamente' : 'Photo Coming Soon'}</span>
                    </div>
                </div>`
            }
        </div>
        <div class="vehicle-info">
            <div class="vehicle-title-block">
                <span class="vehicle-year-label">${car.year}</span>
                <h1 class="vehicle-title">${car.make} ${car.model}</h1>
                <p class="vehicle-trim">${car.trim}</p>
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
                    <span class="vehicle-spec-label" data-en="Type" data-es="Tipo">Type</span>
                    <span class="vehicle-spec-value">${car.type}</span>
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

    // Re-apply language
    if (typeof setLanguage === 'function' && typeof currentLang !== 'undefined') {
        setLanguage(currentLang);
    }
})();
