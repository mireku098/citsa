<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle logout confirmation
if (isset($_POST['confirm_logout'])) {
    // Destroy all session data
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Logging Out</title>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Aggressive responsive styles for SweetAlert2 on mobile */
        @media (max-width: 768px) {
           /* Mobile - force full screen style */
@media (max-width: 480px) {
    .logout-popup {
        width: 100vw !important;
        height: 100vh !important;
        max-width: 100vw !important;
        max-height: 100vh !important;
        margin: 0 !important;
        padding: 40px 20px !important;
        border-radius: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
    }

    .logout-popup .swal2-title {
        font-size: 28px !important;
        font-weight: bold !important;
        margin-bottom: 25px !important;
        text-align: center !important;
    }

    .logout-popup .swal2-html-container {
        font-size: 20px !important;
        margin-bottom: 30px !important;
        text-align: center !important;
    }

    .logout-popup .swal2-actions {
        flex-direction: column !important;
        gap: 15px !important;
        width: 100% !important;
    }

    .logout-popup .swal2-confirm,
    .logout-popup .swal2-cancel {
        width: 100% !important;
        padding: 20px !important;
        font-size: 20px !important;
        font-weight: 600 !important;
        min-height: 60px !important;
        border-radius: 8px !important;
    }

    .logout-popup .swal2-icon {
        transform: scale(1.4) !important;
        margin: 20px auto !important;
    }
        }
        
        /* Force these styles on all screen sizes for testing */
        .swal2-popup {
            min-width: 300px !important;
        }
    </style>
</head>
<body>
    <script>
            Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out of your account!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'logout-popup'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'logout.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'confirm_logout';
                input.value = '1';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            } else {
                window.history.back();
            }
        });
        
        // Force apply styles after popup renders
        setTimeout(function() {
            const popup = document.querySelector('.swal2-popup');
            if (popup) {
                if (window.innerWidth <= 480) {
                    popup.style.width = '100vw';
                    popup.style.height = '100vh';
                    popup.style.maxWidth = '100vw';
                    popup.style.maxHeight = '100vh';
                    popup.style.margin = '0';
                    popup.style.padding = '40px 20px';
                    popup.style.borderRadius = '0';
                    popup.style.position = 'fixed';
                    popup.style.top = '0';
                    popup.style.left = '0';
                    popup.style.right = '0';
                    popup.style.bottom = '0';
                    
                    // Make title bigger
                    const title = popup.querySelector('.swal2-title');
                    if (title) {
                        title.style.fontSize = '28px';
                        title.style.fontWeight = 'bold';
                        title.style.marginBottom = '30px';
                    }
                    
                    // Make content bigger
                    const content = popup.querySelector('.swal2-content');
                    if (content) {
                        content.style.fontSize = '24px';
                        content.style.marginBottom = '40px';
                    }
                    
                    // Make buttons bigger
                    const buttons = popup.querySelectorAll('.swal2-confirm, .swal2-cancel');
                    buttons.forEach(button => {
                        button.style.width = '100%';
                        button.style.padding = '25px';
                        button.style.fontSize = '22px';
                        button.style.fontWeight = '600';
                        button.style.minHeight = '70px';
                        button.style.margin = '10px 0';
                    });
                    
                    // Make icon bigger
                    const icon = popup.querySelector('.swal2-icon');
                    if (icon) {
                        icon.style.transform = 'scale(1.5)';
                        icon.style.margin = '30px auto';
                    }
                }
            }
        }, 100);
    </script>
</body>
</html>