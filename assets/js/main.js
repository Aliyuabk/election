 (function() {
        // Preloader
        const preloader = document.getElementById('preloader');
        window.addEventListener('load', function() {
            preloader.classList.add('hidden');
        });
        setTimeout(() => { preloader.classList.add('hidden'); }, 2200);

        // Sticky nav
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 40) navbar.classList.add('scrolled');
            else navbar.classList.remove('scrolled');
        });

        // Sidebar (right) + overlay
        const hamburger = document.getElementById('hamburger');
        const sidebarMenu = document.getElementById('sidebarMenu');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const closeSidebarBtn = document.getElementById('closeSidebar');

        function openSidebar() {
            sidebarMenu.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebarMenu.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebarMenu.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        closeSidebarBtn.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // close on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebarMenu.classList.contains('open')) {
                closeSidebar();
            }
        });

        // close sidebar on window resize > 768 (desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebarMenu.classList.contains('open')) {
                closeSidebar();
            }
        });
    })();

    // FAQ Toggle Function
function toggleFaq(element) {
    const answer = element.nextElementSibling;
    const icon = element.querySelector('i');
    
    // Close all other FAQs
    const allQuestions = document.querySelectorAll('.faq-question');
    allQuestions.forEach(q => {
        if (q !== element) {
            q.classList.remove('active');
            q.nextElementSibling.classList.remove('open');
        }
    });
    
    // Toggle current FAQ
    element.classList.toggle('active');
    answer.classList.toggle('open');
}

// Auto-open first FAQ on page load (optional)
document.addEventListener('DOMContentLoaded', function() {
    const firstFaq = document.querySelector('.faq-question');
    if (firstFaq) {
        setTimeout(() => {
            firstFaq.classList.add('active');
            firstFaq.nextElementSibling.classList.add('open');
        }, 1000);
    }
});