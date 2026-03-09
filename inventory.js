/* ============================================
   CARU CARS — Inventory Page Logic
   Filtering, sorting, rendering
   ============================================ */

(function() {
    const grid = document.getElementById('inventoryGrid');
    const emptyState = document.getElementById('inventoryEmpty');
    const resultsNum = document.getElementById('resultsNum');
    const clearBtn = document.getElementById('clearFilters');
    const countNum = document.getElementById('invCountNum');
    const filterMake = document.getElementById('filterMake');
    const filterType = document.getElementById('filterType');
    const filterPrice = document.getElementById('filterPrice');
    const filterSort = document.getElementById('filterSort');

    if (!grid || typeof INVENTORY === 'undefined') return;

    // Filter out $0 price (not for sale) vehicles
    const inventory = INVENTORY.filter(c => c.price > 0);

    // Populate makes dropdown dynamically
    const makes = [...new Set(inventory.map(c => c.make))].sort();
    makes.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m;
        opt.textContent = m;
        filterMake.appendChild(opt);
    });

    // Update hero count
    if (countNum) countNum.textContent = inventory.length;

    function formatPrice(p) {
        return '$' + p.toLocaleString('en-US');
    }

    function formatMiles(m) {
        return m.toLocaleString('en-US') + ' mi';
    }

    function getWeeklyPayment(price) {
        // Rough estimate: 48 month term, ~15% APR for BHPH
        const monthly = price * 0.028;
        return Math.round(monthly / 4.33);
    }

    function getBadge(car) {
        if (car.miles < 15000) return { en: "Low Miles!", es: "\u00a1Bajo Millaje!" };
        if (car.price < 8000) return { en: "Great Deal!", es: "\u00a1Gran Oferta!" };
        if (car.year >= 2023) return { en: "Like New!", es: "\u00a1Como Nuevo!" };
        if (car.type === "Truck") return { en: "Truck!", es: "\u00a1Truck!" };
        if (car.make === "Porsche" || car.make === "Maserati" || car.make === "Cadillac" || car.make === "Land Rover") return { en: "Luxury!", es: "\u00a1Lujo!" };
        return null;
    }

    function getColorDot(color) {
        const map = {
            'Black': '#222', 'White': '#f0f0f0', 'Gray': '#888', 'Silver': '#c0c0c0',
            'Blue': '#2563eb', 'Red': '#dc2626', 'Brown': '#92400e', 'Orange': '#ea580c',
            'Beige': '#d4a574', 'Green': '#16a34a'
        };
        return map[color] || '#ccc';
    }

    function renderCard(car, index) {
        const badge = getBadge(car);
        const weekly = getWeeklyPayment(car.price);
        const lang = typeof currentLang !== 'undefined' ? currentLang : 'en';
        const hasImg = car.img && car.img.length > 0;

        return `
        <a href="vehicle.html?stock=${car.stock}" class="car-card-link">
        <div class="car-card" style="animation-delay: ${index * 60}ms">
            <div class="car-img-wrap">
                ${hasImg ?
                    `<img src="${car.img}" alt="${car.year} ${car.make} ${car.model}" class="car-img" loading="lazy">` :
                    `<div class="car-img-placeholder">
                        <div class="car-placeholder-inner">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M7 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"/><path d="M17 17m-2 0a2 2 0 104 0 2 2 0 10-4 0"/><path d="M5 17H3v-6l2-5h9l4 5h1a2 2 0 012 2v4h-2"/><path d="M9 17h6"/></svg>
                            <span class="placeholder-text">${car.year} ${car.make}</span>
                        </div>
                    </div>`
                }
                <div class="car-year-tag">${car.year}</div>
            </div>
            <div class="car-info">
                <div class="car-title-row">
                    <h3>${car.make} ${car.model}</h3>
                </div>
                <div class="car-specs">
                    <span class="car-spec">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        ${formatMiles(car.miles)}
                    </span>
                    <span class="car-spec">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                        ${car.type}
                    </span>
                    <span class="car-spec">
                        <span class="color-dot" style="background:${getColorDot(car.extColor)};${car.extColor === 'White' ? 'border:1px solid #ccc;' : ''}"></span>
                        ${car.extColor}
                    </span>
                </div>
                <div class="car-divider"></div>
                <div class="car-price-row">
                    <div class="car-price-block">
                        <span class="car-price">${formatPrice(car.price)}</span>
                    </div>
                    <span class="car-cta-btn" data-en="View Details" data-es="Ver Detalles">${lang === 'es' ? 'Ver Detalles' : 'View Details'}</span>
                </div>
            </div>
        </div>
        </a>`;
    }

    function filterAndRender() {
        let filtered = [...inventory];

        const make = filterMake.value;
        const type = filterType.value;
        const maxPrice = filterPrice.value;
        const sort = filterSort.value;

        if (make) filtered = filtered.filter(c => c.make === make);
        if (type) filtered = filtered.filter(c => c.type === type);
        if (maxPrice) filtered = filtered.filter(c => c.price <= parseInt(maxPrice));

        // Sort
        switch (sort) {
            case 'newest':
                filtered.sort((a, b) => b.year - a.year || a.price - b.price);
                break;
            case 'price-low':
                filtered.sort((a, b) => a.price - b.price);
                break;
            case 'price-high':
                filtered.sort((a, b) => b.price - a.price);
                break;
            case 'miles-low':
                filtered.sort((a, b) => a.miles - b.miles);
                break;
        }

        // Render
        grid.innerHTML = filtered.map((car, i) => renderCard(car, i)).join('');
        resultsNum.textContent = filtered.length;

        // Show/hide empty state
        emptyState.style.display = filtered.length === 0 ? 'block' : 'none';
        grid.style.display = filtered.length === 0 ? 'none' : 'grid';

        // Show/hide clear button
        const hasFilters = make || type || maxPrice;
        clearBtn.style.display = hasFilters ? 'inline-flex' : 'none';

        // Re-apply language after render
        if (typeof setLanguage === 'function' && typeof currentLang !== 'undefined') {
            setLanguage(currentLang);
        }

        // Trigger scroll reveal for new cards
        requestAnimationFrame(() => {
            document.querySelectorAll('.car-card').forEach(card => card.classList.add('visible'));
        });
    }

    // Event listeners
    [filterMake, filterType, filterPrice, filterSort].forEach(el => {
        el.addEventListener('change', filterAndRender);
    });

    clearBtn.addEventListener('click', () => {
        filterMake.value = '';
        filterType.value = '';
        filterPrice.value = '';
        filterSort.value = 'newest';
        filterAndRender();
    });

    // Initial render
    filterAndRender();
})();
