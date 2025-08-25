<!-- ======= Footer ======= -->
<footer class="footer">
    <div class="container">
        <div class="copyright">
            &copy; Copyright <strong><span>CITSA Connect</span></strong>. All Rights Reserved
        </div>
    </div>
</footer>

<!-- Back to top button -->
<a href="#" class="back-to-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
</a>

<style>
    /* Footer styling */
    .footer {
        background: var(--white);
        border-top: 1px solid rgba(26, 60, 109, 0.1);
        padding: 1.5rem 0;
        text-align: center;
        margin-top: 3rem;
    }

    .footer .copyright {
        color: var(--text-light);
        font-size: 0.9rem;
    }

    .footer .copyright strong {
        color: var(--primary-color);
        font-weight: 600;
    }

    /* Back to top button */
    .back-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: var(--primary-color);
        color: var(--white);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-shadow: 0 4px 20px rgba(26, 60, 109, 0.3);
        transition: all 0.3s ease;
        z-index: 1001;
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
    }

    .back-to-top.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .back-to-top:hover {
        background: var(--secondary-color);
        color: var(--white);
        transform: translateY(-3px);
        box-shadow: 0 6px 25px rgba(26, 60, 109, 0.4);
    }

    .back-to-top i {
        font-size: 1.2rem;
    }
</style>

<script>
// Back to top functionality
document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.querySelector('.back-to-top');
    
    // Show/hide back to top button based on scroll position
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });
    
    // Smooth scroll to top when button is clicked
    backToTopButton.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});
</script> 