document.addEventListener('DOMContentLoaded', function () {

    // --- General Helper Functions ---
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => document.querySelectorAll(selector);

    // --- Loading Screen --- (Improved: Use class toggle)
    const loadingScreen = $('#loading-screen');
    if (loadingScreen) {
        // Ensure it's visible initially if needed by CSS
        loadingScreen.classList.remove('hidden');
        window.addEventListener('load', function () {
            // Add a minimum display time (e.g., 500ms) + fade out
            setTimeout(() => {
                loadingScreen.classList.add('hidden');
            }, 500);
        });
    }

    // --- Set current year in footer ---
    const yearSpan = document.getElementById('current-year');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();

    // --- Mobile Menu Handling (Only for main site if applicable) ---
    const menuToggle = $('#menu-toggle');
    const closeMenu = $('#close-menu');
    const mobileMenu = $('#mobile-menu');

    if (menuToggle && mobileMenu && closeMenu) { // Ensure all elements exist
        menuToggle.addEventListener('click', function () {
            mobileMenu.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });

        closeMenu.addEventListener('click', function () {
            mobileMenu.classList.remove('active');
            document.body.style.overflow = ''; // Re-enable scrolling
        });

        // Close mobile menu when clicking links inside it
        $$('#mobile-menu a').forEach(link => {
            link.addEventListener('click', function () {
                mobileMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }

    // --- Scroll Reveal Animation (For main site sections) ---
    const revealElements = $$('.reveal');
    if (revealElements.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    // Optional: Stop observing once revealed
                    // observer.unobserve(entry.target);
                } else {
                    // Optional: Remove class when scrolling up out of view
                    // entry.target.classList.remove('active');
                }
            });
        }, { threshold: 0.1 }); // Trigger when 10% visible

        revealElements.forEach(element => {
            observer.observe(element);
        });
    }


    // --- Universal Form Submission Handler (AJAX) ---
    $$('form.ajax-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const actionUrl = form.action;
            const method = form.method.toUpperCase();
            const errorDiv = form.querySelector('.alert-danger'); // Standardize error display
            const successDiv = form.querySelector('.alert-success'); // Standardize success display
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton ? submitButton.textContent : 'Submit';

            // Clear previous messages
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }

            try {
                const response = await fetch(actionUrl, {
                    method: method,
                    body: formData,
                    headers: {
                        'Accept': 'application/json' // Expect JSON response
                    }
                });

                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const textResponse = await response.text();
                    throw new Error(`Received non-JSON response from server: ${textResponse}`);
                }

                const data = await response.json();

                if (data.success) {
                    if (successDiv) {
                        successDiv.textContent = data.message || 'Operation successful!';
                        successDiv.style.display = 'block';
                    }
                    // Optional: Reset form on success
                    // form.reset(); 

                    // Handle redirects
                    if (data.redirect) {
                        // Show success message briefly before redirecting
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, successDiv ? 1500 : 0); // Delay if showing message
                    } else if (form.id === 'profileForm' && formData.get('mode') === 'edit') {
                        // Special handling for profile edit: maybe update UI elements if needed
                        console.log('Profile updated successfully.');
                    } else {
                        // If no redirect, maybe reload part of the page or just show success
                        // e.g., if adding an item to a list, refresh the list
                        // window.location.reload(); // Simple reload as a fallback
                    }

                } else {
                    if (errorDiv) {
                        errorDiv.textContent = data.message || 'An error occurred.';
                        errorDiv.style.display = 'block';
                    } else {
                        // Fallback alert if no dedicated error div
                        alert(`Error: ${data.message || 'An unknown error occurred.'}`);
                    }
                }

            } catch (error) {
                console.error('Form Submission Error:', error);
                if (errorDiv) {
                    errorDiv.textContent = `An unexpected error occurred: ${error.message}`;
                    errorDiv.style.display = 'block';
                } else {
                    alert(`An unexpected error occurred: ${error.message}`);
                }
            } finally {
                // Re-enable button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            }
        });
    });

    // --- Add specific JS for individual pages below if needed ---

    // Example: If on the medication page, add specific listeners
    if (document.body.contains($('#medications-page-identifier'))) { // Add an ID to the body or a main container on medications.html
        console.log('Medication page specific script loaded');
        // Add medication specific JS here (like the edit/add form toggles)
        const addMedBtn = document.getElementById('add-med-btn');
        const medFormContainer = document.getElementById('med-form-container');
        const cancelMedBtn = document.getElementById('cancel-med-btn');
        const medFormTitle = document.getElementById('form-title');
        const medIdInput = document.getElementById('med_id');
        const medForm = medFormContainer ? medFormContainer.querySelector('form') : null;

        if (addMedBtn && medFormContainer && medForm) {
            addMedBtn.addEventListener('click', function () {
                medFormContainer.style.display = 'block';
                if (medFormTitle) medFormTitle.textContent = 'Add New Medication';
                medForm.reset();
                if (medIdInput) medIdInput.value = '';
                addMedBtn.style.display = 'none'; // Hide button when form is open
            });
        }

        if (cancelMedBtn && medFormContainer) {
            cancelMedBtn.addEventListener('click', function () {
                medFormContainer.style.display = 'none';
                if (medForm) medForm.reset();
                if (addMedBtn) addMedBtn.style.display = 'inline-block'; // Show add button again
            });
        }

        // Add event listeners for edit buttons (delegation might be better if list is dynamic)
        $$('.edit-med-link').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const medId = this.dataset.id;
                const row = this.closest('tr'); // Assuming data is in a table row

                if (!medFormContainer || !medForm || !medIdInput || !medFormTitle) return;

                // Populate form
                medForm.querySelector('#med_name').value = row.cells[0].textContent; // Adjust selectors based on your HTML
                medForm.querySelector('#dosage').value = row.cells[1].textContent;
                medForm.querySelector('#frequency').value = row.cells[2].textContent;
                // ... populate other fields ...
                medIdInput.value = medId;
                medFormTitle.textContent = 'Edit Medication';

                // Show form and hide 'Add' button
                medFormContainer.style.display = 'block';
                if (addMedBtn) addMedBtn.style.display = 'none';
                window.scrollTo(0, medFormContainer.offsetTop - var(--header - height, 70)); // Scroll to form
        });
    });
    }

// Add similar checks for other pages (dashboard, appointments, etc.)
if (document.body.contains($('#dashboard-page-identifier'))) {
    console.log('Dashboard specific script loaded');
    // Fetch dashboard summary data, etc.
}

// --- Dashboard and page-specific scripts ---
console.log('Dashboard and page-specific scripts loaded');

// Example: Update current year in footer
document.getElementById('current-year').textContent = new Date().getFullYear();

// Global AJAX helper function (optional)
async function fetchData(url, formData = null) {
    let options = { credentials: 'same-origin' };
    if (formData) {
        options.method = 'POST';
        options.body = formData;
    }
    let response = await fetch(url, options);
    return await response.json();
}

// Code for dashboard summaries
if (document.getElementById('user-greeting')) {
    fetchData('../process/get_user_data.php')
        .then(data => {
            if (data.success) {
                document.getElementById('user-greeting').textContent = `Welcome, ${data.userName || 'User'}!`;
                // Update counters
                document.getElementById('medication-count').textContent = `${data.medicationCount || 0} Active`;
                document.getElementById('appointment-count').textContent = `${data.appointmentCount || 0} Upcoming`;
                document.getElementById('prescription-count').textContent = `${data.prescriptionCount || 0} Total`;
            } else {
                window.location.href = 'login.html';
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
        });
}

document.addEventListener('DOMContentLoaded', function () {
    async function checkNotifications() {

        try {
            let response = await fetch('../process/get_notifications.php');
            let data = await response.json();
            if (data.success && data.count > 0) {
                document.getElementById('notifications-dot').style.display = 'inline-block';
            } else {
                document.getElementById('notifications-dot').style.display = 'none';
            }
        } catch (e) {
            console.error('Error fetching notifications:', e);
        }
    }
    checkNotifications();
    setInterval(checkNotifications, 30000);

    // Additional global JS such as toggling navigation or loading spinners can be added here.

    // --- END OF DOMContentLoaded ---
});
