// =========================================
// 01. FRIENDLY CURSOR PHYSICS
// =========================================
const cursorDot = document.getElementById('cursor-dot');
const cursorOutline = document.getElementById('cursor-outline');

let mouseX = window.innerWidth / 2;
let mouseY = window.innerHeight / 2;
let outlineX = mouseX;
let outlineY = mouseY;
const speed = 0.15; 

window.addEventListener('mousemove', (e) => {
    mouseX = e.clientX;
    mouseY = e.clientY;
    
    cursorDot.style.setProperty('--x', `${mouseX}px`);
    cursorDot.style.setProperty('--y', `${mouseY}px`);
});

function animateCursor() {
    outlineX += (mouseX - outlineX) * speed;
    outlineY += (mouseY - outlineY) * speed;
    
    cursorOutline.style.setProperty('--x', `${outlineX}px`);
    cursorOutline.style.setProperty('--y', `${outlineY}px`);
    
    requestAnimationFrame(animateCursor);
}
animateCursor();

window.addEventListener('mousedown', () => cursorOutline.classList.add('click-state'));
window.addEventListener('mouseup', () => cursorOutline.classList.remove('click-state'));


// =========================================
// 02. PRELOADER & INITIALIZATION
// =========================================
document.addEventListener("DOMContentLoaded", () => {
    const preloader = document.getElementById('preloader');
    
    const sessionId = Math.random().toString(16).slice(2, 8).toUpperCase();
    document.getElementById('session-id').innerText = sessionId;

    const clockElement = document.getElementById('live-clock');
    const updateClock = () => {
        const now = new Date();
        clockElement.innerText = now.toLocaleTimeString('en-US', { hour12: false });
    };
    updateClock(); 
    setInterval(updateClock, 1000);

    setTimeout(() => {
        preloader.style.opacity = '0';
        
        setTimeout(() => {
            preloader.style.display = 'none';
            document.body.style.overflowY = 'auto'; 
            
            document.dispatchEvent(new Event('mileleReady'));
        }, 400); 
        
    }, 1500); 
});


// =========================================
// 03. NAV COMPRESSION ON SCROLL
// =========================================
const navbar = document.getElementById('navbar');

window.addEventListener('scroll', () => {
    if (window.scrollY > 80) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});


// =========================================
// 04. HERO ENTRANCE SEQUENCER
// =========================================
document.addEventListener('mileleReady', () => {
    navbar.style.opacity = '1';

    document.querySelector('.hero-corner-tl').style.opacity = '1';
    document.querySelector('.hero-corner-tr').style.opacity = '1';

    const heroContent = document.querySelector('.hero-content');
    heroContent.style.opacity = '1';
    heroContent.style.transform = 'translateY(0)';

    document.querySelector('.hero-bottom').style.opacity = '1';
    
    const buttons = document.querySelectorAll('.micro-btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', () => cursorOutline.classList.add('hover-state'));
        btn.addEventListener('mouseleave', () => cursorOutline.classList.remove('hover-state'));
    });
});


// =========================================
// 05. SCROLL REVEAL (INTERSECTION OBSERVER)
// =========================================
const observerOptions = {
    root: null,
    rootMargin: '0px',
    threshold: 0.1 
};

const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.classList.add('active');
            }, index * 100); 
            
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

const revealElements = document.querySelectorAll('.reveal-up');
revealElements.forEach(el => revealObserver.observe(el));


// =========================================
// 06. HORIZONTAL SCROLL PHYSICS
// =========================================
const processSection = document.getElementById('process-section');
const processTrack = document.getElementById('process-track');
const progressFill = document.getElementById('progress-fill');

window.addEventListener('scroll', () => {
    if (!processSection || !processTrack) return;

    const rect = processSection.getBoundingClientRect();
    const scrollProgress = -rect.top / (rect.height - window.innerHeight);
    const clampedProgress = Math.max(0, Math.min(1, scrollProgress));

    const maxScroll = processTrack.scrollWidth - window.innerWidth + (window.innerWidth * 0.1); 

    processTrack.style.transform = `translateX(${-clampedProgress * maxScroll}px)`;
    progressFill.style.width = `${clampedProgress * 100}%`;
});


// =========================================
// 07. INTERACTIVE HOVER STATES
// =========================================
const interactiveElements = document.querySelectorAll('.work-row, .service-card, .process-panel');
interactiveElements.forEach(el => {
    el.addEventListener('mouseenter', () => cursorOutline.classList.add('hover-state'));
    el.addEventListener('mouseleave', () => cursorOutline.classList.remove('hover-state'));
});