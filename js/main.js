/**
 * Dream Closets - Main JavaScript
 * Handles navigation, animations, forms, and interactivity
 */

document.addEventListener('DOMContentLoaded', function() {
    Navigation.init();
    ScrollEffects.init();
    StatCounter.init();
    TestimonialSlider.init();
    BackToTop.init();
    SmoothScroll.init();
    CookieConsent.init();
    ChatWidget.init();
    ParticleNetwork.init();
    CursorGlow.init();
    ParallaxEffects.init();
    ContactForm.init();
    FAQ.init();
});

/**
 * Navigation Module
 */
const Navigation = {
    init: function() {
        this.header = document.getElementById('header');
        this.navToggle = document.querySelector('.nav-toggle');
        this.navMenu = document.querySelector('.nav-menu');
        this.navLinks = document.querySelectorAll('.nav-menu a');

        this.bindEvents();
    },

    bindEvents: function() {
        if (this.navToggle) {
            this.navToggle.addEventListener('click', () => this.toggleMenu());
        }

        this.navLinks.forEach(link => {
            link.addEventListener('click', () => this.closeMenu());
        });

        document.addEventListener('click', (e) => {
            if (this.navMenu && !e.target.closest('.nav') && this.navMenu.classList.contains('active')) {
                this.closeMenu();
            }
        });

        window.addEventListener('scroll', () => this.handleScroll());
    },

    toggleMenu: function() {
        this.navMenu.classList.toggle('active');
        this.navToggle.classList.toggle('active');
        document.body.classList.toggle('menu-open');
    },

    closeMenu: function() {
        this.navMenu.classList.remove('active');
        this.navToggle.classList.remove('active');
        document.body.classList.remove('menu-open');
    },

    handleScroll: function() {
        if (window.scrollY > 100) {
            this.header.classList.add('scrolled');
        } else {
            this.header.classList.remove('scrolled');
        }
    }
};

/**
 * Scroll Effects Module
 */
const ScrollEffects = {
    init: function() {
        this.animatedElements = document.querySelectorAll(
            '.service-card, .portfolio-card, .testimonial-card, .capability-item, .stat-item, .faq-item, .pricing-card, .process-step, .gallery-item'
        );

        this.observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        this.setupObserver();
    },

    setupObserver: function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Staggered animation: find siblings in same grid
                    const parent = entry.target.parentElement;
                    const siblings = Array.from(parent.children).filter(
                        child => child.classList.contains('animate-ready') && !child.classList.contains('animate-in')
                    );
                    const index = siblings.indexOf(entry.target);
                    const delay = Math.max(0, index) * 100;

                    setTimeout(() => {
                        entry.target.classList.add('animate-in');
                    }, delay);
                    observer.unobserve(entry.target);
                }
            });
        }, this.observerOptions);

        this.animatedElements.forEach(el => {
            el.classList.add('animate-ready');
            observer.observe(el);
        });
    }
};

/**
 * Stat Counter Module
 */
const StatCounter = {
    init: function() {
        this.counters = document.querySelectorAll('[data-count]');
        if (this.counters.length === 0) return;

        this.animated = new Set();
        this.setupObserver();
    },

    setupObserver: function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.animated.has(entry.target)) {
                    this.animated.add(entry.target);
                    this.animateCounter(entry.target);
                }
            });
        }, { threshold: 0.5 });

        this.counters.forEach(counter => observer.observe(counter));
    },

    animateCounter: function(el) {
        const target = parseInt(el.dataset.count);
        const suffix = el.dataset.suffix || '';
        const duration = 2000;
        const startTime = performance.now();

        const update = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(eased * target);

            el.textContent = current + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                el.textContent = target + suffix;
            }
        };

        requestAnimationFrame(update);
    }
};

/**
 * Testimonial Slider Module
 */
const TestimonialSlider = {
    init: function() {
        this.grid = document.querySelector('.testimonials-grid');
        this.cards = document.querySelectorAll('.testimonial-card');
        this.prevBtn = document.querySelector('.testimonial-prev');
        this.nextBtn = document.querySelector('.testimonial-next');
        this.dotsContainer = document.querySelector('.testimonial-dots');

        if (!this.grid || this.cards.length === 0) return;

        this.currentPage = 0;
        this.cardsPerPage = this.getCardsPerPage();
        this.totalPages = Math.ceil(this.cards.length / this.cardsPerPage);

        this.createDots();
        this.bindEvents();
        this.showPage(0);
    },

    getCardsPerPage: function() {
        const width = window.innerWidth;
        if (width < 768) return 1;
        if (width < 1024) return 2;
        return 3;
    },

    createDots: function() {
        if (!this.dotsContainer) return;
        this.dotsContainer.innerHTML = '';
        for (let i = 0; i < this.totalPages; i++) {
            const dot = document.createElement('span');
            dot.className = 'testimonial-dot' + (i === 0 ? ' active' : '');
            dot.addEventListener('click', () => this.showPage(i));
            this.dotsContainer.appendChild(dot);
        }
    },

    bindEvents: function() {
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.prev());
        }
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.next());
        }

        window.addEventListener('resize', () => {
            const newPerPage = this.getCardsPerPage();
            if (newPerPage !== this.cardsPerPage) {
                this.cardsPerPage = newPerPage;
                this.totalPages = Math.ceil(this.cards.length / this.cardsPerPage);
                this.currentPage = Math.min(this.currentPage, this.totalPages - 1);
                this.createDots();
                this.showPage(this.currentPage);
            }
        });
    },

    showPage: function(page) {
        this.currentPage = page;

        this.cards.forEach((card, index) => {
            const start = page * this.cardsPerPage;
            const end = start + this.cardsPerPage;
            card.style.display = (index >= start && index < end) ? '' : 'none';
        });

        // Update dots
        const dots = this.dotsContainer ? this.dotsContainer.querySelectorAll('.testimonial-dot') : [];
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === page);
        });
    },

    prev: function() {
        if (this.currentPage > 0) {
            this.showPage(this.currentPage - 1);
        }
    },

    next: function() {
        if (this.currentPage < this.totalPages - 1) {
            this.showPage(this.currentPage + 1);
        }
    }
};

/**
 * Back to Top Module
 */
const BackToTop = {
    init: function() {
        this.button = document.querySelector('.back-to-top');
        if (!this.button) return;

        this.bindEvents();
    },

    bindEvents: function() {
        window.addEventListener('scroll', () => this.toggleVisibility());
        this.button.addEventListener('click', () => this.scrollToTop());
    },

    toggleVisibility: function() {
        if (window.scrollY > 500) {
            this.button.classList.add('visible');
        } else {
            this.button.classList.remove('visible');
        }
    },

    scrollToTop: function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
};

/**
 * Smooth Scroll Module
 */
const SmoothScroll = {
    init: function() {
        this.bindEvents();
    },

    bindEvents: function() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => this.handleClick(e, anchor));
        });
    },

    handleClick: function(e, anchor) {
        const href = anchor.getAttribute('href');
        if (href === '#') return;

        const target = document.querySelector(href);
        if (target) {
            e.preventDefault();

            const headerOffset = 80;
            const elementPosition = target.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    }
};

/**
 * Cookie Consent Module
 */
const CookieConsent = {
    init: function() {
        this.banner = document.getElementById('cookieConsent');
        this.acceptBtn = document.getElementById('cookieAccept');

        if (!this.banner) return;

        if (!localStorage.getItem('dc-cookie-consent')) {
            setTimeout(() => {
                this.banner.classList.add('show');
            }, 2000);
        }

        if (this.acceptBtn) {
            this.acceptBtn.addEventListener('click', () => this.accept());
        }
    },

    accept: function() {
        localStorage.setItem('dc-cookie-consent', 'true');
        this.banner.classList.remove('show');
    }
};

/**
 * Chat Widget Module
 */
const ChatWidget = {
    init: function() {
        this.widget = document.getElementById('chatWidget');
        this.toggle = this.widget ? this.widget.querySelector('.chat-toggle') : null;
        this.close = this.widget ? this.widget.querySelector('.chat-close') : null;

        if (!this.widget) return;

        this.bindEvents();
    },

    bindEvents: function() {
        if (this.toggle) {
            this.toggle.addEventListener('click', () => this.togglePanel());
        }
        if (this.close) {
            this.close.addEventListener('click', () => this.closePanel());
        }

        document.addEventListener('click', (e) => {
            if (this.widget && !e.target.closest('.chat-widget') && this.widget.classList.contains('active')) {
                this.closePanel();
            }
        });
    },

    togglePanel: function() {
        this.widget.classList.toggle('active');
    },

    closePanel: function() {
        this.widget.classList.remove('active');
    }
};

/**
 * Particle Network Module
 */
const ParticleNetwork = {
    init: function() {
        this.canvas = document.getElementById('particle-network');
        if (!this.canvas) return;

        this.ctx = this.canvas.getContext('2d');
        this.particles = [];
        this.particleCount = window.innerWidth < 768 ? 40 : 80;
        this.maxDistance = 150;
        this.time = 0;

        this.resize();
        this.createParticles();
        this.animate();

        window.addEventListener('resize', () => this.resize());
    },

    resize: function() {
        this.canvas.width = this.canvas.offsetWidth;
        this.canvas.height = this.canvas.offsetHeight;
    },

    createParticles: function() {
        this.particles = [];
        for (let i = 0; i < this.particleCount; i++) {
            this.particles.push({
                x: Math.random() * this.canvas.width,
                y: Math.random() * this.canvas.height,
                vx: (Math.random() - 0.5) * 0.4,
                vy: (Math.random() - 0.5) * 0.4,
                baseRadius: Math.random() * 2 + 1,
                radius: Math.random() * 2 + 1,
                opacity: Math.random() * 0.5 + 0.2,
                pulseSpeed: Math.random() * 0.02 + 0.01,
                pulseOffset: Math.random() * Math.PI * 2
            });
        }
    },

    animate: function() {
        this.time += 1;
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;

            if (p.x < 0 || p.x > this.canvas.width) p.vx *= -1;
            if (p.y < 0 || p.y > this.canvas.height) p.vy *= -1;

            // Pulsing radius
            p.radius = p.baseRadius + Math.sin(this.time * p.pulseSpeed + p.pulseOffset) * 0.8;

            // Glow effect
            this.ctx.save();
            this.ctx.shadowBlur = 8;
            this.ctx.shadowColor = `rgba(201, 169, 110, ${p.opacity * 0.5})`;

            this.ctx.beginPath();
            this.ctx.arc(p.x, p.y, Math.max(0.5, p.radius), 0, Math.PI * 2);
            this.ctx.fillStyle = `rgba(201, 169, 110, ${p.opacity})`;
            this.ctx.fill();
            this.ctx.restore();
        });

        for (let i = 0; i < this.particles.length; i++) {
            for (let j = i + 1; j < this.particles.length; j++) {
                const dx = this.particles[i].x - this.particles[j].x;
                const dy = this.particles[i].y - this.particles[j].y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < this.maxDistance) {
                    const opacity = (1 - dist / this.maxDistance) * 0.18;
                    this.ctx.beginPath();
                    this.ctx.moveTo(this.particles[i].x, this.particles[i].y);
                    this.ctx.lineTo(this.particles[j].x, this.particles[j].y);
                    this.ctx.strokeStyle = `rgba(201, 169, 110, ${opacity})`;
                    this.ctx.lineWidth = 0.6;
                    this.ctx.stroke();
                }
            }
        }

        requestAnimationFrame(() => this.animate());
    }
};

/**
 * Cursor Glow Module - Interactive dreamy spotlight on hero
 */
const CursorGlow = {
    init: function() {
        this.hero = document.querySelector('.hero');
        if (!this.hero || window.innerWidth < 768) return;

        this.glowEl = document.createElement('div');
        this.glowEl.className = 'cursor-glow';
        this.hero.appendChild(this.glowEl);

        this.canvas = document.createElement('canvas');
        this.canvas.style.cssText = 'width:100%;height:100%;';
        this.glowEl.appendChild(this.canvas);
        this.ctx = this.canvas.getContext('2d');

        this.mouseX = -500;
        this.mouseY = -500;
        this.currentX = -500;
        this.currentY = -500;

        this.resize();
        this.bindEvents();
        this.render();
    },

    resize: function() {
        this.canvas.width = this.hero.offsetWidth;
        this.canvas.height = this.hero.offsetHeight;
    },

    bindEvents: function() {
        this.hero.addEventListener('mousemove', (e) => {
            const rect = this.hero.getBoundingClientRect();
            this.mouseX = e.clientX - rect.left;
            this.mouseY = e.clientY - rect.top;
        });

        this.hero.addEventListener('mouseleave', () => {
            this.mouseX = -500;
            this.mouseY = -500;
        });

        window.addEventListener('resize', () => this.resize());
    },

    render: function() {
        // Smooth follow
        this.currentX += (this.mouseX - this.currentX) * 0.08;
        this.currentY += (this.mouseY - this.currentY) * 0.08;

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        const gradient = this.ctx.createRadialGradient(
            this.currentX, this.currentY, 0,
            this.currentX, this.currentY, 250
        );
        gradient.addColorStop(0, 'rgba(201, 169, 110, 0.08)');
        gradient.addColorStop(0.5, 'rgba(201, 169, 110, 0.03)');
        gradient.addColorStop(1, 'rgba(201, 169, 110, 0)');

        this.ctx.fillStyle = gradient;
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        requestAnimationFrame(() => this.render());
    }
};

/**
 * Parallax Effects Module - Subtle scroll-based movement
 */
const ParallaxEffects = {
    init: function() {
        this.heroContent = document.querySelector('.hero-content');
        this.hero = document.querySelector('.hero');

        if (!this.hero || window.innerWidth < 768) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        this.bindEvents();
    },

    bindEvents: function() {
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.update();
                    ticking = false;
                });
                ticking = true;
            }
        });
    },

    update: function() {
        const scrollY = window.scrollY;
        const heroHeight = this.hero.offsetHeight;

        // Only apply when hero is in view
        if (scrollY > heroHeight) return;

        const progress = scrollY / heroHeight;

        if (this.heroContent) {
            this.heroContent.style.transform = `translateY(${scrollY * 0.15}px)`;
            this.heroContent.style.opacity = 1 - progress * 0.6;
        }
    }
};

/**
 * Contact Form Module
 */
const ContactForm = {
    init: function() {
        this.form = document.getElementById('consultation-form');
        if (!this.form) return;

        this.bindEvents();
    },

    bindEvents: function() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearError(input));
        });

        const phoneInput = this.form.querySelector('#phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', (e) => this.formatPhone(e));
        }
    },

    handleSubmit: function(e) {
        e.preventDefault();
        if (this.validateForm()) {
            this.submitForm();
        }
    },

    validateForm: function() {
        let isValid = true;
        const requiredFields = this.form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    },

    validateField: function(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }

        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        }

        if (field.type === 'tel' && value) {
            const phoneRegex = /^[\d\s\-\(\)]{10,}$/;
            if (!phoneRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number';
            }
        }

        if (!isValid) {
            this.showError(field, errorMessage);
        } else {
            this.clearError(field);
        }

        return isValid;
    },

    showError: function(field, message) {
        const formGroup = field.closest('.form-group');
        formGroup.classList.add('has-error');

        const existingError = formGroup.querySelector('.error-message');
        if (existingError) existingError.remove();

        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.cssText = 'color: #f44336; font-size: 0.75rem; margin-top: 0.25rem;';
        formGroup.appendChild(errorDiv);

        field.style.borderColor = '#f44336';
    },

    clearError: function(field) {
        const formGroup = field.closest('.form-group');
        formGroup.classList.remove('has-error');

        const errorMessage = formGroup.querySelector('.error-message');
        if (errorMessage) errorMessage.remove();

        field.style.borderColor = '';
    },

    formatPhone: function(e) {
        const input = e.target;
        const previousValue = input.dataset.previousValue || '';
        let value = input.value.replace(/\D/g, '');
        const previousDigits = previousValue.replace(/\D/g, '');

        if (value.length >= previousDigits.length) {
            if (value.length >= 6) {
                value = `(${value.slice(0,3)}) ${value.slice(3,6)}-${value.slice(6,10)}`;
            } else if (value.length >= 3) {
                value = `(${value.slice(0,3)}) ${value.slice(3)}`;
            }
        }

        input.value = value;
        input.dataset.previousValue = value;
    },

    submitForm: function() {
        const submitBtn = this.form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        const formData = new FormData(this.form);

        const baseUrl = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
        const phpUrl = baseUrl + 'send-quote.php';

        fetch(phpUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Server returned ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                this.showSuccessMessage(data.message);
                this.form.reset();
            } else {
                this.showErrorMessage(data.message || 'Something went wrong. Please try again.');
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            this.showErrorMessage('Failed to send. Please call us directly at (770) 555-1234.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    },

    showSuccessMessage: function(message) {
        const modal = document.createElement('div');
        modal.className = 'success-modal';
        modal.innerHTML = `
            <div class="success-modal-content">
                <div class="success-icon"><i class="fas fa-check"></i></div>
                <h3>Thank You!</h3>
                <p>${message}</p>
                <button class="btn btn-primary" onclick="this.closest('.success-modal').remove()">Close</button>
            </div>
        `;

        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:10000;animation:fadeIn 0.3s ease;';

        const content = modal.querySelector('.success-modal-content');
        content.style.cssText = 'background:#1a1a1a;padding:3rem;border-radius:12px;text-align:center;max-width:400px;animation:slideUp 0.3s ease;border:1px solid #2a2a2a;color:#e0e0e0;';

        const icon = modal.querySelector('.success-icon');
        icon.style.cssText = 'width:80px;height:80px;background:#4CAF50;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1.5rem;';

        const h3 = modal.querySelector('h3');
        h3.style.cssText = 'color:#ffffff;margin-bottom:1rem;font-family:Playfair Display,serif;';

        document.body.appendChild(modal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
    },

    showErrorMessage: function(message) {
        const modal = document.createElement('div');
        modal.className = 'error-modal';
        modal.innerHTML = `
            <div class="error-modal-content">
                <div class="error-icon"><i class="fas fa-times"></i></div>
                <h3>Oops!</h3>
                <p>${message || 'Something went wrong. Please try again or call us directly.'}</p>
                <button class="btn btn-primary" onclick="this.closest('.error-modal').remove()">Close</button>
            </div>
        `;

        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:10000;animation:fadeIn 0.3s ease;';

        const content = modal.querySelector('.error-modal-content');
        content.style.cssText = 'background:#1a1a1a;padding:3rem;border-radius:12px;text-align:center;max-width:400px;animation:slideUp 0.3s ease;border:1px solid #2a2a2a;color:#e0e0e0;';

        const icon = modal.querySelector('.error-icon');
        icon.style.cssText = 'width:80px;height:80px;background:#c9a96e;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 1.5rem;';

        const h3 = modal.querySelector('h3');
        h3.style.cssText = 'color:#ffffff;margin-bottom:1rem;font-family:Playfair Display,serif;';

        document.body.appendChild(modal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
    }
};

/**
 * FAQ Module
 */
const FAQ = {
    init: function() {
        this.faqItems = document.querySelectorAll('.faq-item');
        this.bindEvents();
    },

    bindEvents: function() {
        this.faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            if (question) {
                question.addEventListener('click', () => this.toggleItem(item));
            }
        });
    },

    toggleItem: function(item) {
        const isActive = item.classList.contains('active');

        this.faqItems.forEach(faq => {
            faq.classList.remove('active');
        });

        if (!isActive) {
            item.classList.add('active');
        }
    }
};
