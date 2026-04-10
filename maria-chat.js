/* ============================================
   CARU CARS — Chat Bubble + Lead Capture Popup
   ============================================ */

(function() {
    // Skip chat bubble on contact page
    if (window.location.pathname.indexOf('contact') !== -1) return;

    var lang = (typeof currentLang !== 'undefined') ? currentLang : 'en';

    // ---- Chat Bubble HTML ----
    var bubble = document.createElement('div');
    bubble.id = 'chatBubbleWrap';
    bubble.innerHTML =
        '<div class="chat-bubble" onclick="openLeadPopup()">' +
            '<div class="chat-bubble-tooltip">' +
                '<span data-en="Have questions about our Buy Here Pay Here process? Just ask!" ' +
                'data-es="&iquest;Tienes preguntas sobre nuestro proceso Compra Aqu&iacute; Paga Aqu&iacute;? &iexcl;Preg&uacute;ntanos!">' +
                (lang === 'es' ? '\u00bfTienes preguntas sobre nuestro proceso Compra Aqu\u00ed Paga Aqu\u00ed? \u00a1Preg\u00fantanos!' : 'Have questions about our Buy Here Pay Here process? Just ask!') +
                '</span>' +
            '</div>' +
            '<div class="chat-bubble-avatar">' +
                '<img src="maria.png" alt="Maria" width="56" height="56">' +
                '<span class="chat-bubble-pulse"></span>' +
            '</div>' +
            '<span class="chat-bubble-name">Maria</span>' +
        '</div>';
    document.body.appendChild(bubble);

    // ---- Lead Popup HTML ----
    var popup = document.createElement('div');
    popup.id = 'leadPopupWrap';
    popup.innerHTML =
        '<div class="lead-overlay" id="leadOverlay" onclick="closeLeadPopup()">' +
            '<div class="lead-popup" onclick="event.stopPropagation()">' +
                '<button class="lead-popup-close" onclick="closeLeadPopup()">&times;</button>' +
                '<div class="lead-popup-header">' +
                    '<div class="lead-popup-avatar">' +
                        '<img src="maria.png" alt="Maria" width="48" height="48">' +
                    '</div>' +
                    '<div>' +
                        '<strong>Maria</strong>' +
                        '<p data-en="Let\'s get you on the road! Fill out your info and we\'ll reach out today." ' +
                        'data-es="&iexcl;Vamos a ponerte en el camino! Llena tu info y te contactamos hoy.">' +
                        (lang === 'es' ? '\u00a1Vamos a ponerte en el camino! Llena tu info y te contactamos hoy.' : "Let's get you on the road! Fill out your info and we'll reach out today.") +
                        '</p>' +
                    '</div>' +
                '</div>' +
                '<div class="lead-popup-body">' +
                    '<form id="leadForm" onsubmit="submitLeadForm(event)">' +
                        '<div class="lead-form-group">' +
                            '<input type="text" id="leadName" placeholder="' + (lang === 'es' ? 'Tu Nombre' : 'Your Name') + '" data-en="Your Name" data-es="Tu Nombre" required>' +
                        '</div>' +
                        '<div class="lead-form-group">' +
                            '<input type="tel" id="leadPhone" placeholder="' + (lang === 'es' ? 'Tu Tel\u00e9fono' : 'Your Phone') + '" data-en="Your Phone" data-es="Tu Tel\u00e9fono" required>' +
                        '</div>' +
                        '<div class="lead-form-group">' +
                            '<input type="email" id="leadEmail" placeholder="' + (lang === 'es' ? 'Tu Email' : 'Your Email') + '" data-en="Your Email" data-es="Tu Email">' +
                        '</div>' +
                        '<div class="lead-form-group">' +
                            '<input type="text" id="leadZip" placeholder="' + (lang === 'es' ? 'C\u00f3digo Postal' : 'Zip Code') + '" data-en="Zip Code" data-es="C&oacute;digo Postal" inputmode="numeric" pattern="[0-9]{5}" maxlength="5">' +
                        '</div>' +
                        '<button type="submit" class="lead-submit-btn" data-en="Get Pre-Approved Today!" data-es="&iexcl;Pre-Apr&oacute;bate Hoy!">' +
                        (lang === 'es' ? '\u00a1Pre-Apru\u00e9bate Hoy!' : 'Get Pre-Approved Today!') +
                        '</button>' +
                    '</form>' +
                    '<div class="lead-thank-you" id="leadThankYou" style="display:none">' +
                        '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-5"/></svg>' +
                        '<h3 data-en="Thank You!" data-es="&iexcl;Gracias!">' + (lang === 'es' ? '\u00a1Gracias!' : 'Thank You!') + '</h3>' +
                        '<p data-en="We\'ll call you shortly. Talk soon!" data-es="Te llamamos pronto. &iexcl;Hablamos!">' +
                        (lang === 'es' ? 'Te llamamos pronto. \u00a1Hablamos!' : "We'll call you shortly. Talk soon!") +
                        '</p>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    document.body.appendChild(popup);

    // ---- Auto-hide tooltip on mobile after 6s to reduce clutter ----
    setTimeout(function() {
        var tip = document.querySelector('.chat-bubble-tooltip');
        if (tip) {
            tip.style.transition = 'opacity 0.5s ease';
            tip.style.opacity = '0';
            setTimeout(function() { tip.style.display = 'none'; }, 500);
        }
    }, 8000); // 2s delay for animation + 6s visible

    // ---- Auto-popup after 10s on a new section ----
    if (!sessionStorage.getItem('carucars-lead-shown')) {
        var sections = document.querySelectorAll('section');
        var popupTimer = null;
        var popupTriggered = false;

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting && !popupTriggered) {
                    if (popupTimer) clearTimeout(popupTimer);
                    popupTimer = setTimeout(function() {
                        if (!popupTriggered) {
                            popupTriggered = true;
                            openLeadPopup();
                            sessionStorage.setItem('carucars-lead-shown', '1');
                            observer.disconnect();
                        }
                    }, 10000);
                }
            });
        }, { threshold: 0.3 });

        sections.forEach(function(sec) { observer.observe(sec); });
    }

    // ---- Global functions ----
    window.openLeadPopup = function() {
        var overlay = document.getElementById('leadOverlay');
        if (overlay) overlay.classList.add('active');
    };

    window.closeLeadPopup = function() {
        var overlay = document.getElementById('leadOverlay');
        if (overlay) overlay.classList.remove('active');
    };

    window.submitLeadForm = function(e) {
        e.preventDefault();
        var name = document.getElementById('leadName').value;
        var phone = document.getElementById('leadPhone').value;
        var email = document.getElementById('leadEmail').value;
        var zip = document.getElementById('leadZip').value;

        var formData = new FormData();
        formData.append('access_key', '2521447a-cb11-4639-b08f-9c11d8cb7cec');
        formData.append('subject', 'New Lead - Caru Cars Chat');
        formData.append('name', name);
        formData.append('phone', phone);
        formData.append('email', email || 'N/A');
        formData.append('zip', zip || 'N/A');

        fetch('https://api.web3forms.com/submit', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                document.getElementById('leadForm').style.display = 'none';
                document.getElementById('leadThankYou').style.display = 'flex';
                setTimeout(function() { closeLeadPopup(); }, 3000);
            })
            .catch(function() {
                alert('Something went wrong. Please call us at (786) 428-4008.');
                closeLeadPopup();
            });
    };

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLeadPopup();
    });
})();
