/* ============================================
   CARU CARS — Main JavaScript
   Language toggle, scroll animations, nav
   ============================================ */

// ============ LANGUAGE TOGGLE ============
let currentLang = 'en';
let hasChosenLang = !!localStorage.getItem('carucars-lang');
if (hasChosenLang) currentLang = localStorage.getItem('carucars-lang');

function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem('carucars-lang', lang);

    // Update toggle buttons (fixed + mobile nav)
    document.querySelectorAll('.lang-btn, .nav-lang-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.lang === lang);
    });

    // Update all translatable elements
    document.querySelectorAll('[data-en]').forEach(el => {
        const text = el.getAttribute('data-' + lang);
        if (text) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.placeholder = text;
            } else {
                el.innerHTML = text;
            }
        }
    });

    // Update HTML lang attribute
    document.documentElement.lang = lang === 'es' ? 'es' : 'en';
}

// Initialize language on page load
document.addEventListener('DOMContentLoaded', () => {
    setLanguage(currentLang);

    // Show language picker on first visit only
    if (!hasChosenLang) {
        var overlay = document.createElement('div');
        overlay.className = 'lang-picker-overlay';
        overlay.innerHTML = `
            <div class="lang-picker">
                <div class="lang-picker-logo"><span style="color:#1d2939;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.3rem;">CARU</span><span style="color:#e63946;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.3rem;">CARS</span></div>
                <p class="lang-picker-text">Choose your language<br><span style="opacity:0.6;">Elige tu idioma</span></p>
                <div class="lang-picker-btns">
                    <button class="lang-pick-btn" data-pick="en">
                        <span class="lang-pick-flag">EN</span>
                        <span>English</span>
                    </button>
                    <button class="lang-pick-btn" data-pick="es">
                        <span class="lang-pick-flag">ES</span>
                        <span>Espa\u00f1ol</span>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        // Animate in
        requestAnimationFrame(() => { overlay.classList.add('visible'); });

        overlay.querySelectorAll('.lang-pick-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                setLanguage(btn.dataset.pick);
                overlay.classList.remove('visible');
                setTimeout(() => { overlay.remove(); }, 300);
            });
        });
    }
});


// ============ MOBILE MENU ============
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');

if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('open');
        navLinks.classList.toggle('open');
    });

    // Close menu on link click
    navLinks.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('open');
            navLinks.classList.remove('open');
        });
    });
}


// ============ NAVBAR SCROLL ============
const navbar = document.getElementById('navbar');
let lastScroll = 0;

window.addEventListener('scroll', () => {
    const currentScroll = window.scrollY;
    if (navbar) {
        navbar.classList.toggle('scrolled', currentScroll > 50);
    }
    lastScroll = currentScroll;
}, { passive: true });


// ============ SCROLL REVEAL ANIMATIONS ============
function revealOnScroll() {
    const elements = document.querySelectorAll('.bhph-card, .car-card, .step-card, .testimonial-card, .reveal');
    const windowHeight = window.innerHeight;

    elements.forEach((el, i) => {
        const rect = el.getBoundingClientRect();
        const offset = 80;

        if (rect.top < windowHeight - offset && rect.bottom > 0) {
            // Stagger the animation based on sibling index
            const siblings = el.parentElement.children;
            let siblingIndex = 0;
            for (let j = 0; j < siblings.length; j++) {
                if (siblings[j] === el) { siblingIndex = j; break; }
            }
            setTimeout(() => {
                el.classList.add('visible');
            }, siblingIndex * 100);
        }
    });
}

window.addEventListener('scroll', revealOnScroll, { passive: true });
window.addEventListener('load', revealOnScroll);


// ============ FAQ ACCORDION ============
document.querySelectorAll('.faq-item').forEach(item => {
    const question = item.querySelector('.faq-q');
    if (question) {
        question.addEventListener('click', () => {
            // Close others
            document.querySelectorAll('.faq-item').forEach(other => {
                if (other !== item) other.classList.remove('open');
            });
            item.classList.toggle('open');
        });
    }
});


// ============ KINETIC TILT ON CARDS ============
document.querySelectorAll('.bhph-card, .car-card, .step-card').forEach(card => {
    card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = (y - centerY) / centerY * -4;
        const rotateY = (x - centerX) / centerX * 4;
        card.style.transform = `translateY(-8px) perspective(600px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    });

    card.addEventListener('mouseleave', () => {
        card.style.transform = '';
    });
});


// ============ SMOOTH COUNTER (for stats if any) ============
function animateCounter(el, target, duration = 1500) {
    let start = 0;
    const step = (timestamp) => {
        if (!start) start = timestamp;
        const progress = Math.min((timestamp - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        el.textContent = Math.floor(eased * target);
        if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}


// ============ PARALLAX SUBTLE ============
window.addEventListener('scroll', () => {
    const heroBg = document.querySelector('.hero-bg');
    if (heroBg) {
        const scrolled = window.scrollY;
        heroBg.style.transform = `scale(${1 + scrolled * 0.0002}) translateY(${scrolled * 0.15}px)`;
    }
}, { passive: true });


// Chat bubble + lead popup loaded from chat.js
